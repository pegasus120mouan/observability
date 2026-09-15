<?php

declare(strict_types=1);

/**
 * Lightweight SAHA collector (Phase 4).
 *
 * Registers with an enrollment token, stores the API key locally, then sends heartbeats, metrics, and logs.
 *
 *   php agent/saha-agent.php
 *   php agent/saha-agent.php --once
 */
$configPath = __DIR__.DIRECTORY_SEPARATOR.'agent.yml';

if (! is_file($configPath)) {
    fwrite(STDERR, "Missing agent/agent.yml\n");
    exit(1);
}

$config = parseSimpleYaml($configPath);
$apiUrl = rtrim((string) ($config['api_url'] ?? ''), '/');
$once = in_array('--once', $argv, true);

if ($apiUrl === '') {
    fwrite(STDERR, "api_url is required in agent.yml\n");
    exit(1);
}

if (($config['agent_id'] ?? '') === '' || ($config['api_key'] ?? '') === '') {
    $config = registerAgent($apiUrl, $config, $configPath);
}

$interval = max(5, (int) ($config['heartbeat_interval'] ?? 30));

do {
    heartbeat($apiUrl, $config);
    reportMetrics($apiUrl, $config);
    reportLogs($apiUrl, $config);
    if ($once) {
        break;
    }
    sleep($interval);
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
    $config['heartbeat_interval'] = (string) (($data['config']['heartbeat_interval'] ?? $config['heartbeat_interval']) ?: '30');
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

    $stat = @file_get_contents('/proc/stat');

    if (! is_string($stat) || preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $matches) !== 1) {
        return null;
    }

    $idle = (float) $matches[4];
    $total = (float) $matches[1] + (float) $matches[2] + (float) $matches[3] + $idle;

    return $total > 0 ? round((1 - ($idle / $total)) * 100, 2) : null;
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

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        return ['success' => false, 'message' => 'HTTP request failed'];
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : ['success' => false, 'message' => $body];
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
        if (! str_contains($line, '=')) {
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

        if ($line === '' || str_starts_with($line, '#')) {
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
    $order = ['api_url', 'enrollment_token', 'agent_id', 'api_key', 'heartbeat_interval', 'hostname'];
    $lines = [
        '# Generated by saha-agent.php. Keep api_key private.',
        '',
    ];

    foreach ($order as $key) {
        $lines[] = $key.': '.($config[$key] ?? '');
    }

    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
}
