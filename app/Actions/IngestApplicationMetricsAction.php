<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\HostEnvironment;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Support\ApmCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IngestApplicationMetricsAction
{
    /**
     * @param  list<array<string, mixed>>  $applications
     * @return array{inserted: int, applications: int}
     */
    public function handle(Agent $agent, array $applications, ?Carbon $collectedAt = null): array
    {
        $collectedAt ??= now();
        $now = now();
        $hostId = $agent->host_id;
        $rows = [];
        $touched = 0;

        foreach ($applications as $payload) {
            $application = $this->upsert($agent, $payload, $hostId);
            $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
            $requestCount = (int) ($metrics['request_count'] ?? 0);
            $errorCount = (int) ($metrics['error_count'] ?? 0);
            $avg = (int) ($metrics['response_time_avg'] ?? 0);
            $p95 = (int) ($metrics['response_time_p95'] ?? 0);
            $statusCodes = $this->statusCodes($metrics['status_codes'] ?? []);

            $rows[] = [
                'organization_id' => $agent->organization_id,
                'application_id' => $application->id,
                'request_count' => $requestCount,
                'error_count' => $errorCount,
                'response_time_avg' => $avg,
                'response_time_p95' => $p95,
                'status_codes' => json_encode($statusCodes),
                'collected_at' => $collectedAt,
                'created_at' => $now,
            ];

            $application->forceFill([
                'status' => ApmCatalog::statusFromSample($requestCount, $errorCount, $p95),
                'last_seen_at' => $now,
            ])->save();

            $touched++;
        }

        if ($rows !== []) {
            ApplicationMetric::query()->withoutGlobalScopes()->insert($rows);
        }

        return [
            'inserted' => count($rows),
            'applications' => $touched,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(Agent $agent, array $payload, ?int $hostId): Application
    {
        $environment = HostEnvironment::from((string) ($payload['environment'] ?? HostEnvironment::Production->value));
        $name = (string) $payload['name'];
        $slug = Str::slug($name) ?: 'application';
        $resolvedHostId = isset($payload['host_id']) ? (int) $payload['host_id'] : $hostId;

        $application = Application::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $agent->organization_id,
                'slug' => $slug,
                'environment' => $environment->value,
            ]);

        $application->fill([
            'name' => $name,
            'type' => $payload['type'] instanceof ApplicationType
                ? $payload['type']
                : ApplicationType::from((string) $payload['type']),
            'version' => $payload['version'] ?? $application->version,
            'endpoint' => $payload['endpoint'] ?? $application->endpoint,
            'host_id' => $resolvedHostId,
        ]);

        if (! $application->exists) {
            $application->status = ApplicationStatus::Unknown;
        }

        $application->save();

        return $application;
    }

    /**
     * @return array<string, int>
     */
    private function statusCodes(mixed $codes): array
    {
        if (! is_array($codes)) {
            return [];
        }

        $normalized = [];

        foreach ($codes as $code => $count) {
            $key = (string) $code;

            if (! ApmCatalog::isAllowedStatusCode($key) || ! is_numeric($count)) {
                continue;
            }

            $normalized[$key] = max(0, (int) $count);
        }

        return $normalized;
    }
}
