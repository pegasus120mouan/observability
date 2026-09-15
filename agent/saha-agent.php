<?php

declare(strict_types=1);

/**
 * Lightweight SAHA collector (Phase 4).
 *
 * Registers with an enrollment token, stores the API key locally, then sends heartbeats, metrics, and logs.
 *
 *   php agent/saha-agent.php
 *   php agent/saha-agent.php --once
 *
 * Requires PHP 7.4+ (the SAHA server itself still needs PHP 8.3).
 */
if (PHP_VERSION_ID < 70400) {
    fwrite(STDERR, 'saha-agent requires PHP 7.4 or newer. This host has '.PHP_VERSION.".\n");
    exit(1);
}

$configPath = __DIR__.DIRECTORY_SEPARATOR.'agent.yml';

if (! is_file($configPath)) {
    fwrite(STDERR, "Missing {$configPath}\nCopy agent.yml next to saha-agent.php and set api_url + enrollment_token.\n");
    exit(1);
}

$config = parseSimpleYaml($configPath);
$apiUrl = rtrim((string) ($config['api_url'] ?? ''), '/');
$once = in_array('--once', $argv, true);
$GLOBALS['saha_verify_ssl'] = ! in_array(strtolower((string) ($config['verify_ssl'] ?? 'true')), ['0', 'false', 'no', 'off'], true);

if ($apiUrl === '') {
    fwrite(STDERR, "api_url is required in agent.yml\n");
    exit(1);
}

fwrite(STDOUT, "Using {$apiUrl}\n");

if (stripos($apiUrl, '127.0.0.1') !== false || stripos($apiUrl, 'localhost') !== false) {
    fwrite(STDERR, "api_url points at this machine. Set it to the public SAHA URL, e.g. https://observe.example.com/api/v1\n");
}

if (($config['agent_id'] ?? '') === '' || ($config['api_key'] ?? '') === '') {
    $config = registerAgent($apiUrl, $config, $configPath);
}

$interval = max(5, (int) ($config['heartbeat_interval'] ?? 10));
$httpInterval = 2;
$nextHeartbeat = 0;

do {
    $now = time();

    if ($once || $now >= $nextHeartbeat) {
        heartbeat($apiUrl, $config);
        reportMetrics($apiUrl, $config);
        reportLogs($apiUrl, $config);
        reportServices($apiUrl, $config);
        $nextHeartbeat = $now + $interval;
    }

    reportHttpRequests($apiUrl, $config);

    if ($once) {
        break;
    }

    sleep($httpInterval);
} while (true);

/**
 * @param  array<string, string>  $config
 * @return array<string, string>
 */
function registerAgent(string $apiUrl, array $config, string $configPath): array
{
    $token = trim((string) ($config['enrollment_token'] ?? ''));

    if ($token === '') {
        fwrite(STDERR, "Set enrollment_token in agent.yml, then rerun.\n");
        exit(1);
    }

    $os = detectOperatingSystem();

    $payload = [
        'enrollment_token' => $token,
        'hostname' => ($config['hostname'] ?? '') !== '' ? $config['hostname'] : php_uname('n'),
        'ip_address' => detectIp(),
        'operating_system' => $os['name'],
        'os_version' => $os['version'],
        'architecture' => php_uname('m'),
        'platform' => PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux',
        'version' => '0.1.0',
    ];

    $response = request('POST', $apiUrl.'/agent/register', $payload);

    if (($response['success'] ?? false) !== true) {
        fwrite(STDERR, 'Registration failed: '.json_encode($response)."\n");
        exit(1);
    }

    $data = $response['data'] ?? [];
    $config['agent_id'] = (string) ($data['agent_id'] ?? '');
    $config['api_key'] = (string) ($data['api_key'] ?? '');
    $config['heartbeat_interval'] = (string) (($data['config']['heartbeat_interval'] ?? $config['heartbeat_interval']) ?: '10');
    $config['enrollment_token'] = '';

    writeSimpleYaml($configPath, $config);

    fwrite(STDOUT, "Registered as {$config['agent_id']}. API key stored in agent.yml.\n");

    return $config;
}

/**
 * @param  array<string, string>  $config
 */
