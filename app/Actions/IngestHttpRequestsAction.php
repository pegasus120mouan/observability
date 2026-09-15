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
     * @return array{inserted: int, application_id: int}
     */
    public function handle(Agent $agent, array $payload, array $requests, ?Carbon $collectedAt = null): array
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

            if ($statusCode >= 400) {
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

        $application->forceFill([
            'status' => ApmCatalog::statusFromSample($requestCount, $errorCount, $p95),
            'last_seen_at' => $now,
        ])->save();

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
}
