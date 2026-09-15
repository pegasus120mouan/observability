<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\ApplicationRequest;
use App\Support\ApmCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IngestHttpRequestsAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $requests
     * @param  array<string, mixed>  $sample
     * @return array{inserted: int, application_id: int}
     */
    public function handle(Agent $agent, array $payload, array $requests, ?Carbon $collectedAt = null, array $sample = []): array
    {
        $agent->loadMissing('host');
        $host = $agent->host;

        abort_if($host === null, 422, 'This agent is not linked to a host.');

        $collectedAt ??= now();
        $now = now();
        $application = $this->resolveApplication($agent, $payload);
        $rows = [];
        $durationsMs = [];
        $statusCodes = [];
        $errorCount = 0;

        foreach ($requests as $request) {
            $statusCode = (int) $request['status_code'];
            $durationUs = max(0, (int) ($request['duration_us'] ?? 0));
            $occurredAt = isset($request['occurred_at'])
                ? Carbon::parse((string) $request['occurred_at'])
                : $collectedAt;

            $rows[] = [
                'organization_id' => $agent->organization_id,
                'application_id' => $application->id,
                'occurred_at' => $occurredAt,
                'method' => $this->method($request['method'] ?? null),
                'resource' => $this->resource((string) $request['resource']),
                'status_code' => $statusCode,
                'duration_us' => $durationUs,
                'created_at' => $now,
            ];

            $codeKey = (string) $statusCode;
            $statusCodes[$codeKey] = ($statusCodes[$codeKey] ?? 0) + 1;

            if ($statusCode >= 500) {
                $errorCount++;
            }

            if ($durationUs > 0) {
                $durationsMs[] = (int) round($durationUs / 1000);
            }
        }

        if ($rows !== []) {
            ApplicationRequest::query()->withoutGlobalScopes()->insert($rows);
        }

        $requestCount = count($rows);
        $avg = $durationsMs === [] ? 0 : (int) round(array_sum($durationsMs) / count($durationsMs));
        $p95 = ApmCatalog::percentile($durationsMs, 95);

        if ($requestCount === 0) {
            $requestCount = max(0, (int) ($sample['request_count'] ?? 0));
            $errorCount = max(0, (int) ($sample['error_count'] ?? 0));
            $avg = max(0, (int) ($sample['response_time_avg'] ?? 0));
            $p95 = max(0, (int) ($sample['response_time_p95'] ?? 0));
        }

        if ($requestCount > 0) {
            ApplicationMetric::query()->withoutGlobalScopes()->insert([
                [
                    'organization_id' => $agent->organization_id,
                    'application_id' => $application->id,
                    'request_count' => $requestCount,
                    'error_count' => $errorCount,
                    'response_time_avg' => $avg,
                    'response_time_p95' => $p95,
                    'status_codes' => json_encode($statusCodes),
                    'collected_at' => $collectedAt,
                    'created_at' => $now,
                ],
            ]);
        }

        $application->forceFill([
            'runtime_stats' => $this->runtimeStats($sample, $application->runtime_stats ?? []),
            'last_seen_at' => $now,
        ])->save();

        $this->refreshWindowStatus($application);

        return [
            'inserted' => $requestCount,
            'application_id' => $application->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveApplication(Agent $agent, array $payload): Application
    {
        $type = $payload['type'] instanceof ApplicationType
            ? $payload['type']
            : ApplicationType::from((string) $payload['type']);
        $name = (string) $payload['name'];
        $host = $agent->host;
        $environment = $host?->environment;

        $application = Application::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $agent->organization_id)
            ->where('host_id', $host?->id)
            ->where('type', $type)
            ->first();

        if ($application !== null) {
            return $application;
        }

        $slug = Str::slug($name.($host !== null ? '-'.$host->hostname : '')) ?: 'application';

        $application = Application::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $agent->organization_id,
                'slug' => $slug,
                'environment' => $environment?->value ?? 'production',
            ]);

        $application->fill([
            'name' => $name,
            'type' => $type,
            'host_id' => $host?->id,
            'discovered' => true,
        ]);

        if (! $application->exists) {
            $application->status = ApplicationStatus::Unknown;
            $application->description = $application->description ?: 'Discovered from HTTP samples.';
        }

        $application->save();

        return $application;
    }

    private function refreshWindowStatus(Application $application): void
    {
        $requests = ApplicationRequest::query()
            ->withoutGlobalScopes()
            ->where('application_id', $application->id)
            ->where('occurred_at', '>=', now()->subMinutes(15))
            ->get(['status_code', 'duration_us']);

        if ($requests->isEmpty()) {
            return;
        }

        $serverErrors = $requests->filter(fn (ApplicationRequest $request): bool => $request->status_code >= 500)->count();
        $durations = $requests
            ->filter(fn (ApplicationRequest $request): bool => $request->duration_us > 0)
            ->map(fn (ApplicationRequest $request): int => (int) round($request->duration_us / 1000))
            ->values()
            ->all();

        $application->forceFill([
            'status' => ApmCatalog::statusFromSample(
                $requests->count(),
                $serverErrors,
                ApmCatalog::percentile($durations, 95),
            ),
        ])->save();
    }

    private function method(mixed $method): ?string
    {
        $value = strtoupper(trim((string) $method));

        if ($value === '') {
            return null;
        }

        return Str::substr($value, 0, 16);
    }

    private function resource(string $resource): string
    {
        $value = trim($resource);

        if ($value === '') {
            return '/';
        }

        return Str::substr($value, 0, 512);
    }

    /**
     * @param  array<string, mixed>  $sample
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function runtimeStats(array $sample, array $current): array
    {
        $stats = $current;
        $updated = false;

        if (array_key_exists('req_per_sec', $sample) && $sample['req_per_sec'] !== null) {
            $stats['req_per_sec'] = round((float) $sample['req_per_sec'], 2);
            $updated = true;
        }

        if (array_key_exists('busy_workers', $sample) && $sample['busy_workers'] !== null) {
            $stats['busy_workers'] = max(0, (int) $sample['busy_workers']);
            $updated = true;
        }

        if (array_key_exists('idle_workers', $sample) && $sample['idle_workers'] !== null) {
            $stats['idle_workers'] = max(0, (int) $sample['idle_workers']);
            $updated = true;
        }

        if (array_key_exists('bytes_per_sec', $sample) && $sample['bytes_per_sec'] !== null) {
            $stats['bytes_per_sec'] = round((float) $sample['bytes_per_sec'], 2);
            $updated = true;
        }

        if ($updated) {
            $stats['collected_at'] = now()->toIso8601String();
        }

        return $stats;
    }
}