function heartbeat(string $apiUrl, array $config): void
{
    $response = request('POST', $apiUrl.'/agent/heartbeat', [
        'version' => '0.1.0',
        'ip_address' => detectIp(),
        'timestamp' => gmdate('c'),
    ], [
        'X-Agent-Id: '.$config['agent_id'],
        'Authorization: Bearer '.$config['api_key'],
    ]);

    if (($response['success'] ?? false) !== true) {
        fwrite(STDERR, '['.gmdate('c').'] Heartbeat failed: '.json_encode($response)."\n");

        return;
    }

    fwrite(STDOUT, '['.gmdate('c').'] Heartbeat ok status='.($response['data']['status'] ?? '?')."\n");
}

/**
 * @param  array<string, string>  $config
 */
function reportMetrics(string $apiUrl, array $config): void
{
    $metrics = collectHostMetrics();

    if ($metrics === []) {
        return;
    }

    $response = request('POST', $apiUrl.'/agent/metrics', [
        'timestamp' => gmdate('c'),
        'metrics' => $metrics,
    ], [
        'X-Agent-Id: '.$config['agent_id'],
        'Authorization: Bearer '.$config['api_key'],
    ]);

    if (($response['success'] ?? false) !== true) {
        fwrite(STDERR, '['.gmdate('c').'] Metrics failed: '.json_encode($response)."\n");

        return;
    }

    fwrite(STDOUT, '['.gmdate('c').'] Metrics inserted='.($response['data']['inserted'] ?? 0)."\n");
}

/**
 * @param  array<string, string>  $config
 */
function reportLogs(string $apiUrl, array $config): void
{
    $logs = collectHostLogs();

    if ($logs === []) {
        return;
    }

    $response = request('POST', $apiUrl.'/agent/logs', [
        'timestamp' => gmdate('c'),
        'logs' => $logs,
    ], [
        'X-Agent-Id: '.$config['agent_id'],
        'Authorization: Bearer '.$config['api_key'],
    ]);

    if (($response['success'] ?? false) !== true) {
        fwrite(STDERR, '['.gmdate('c').'] Logs failed: '.json_encode($response)."\n");

        return;
    }

    fwrite(STDOUT, '['.gmdate('c').'] Logs inserted='.($response['data']['inserted'] ?? 0).' skipped='.($response['data']['skipped'] ?? 0)."\n");
}

/**
 * @param  array<string, string>  $config
 */
function reportServices(string $apiUrl, array $config): void
{
    $services = collectRunningServices();

    $response = request('POST', $apiUrl.'/agent/services', [
        'timestamp' => gmdate('c'),
        'services' => $services,
    ], [
        'X-Agent-Id: '.$config['agent_id'],
        'Authorization: Bearer '.$config['api_key'],
    ]);

    if (($response['success'] ?? false) !== true) {
        fwrite(STDERR, '['.gmdate('c').'] Services failed: '.json_encode($response)."\n");

        return;
    }

    fwrite(STDOUT, '['.gmdate('c').'] Services running='.($response['data']['applications'] ?? 0).' stopped='.($response['data']['stopped'] ?? 0)."\n");
}

/**
 * @param  array<string, string>  $config
 */
function reportHttpRequests(string $apiUrl, array $config): void
{
    $batches = collectHttpRequests($config);
    $apacheRuntime = collectApacheRuntime($config);
    $batches = attachApacheRuntime($batches, $apacheRuntime);

    if ($batches === []) {
        warnMissingApacheSource();

        return;
    }

    foreach ($batches as $batch) {
        $payload = [
            'timestamp' => gmdate('c'),
            'name' => $batch['name'],
            'type' => $batch['type'],
        ];

        if (isset($batch['requests']) && $batch['requests'] !== []) {
            $payload['requests'] = $batch['requests'];
        }

        if (isset($batch['sample']) && $batch['sample'] !== []) {
            $payload['sample'] = $batch['sample'];
        }

        if (! isset($payload['requests']) && ! isset($payload['sample'])) {
            continue;
        }

        $response = request('POST', $apiUrl.'/agent/http-requests', $payload, [
            'X-Agent-Id: '.$config['agent_id'],
            'Authorization: Bearer '.$config['api_key'],
        ]);

        if (($response['success'] ?? false) !== true) {
            fwrite(STDERR, '['.gmdate('c').'] HTTP requests failed: '.json_encode($response)."\n");

            continue;
        }

        fwrite(STDOUT, '['.gmdate('c').'] HTTP '.$batch['name'].' inserted='.($response['data']['inserted'] ?? 0)."\n");
    }
}

/**
 * @param  array<string, string>  $config
 * @return list<array{name: string, type: string, requests: list<array<string, mixed>>}>
 */
function collectHttpRequests(array $config): array
{
    $statePath = __DIR__.DIRECTORY_SEPARATOR.'saha-access-log.offset.json';
    $state = readJsonFile($statePath);
    $batches = [];

    foreach (httpAccessLogSources($config) as $source) {
        $tailed = tailAccessLog($source['path'], $state);

        if ($tailed['requests'] === []) {
            $state = $tailed['state'];

            continue;
        }

        $batches[] = [
            'name' => $source['name'],
            'type' => $source['type'],
            'requests' => $tailed['requests'],
        ];
        $state = $tailed['state'];
    }

    writeJsonFile($statePath, $state);

    return $batches;
}

/**
 * @param  array<string, string>  $config
 * @return list<array{path: string, name: string, type: string}>
 */
function httpAccessLogSources(array $config): array
{
    $catalog = [
        [
            'key' => 'access_log_apache',
            'name' => 'Apache',
            'type' => 'apache',
            'defaults' => [
                '/var/log/apache2/other_vhosts_access.log',
                '/var/log/apache2/access.log',
                '/var/log/httpd/access_log',
                '/var/log/httpd/access.log',
            ],
        ],
        [
            'key' => 'access_log_nginx',
            'name' => 'Nginx',
            'type' => 'nginx',
            'defaults' => [
                '/var/log/nginx/access.log',
            ],
        ],
    ];
    $sources = [];

    foreach ($catalog as $entry) {
        $configured = trim((string) ($config[$entry['key']] ?? ''));
        $paths = $configured !== '' ? [$configured] : $entry['defaults'];

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                $sources[$path] = [
                    'path' => $path,
                    'name' => $entry['name'],
                    'type' => $entry['type'],
                ];
            }
        }

        if ($configured === '' && $entry['type'] === 'apache') {
            foreach (glob('/var/log/apache2/*access*') ?: [] as $path) {
                if (is_file($path) && is_readable($path)) {
                    $sources[$path] = [
                        'path' => $path,
                        'name' => $entry['name'],
                        'type' => $entry['type'],
                    ];
                }
            }
        }
    }

    return array_values($sources);
}

/**
 * @param  array<string, int>  $state
 * @return array{requests: list<array<string, mixed>>, state: array<string, int>}
 */
function tailAccessLog(string $path, array $state): array
{
    $size = @filesize($path);

    if (! is_int($size) || $size < 1) {
        return ['requests' => [], 'state' => $state];
    }

    if (! array_key_exists($path, $state)) {
        $state[$path] = max(0, $size - 1048576);
    }

    $offset = (int) $state[$path];

    if ($size < $offset) {
        $offset = 0;
    }

    if ($size <= $offset) {
        $state[$path] = $size;

        return ['requests' => [], 'state' => $state];
    }

    $handle = @fopen($path, 'rb');

    if ($handle === false) {
        return ['requests' => [], 'state' => $state];
    }

    fseek($handle, $offset);
    $chunk = stream_get_contents($handle);
    fclose($handle);

    if (! is_string($chunk) || $chunk === '') {
        return ['requests' => [], 'state' => $state];
    }

    $complete = $chunk;

    if (substr($chunk, -1) !== "\n") {
        $lastNewline = strrpos($chunk, "\n");

        if ($lastNewline === false) {
            return ['requests' => [], 'state' => $state];
        }

        $complete = substr($chunk, 0, $lastNewline + 1);
    }

    $lines = preg_split("/\r\n|\n|\r/", rtrim($complete, "\r\n")) ?: [];
    $requests = [];
    $consumed = 0;
    $max = 200;

    foreach ($lines as $line) {
        if ($line === '') {
            $consumed += 1;

            continue;
        }

        if (count($requests) >= $max) {
            break;
        }

        $parsed = parseAccessLogLine($line);

        if ($parsed !== null && isRecentHttpSample($parsed['occurred_at'])) {
            $requests[] = $parsed;
        }

        $consumed += strlen($line) + 1;
    }

    $state[$path] = $offset + $consumed;

    return ['requests' => $requests, 'state' => $state];
}

/**
 * @return array{occurred_at: string, method: string, resource: string, status_code: int, duration_us: int, client_ip: ?string}|null
 */
function parseAccessLogLine(string $line): ?array
{
    if (preg_match('/(?:^|\s)((?:\d{1,3}\.){3}\d{1,3}|(?:[0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}) - - \[(\d{2}\/[A-Za-z]{3}\/\d{4}:\d{2}:\d{2}:\d{2} [+\-]\d{4})\]\s+"(\S+)\s+(\S+)(?:\s+HTTP\/[0-9.]+)?"\s+(\d{3})\s+(\S+)(.*)$/', $line, $matches) !== 1) {
        return null;
    }

    $occurred = DateTime::createFromFormat('d/M/Y:H:i:s O', $matches[2]);
    $resource = $matches[4];
    $query = strpos($resource, '?');

    if ($query !== false) {
        $resource = substr($resource, 0, $query);
    }

    if (strlen($resource) > 512) {
        $resource = substr($resource, 0, 512);
    }

    $durationUs = 0;
    $rest = ltrim($matches[7]);

    if ($rest !== '' && isset($rest[0]) && $rest[0] !== '"' && preg_match('/^(\d+(?:\.\d+)?)/', $rest, $duration) === 1) {
        $durationUs = durationToMicroseconds($duration[1]);
    }

    $clientIp = $matches[1];

    if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
        $clientIp = null;
    }

    return [
        'occurred_at' => $occurred instanceof DateTime ? $occurred->format('c') : gmdate('c'),
        'method' => strtoupper($matches[3]),
        'resource' => $resource !== '' ? $resource : '/',
        'status_code' => (int) $matches[5],
        'duration_us' => $durationUs,
        'client_ip' => $clientIp,
    ];
}

function durationToMicroseconds(string $raw): int
{
    if (strpos($raw, '.') !== false) {
        return (int) round((float) $raw * 1000000);
    }

    return (int) $raw;
}

function isRecentHttpSample(string $occurredAt): bool
{
    $timestamp = strtotime($occurredAt);

    if ($timestamp === false) {
        return true;
    }

    return $timestamp >= (time() - 900);
}

/**
 * @param  list<array{name: string, type: string, requests?: list<array<string, mixed>>, sample?: array<string, mixed>}>  $batches
 * @param  array<string, mixed>|null  $runtime
 * @return list<array{name: string, type: string, requests?: list<array<string, mixed>>, sample?: array<string, mixed>}>
 */
function attachApacheRuntime(array $batches, ?array $runtime): array
{
    if ($runtime === null) {
        return $batches;
    }

    foreach ($batches as $index => $batch) {
        if (($batch['type'] ?? '') === 'apache') {
            $batches[$index]['sample'] = $runtime;

            return $batches;
        }
    }

    $batches[] = [
        'name' => 'Apache',
        'type' => 'apache',
        'sample' => $runtime,
    ];

    return $batches;
}

/**
 * @param  array<string, string>  $config
 * @return array<string, mixed>|null
 */
function collectApacheRuntime(array $config): ?array
{
    $status = fetchApacheStatus($config);

    if ($status === null) {
        return null;
    }

    $statePath = __DIR__.DIRECTORY_SEPARATOR.'saha-apache-status.json';
    $state = readJsonFile($statePath);
    $previous = array_key_exists('total_accesses', $state) ? (int) $state['total_accesses'] : null;
    $total = (int) $status['total_accesses'];
    $delta = 0;

    if ($previous !== null && $total >= $previous) {
        $delta = $total - $previous;
    }

    $state['total_accesses'] = $total;
    writeJsonFile($statePath, $state);

    $reqPerSec = $status['req_per_sec'];

    if ($reqPerSec === null && $delta > 0) {
        $reqPerSec = $delta / 2;
    }

    return [
        'request_count' => $delta,
        'error_count' => 0,
        'response_time_avg' => 0,
        'response_time_p95' => 0,
        'req_per_sec' => $reqPerSec,
        'busy_workers' => $status['busy_workers'],
        'idle_workers' => $status['idle_workers'],
        'bytes_per_sec' => $status['bytes_per_sec'],
    ];
}

/**
 * @param  array<string, string>  $config
 * @return array{total_accesses: int, busy_workers: int, idle_workers: int, req_per_sec: float|null, bytes_per_sec: float|null}|null
 */
function fetchApacheStatus(array $config): ?array
{
    $urls = [];
    $configured = trim((string) ($config['apache_status_url'] ?? ''));

    if ($configured !== '') {
        $urls[] = $configured;
    }

    $urls[] = 'http://127.0.0.1/server-status?auto';
    $urls[] = 'http://127.0.0.1:80/server-status?auto';
    $urls[] = 'http://localhost/server-status?auto';

    foreach ($urls as $url) {
        $body = localHttpGet($url);
        $parsed = parseApacheStatus($body);

        if ($parsed !== null) {
            return $parsed;
        }
    }

    return null;
}

/**
 * @return array{total_accesses: int, busy_workers: int, idle_workers: int, req_per_sec: float|null, bytes_per_sec: float|null}|null
 */
function parseApacheStatus(string $body): ?array
{
    if ($body === '') {
        return null;
    }

    $total = preg_match('/Total Accesses:\s+(\d+)/', $body, $accesses) === 1 ? (int) $accesses[1] : null;
    $busy = preg_match('/BusyWorkers:\s+(\d+)/', $body, $busyMatch) === 1 ? (int) $busyMatch[1] : null;
    $idle = preg_match('/IdleWorkers:\s+(\d+)/', $body, $idleMatch) === 1 ? (int) $idleMatch[1] : null;

    if ($total === null && $busy === null) {
        return null;
    }

    return [
        'total_accesses' => $total ?? 0,
        'busy_workers' => $busy ?? 0,
        'idle_workers' => $idle ?? 0,
        'req_per_sec' => preg_match('/ReqPerSec:\s+([0-9.]+)/', $body, $req) === 1 ? (float) $req[1] : null,
        'bytes_per_sec' => preg_match('/BytesPerSec:\s+([0-9.]+)/', $body, $bytes) === 1 ? (float) $bytes[1] : null,
    ];
}

function localHttpGet(string $url): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        if ($curl === false) {
            return '';
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return is_string($response) ? $response : '';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);

    return is_string($response) ? $response : '';
}

function warnMissingApacheSource(): void
{
    static $warned = false;

    if ($warned || ! apacheIsRunning()) {
        return;
    }

    $warned = true;
    fwrite(STDERR, 'Apache is running but no access log or server-status sample was collected. Grant read access to /var/log/apache2 (group adm) or enable mod_status for 127.0.0.1. Optional: apache_status_url in agent.yml.'."\n");
}

function apacheIsRunning(): bool
{
    foreach (collectRunningServices() as $service) {
        if (($service['type'] ?? '') === 'apache') {
            return true;
        }
    }

    return false;
}

/**
 * @return array<string, mixed>
 */
function readJsonFile(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param  array<string, mixed>  $data
 */
function writeJsonFile(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * @return list<array<string, mixed>>
 */
function collectHostLogs(): array
{
    $logs = [
        [
            'timestamp' => gmdate('c'),
            'level' => 'info',
            'source' => 'agent',
            'process' => 'saha-agent',
            'message' => 'Collector cycle completed on '.php_uname('n'),
        ],
    ];

    $diskPath = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';
    $total = @disk_total_space($diskPath);
    $free = @disk_free_space($diskPath);

    if (is_float($total) && $total > 0 && is_float($free)) {
        $usedPercent = (($total - $free) / $total) * 100;

        if ($usedPercent >= 90) {
            $logs[] = [
                'timestamp' => gmdate('c'),
                'level' => 'error',
                'source' => 'agent',
                'process' => 'saha-agent',
                'message' => 'Disk usage is '.round($usedPercent, 1).'% on '.$diskPath,
            ];
        } elseif ($usedPercent >= 80) {
            $logs[] = [
                'timestamp' => gmdate('c'),
                'level' => 'warning',
                'source' => 'agent',
                'process' => 'saha-agent',
                'message' => 'Disk usage is '.round($usedPercent, 1).'% on '.$diskPath,
            ];
        }
    }

    return $logs;
}

/**
 * @return list<array{type: string, name: string, value: float, unit: string}>
 */
function collectHostMetrics(): array
{
    $metrics = [];
    $diskPath = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';
    $total = @disk_total_space($diskPath);
    $free = @disk_free_space($diskPath);

    if (is_float($total) && $total > 0 && is_float($free)) {
        $used = $total - $free;
        $metrics[] = ['type' => 'disk', 'name' => 'usage', 'value' => round(($used / $total) * 100, 2), 'unit' => 'percent'];
        $metrics[] = ['type' => 'disk', 'name' => 'used_bytes', 'value' => round($used, 0), 'unit' => 'bytes'];
        $metrics[] = ['type' => 'disk', 'name' => 'total_bytes', 'value' => round($total, 0), 'unit' => 'bytes'];
    }

    $memory = collectMemory();

    if ($memory !== null) {
        $metrics[] = ['type' => 'memory', 'name' => 'usage', 'value' => $memory['usage'], 'unit' => 'percent'];
        $metrics[] = ['type' => 'memory', 'name' => 'used_bytes', 'value' => $memory['used'], 'unit' => 'bytes'];
        $metrics[] = ['type' => 'memory', 'name' => 'total_bytes', 'value' => $memory['total'], 'unit' => 'bytes'];
    }

    $cpu = collectCpu();

    if ($cpu !== null) {
        $metrics[] = ['type' => 'cpu', 'name' => 'usage', 'value' => $cpu, 'unit' => 'percent'];
    }

    $network = collectNetwork();

    if ($network !== null) {
        $metrics[] = ['type' => 'network', 'name' => 'rx_bytes', 'value' => $network['rx'], 'unit' => 'bytes'];
        $metrics[] = ['type' => 'network', 'name' => 'tx_bytes', 'value' => $network['tx'], 'unit' => 'bytes'];
    }

    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();

        if (is_array($load)) {
            $metrics[] = ['type' => 'load', 'name' => 'load1', 'value' => round((float) $load[0], 2), 'unit' => 'load'];
        }
    }

    $uptime = collectUptime();

    if ($uptime !== null) {
        $metrics[] = ['type' => 'uptime', 'name' => 'seconds', 'value' => $uptime, 'unit' => 'seconds'];
    }

    return $metrics;
}

function collectCpu(): ?float
{
    if (PHP_OS_FAMILY === 'Windows') {
        $output = @shell_exec('wmic cpu get loadpercentage /value');

        if (is_string($output) && preg_match('/LoadPercentage=(\d+)/', $output, $matches) === 1) {
            return (float) $matches[1];
        }

        return null;
    }

    $first = readProcStat();

    if ($first === null) {
        return null;
    }

    usleep(400000);

    $second = readProcStat();

    if ($second === null) {
        return null;
    }

    $idleDelta = $second['idle'] - $first['idle'];
    $totalDelta = $second['total'] - $first['total'];

    if ($totalDelta <= 0) {
        return 0.0;
    }

    return round((1 - ($idleDelta / $totalDelta)) * 100, 2);
}

/**
 * @return array{idle: float, total: float}|null
 */
function readProcStat(): ?array
{
    $stat = @file_get_contents('/proc/stat');

    if (! is_string($stat) || preg_match('/^cpu\s+(.+)$/m', $stat, $matches) !== 1) {
        return null;
    }

    $parts = preg_split('/\s+/', trim($matches[1])) ?: [];
    $user = (float) ($parts[0] ?? 0);
    $nice = (float) ($parts[1] ?? 0);
    $system = (float) ($parts[2] ?? 0);
    $idle = (float) ($parts[3] ?? 0);
    $iowait = (float) ($parts[4] ?? 0);
    $irq = (float) ($parts[5] ?? 0);
    $softirq = (float) ($parts[6] ?? 0);
    $steal = (float) ($parts[7] ?? 0);
    $idleAll = $idle + $iowait;
    $total = $user + $nice + $system + $idleAll + $irq + $softirq + $steal;

    return [
        'idle' => $idleAll,
        'total' => $total,
    ];
}

/**
 * @return array{rx: float, tx: float}|null
 */
function collectNetwork(): ?array
{
    if (PHP_OS_FAMILY === 'Windows') {
        return null;
    }

    $info = @file_get_contents('/proc/net/dev');

    if (! is_string($info)) {
        return null;
    }

    $rx = 0.0;
    $tx = 0.0;

    foreach (explode("\n", $info) as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }

        [$iface, $rest] = explode(':', $line, 2);

        if (trim($iface) === 'lo') {
            continue;
        }

        $cols = preg_split('/\s+/', trim($rest)) ?: [];

        $rx += (float) ($cols[0] ?? 0);
        $tx += (float) ($cols[8] ?? 0);
    }

    return [
        'rx' => $rx,
        'tx' => $tx,
    ];
}

/**
 * @return array{usage: float, used: float, total: float}|null
 */
function collectMemory(): ?array
{
    if (PHP_OS_FAMILY === 'Windows') {
        $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value');

        if (! is_string($output) || preg_match('/FreePhysicalMemory=(\d+)/', $output, $free) !== 1 || preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $total) !== 1) {
            return null;
        }

        $totalBytes = (float) $total[1] * 1024;
        $freeBytes = (float) $free[1] * 1024;
        $used = $totalBytes - $freeBytes;

        return [
            'total' => $totalBytes,
            'used' => $used,
            'usage' => $totalBytes > 0 ? round(($used / $totalBytes) * 100, 2) : 0.0,
        ];
    }

    $info = @file_get_contents('/proc/meminfo');

    if (! is_string($info) || preg_match('/MemTotal:\s+(\d+)/', $info, $total) !== 1 || preg_match('/MemAvailable:\s+(\d+)/', $info, $available) !== 1) {
        return null;
    }

    $totalBytes = (float) $total[1] * 1024;
    $used = $totalBytes - ((float) $available[1] * 1024);

    return [
        'total' => $totalBytes,
        'used' => $used,
        'usage' => $totalBytes > 0 ? round(($used / $totalBytes) * 100, 2) : 0.0,
    ];
}

/**
 * @return list<array{name: string, type: string}>
 */
function collectRunningServices(): array
{
    $catalog = [
        ['needles' => ['apache2', 'httpd'], 'name' => 'Apache', 'type' => 'apache'],
        ['needles' => ['nginx'], 'name' => 'Nginx', 'type' => 'nginx'],
        ['needles' => ['mysqld', 'mariadbd'], 'name' => 'MySQL', 'type' => 'mysql'],
        ['needles' => ['postgres', 'postmaster'], 'name' => 'PostgreSQL', 'type' => 'postgres'],
        ['needles' => ['redis-server'], 'name' => 'Redis', 'type' => 'redis'],
        ['needles' => ['php-fpm'], 'name' => 'PHP-FPM', 'type' => 'php_fpm'],
        ['needles' => ['mongod'], 'name' => 'MongoDB', 'type' => 'mongodb'],
        ['needles' => ['dockerd'], 'name' => 'Docker', 'type' => 'docker'],
        ['needles' => ['memcached'], 'name' => 'Memcached', 'type' => 'memcached'],
        ['needles' => ['haproxy'], 'name' => 'HAProxy', 'type' => 'haproxy'],
        ['needles' => ['caddy'], 'name' => 'Caddy', 'type' => 'other'],
        ['needles' => ['lighttpd'], 'name' => 'Lighttpd', 'type' => 'other'],
        ['needles' => ['varnishd'], 'name' => 'Varnish', 'type' => 'other'],
        ['needles' => ['rabbitmq-server', 'beam.smp'], 'name' => 'RabbitMQ', 'type' => 'other'],
        ['needles' => ['elasticsearch'], 'name' => 'Elasticsearch', 'type' => 'other'],
    ];

    $found = [];

    foreach (runningProcessNames() as $command) {
        $match = matchRunningService($command, $catalog);

        if ($match === null) {
            continue;
        }

        $found[$match['type'].'|'.$match['name']] = $match;
    }

    return array_values($found);
}

/**
 * @param  list<array{needles: list<string>, name: string, type: string}>  $catalog
 * @return array{name: string, type: string}|null
 */
function matchRunningService(string $command, array $catalog): ?array
{
    $normalized = strtolower(str_replace('\\', '/', $command));
    $base = basename($normalized);
    $base = preg_replace('/\.exe$/', '', $base) ?: $base;

    foreach ($catalog as $service) {
        foreach ($service['needles'] as $needle) {
            if ($base === $needle || strpos($base, $needle) === 0) {
                return [
                    'name' => $service['name'],
                    'type' => $service['type'],
                ];
            }
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function runningProcessNames(): array
{
    if (PHP_OS_FAMILY === 'Windows') {
        $output = @shell_exec('tasklist /FO CSV /NH');

        if (! is_string($output) || $output === '') {
            return [];
        }

        $names = [];

        foreach (preg_split('/\r\n|\n|\r/', trim($output)) ?: [] as $line) {
            if (preg_match('/^"([^"]+)"/', $line, $matches) === 1) {
                $names[] = $matches[1];
            }
        }

        return $names;
    }

    $names = [];

    foreach (glob('/proc/[0-9]*/comm') ?: [] as $file) {
        $comm = trim((string) @file_get_contents($file));

        if ($comm !== '') {
            $names[] = $comm;
        }
    }

    return $names;
}

function collectUptime(): ?float
{
    if (PHP_OS_FAMILY === 'Windows') {
        return null;
    }

    $uptime = @file_get_contents('/proc/uptime');

    if (! is_string($uptime)) {
        return null;
    }

    return (float) strtok($uptime, ' ');
}

/**
 * @param  array<string, mixed>  $payload
 * @param  list<string>  $headers
 * @return array<string, mixed>
 */
function request(string $method, string $url, array $payload = [], array $headers = []): array
{
    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json';
    $body = $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
    $verifySsl = (bool) ($GLOBALS['saha_verify_ssl'] ?? true);

    if (function_exists('curl_init')) {
        $result = requestWithCurl($method, $url, $body, $headers, $verifySsl);
    } else {
        $result = requestWithStream($method, $url, $body, $headers, $verifySsl);
    }

    if ($result['ok'] === false) {
        return [
            'success' => false,
            'message' => $result['error'].' ['.$method.' '.$url.']',
        ];
    }

    $decoded = json_decode($result['body'], true);

    return is_array($decoded) ? $decoded : ['success' => false, 'message' => $result['body']];
}

/**
 * @param  list<string>  $headers
 * @return array{ok: bool, body: string, error: string}
 */
function requestWithCurl(string $method, string $url, string $body, array $headers, bool $verifySsl): array
{
    $curl = curl_init($url);

    if ($curl === false) {
        return ['ok' => false, 'body' => '', 'error' => 'curl_init failed'];
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $method === 'GET' ? null : $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if (! is_string($response)) {
        return ['ok' => false, 'body' => '', 'error' => $error !== '' ? $error : 'HTTP request failed'];
    }

    return ['ok' => true, 'body' => $response, 'error' => ''];
}

/**
 * @param  list<string>  $headers
 * @return array{ok: bool, body: string, error: string}
 */
function requestWithStream(string $method, string $url, string $body, array $headers, bool $verifySsl): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'GET' ? '' : $body,
            'ignore_errors' => true,
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => $verifySsl,
            'verify_peer_name' => $verifySsl,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if (! is_string($response)) {
        $last = error_get_last();

        return [
            'ok' => false,
            'body' => '',
            'error' => $last['message'] ?? 'HTTP request failed (enable allow_url_fopen or php-curl)',
        ];
    }

    return ['ok' => true, 'body' => $response, 'error' => ''];
}

/**
 * @return array{name: string, version: string}
 */
function detectOperatingSystem(): array
{
    if (PHP_OS_FAMILY === 'Windows') {
        return [
            'name' => 'Windows',
            'version' => php_uname('r'),
        ];
    }

    $release = @file_get_contents('/etc/os-release');

    if (! is_string($release)) {
        return [
            'name' => 'Linux',
            'version' => php_uname('r'),
        ];
    }

    $values = [];

    foreach (explode("\n", $release) as $line) {
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\"'");
    }

    return [
        'name' => $values['NAME'] ?? 'Linux',
        'version' => $values['VERSION_ID'] ?? php_uname('r'),
    ];
}

function detectIp(): string
{
    $hostname = gethostname();

    if ($hostname === false) {
        return '127.0.0.1';
    }

    $ip = gethostbyname($hostname);

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/**
 * @return array<string, string>
 */
function parseSimpleYaml(string $path): array
{
    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
        $values[trim($key)] = trim($value, " \t\"'");
    }

    return $values;
}

/**
 * @param  array<string, string>  $config
 */
function writeSimpleYaml(string $path, array $config): void
{
    $order = ['api_url', 'enrollment_token', 'agent_id', 'api_key', 'heartbeat_interval', 'hostname', 'verify_ssl'];
    $lines = [
        '# Generated by saha-agent.php. Keep api_key private.',
        '',
    ];

    foreach ($order as $key) {
        $lines[] = $key.': '.($config[$key] ?? '');
    }

    foreach ($config as $key => $value) {
        if (! in_array($key, $order, true)) {
            $lines[] = $key.': '.$value;
        }
    }

    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
}
