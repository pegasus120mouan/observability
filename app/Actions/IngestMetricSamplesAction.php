<?php

namespace App\Actions;

use App\Enums\HostStatus;
use App\Enums\MetricType;
use App\Models\Agent;
use App\Models\Host;
use App\Models\MetricSample;
use App\Support\MetricCatalog;
use Illuminate\Support\Carbon;

class IngestMetricSamplesAction
{
    /**
     * @param  list<array{type: string, name: string, value: mixed, unit?: string|null, metadata?: array<string, mixed>|null}>  $metrics
     * @return array{inserted: int, host: Host}
     */
    public function handle(Agent $agent, array $metrics, ?Carbon $collectedAt = null): array
    {
        $agent->loadMissing('host');
        $host = $agent->host;

        abort_if($host === null, 422, 'This agent is not linked to a host.');

        $collectedAt ??= now();
        $now = now();
        $rows = [];
        $usage = [];

        foreach ($metrics as $metric) {
            $type = MetricType::from($metric['type']);
            $name = $metric['name'];
            $value = round((float) $metric['value'], 4);

            $rows[] = [
                'organization_id' => $agent->organization_id,
                'host_id' => $host->id,
                'metric_type' => $type->value,
                'metric_name' => $name,
                'value' => $value,
                'unit' => $metric['unit'] ?? MetricCatalog::unit($type, $name),
                'collected_at' => $collectedAt,
                'metadata' => isset($metric['metadata']) ? json_encode($metric['metadata']) : null,
                'created_at' => $now,
            ];

            if ($name === 'usage' && in_array($type, [MetricType::Cpu, MetricType::Memory, MetricType::Disk], true)) {
                $usage[$type->value] = $value;
            }
        }

        if ($rows !== []) {
            MetricSample::query()->withoutGlobalScopes()->insert($rows);
        }

        $this->refreshHost($host, $usage, $now);

        return ['inserted' => count($rows), 'host' => $host->fresh()];
    }

    /**
     * @param  array<string, float>  $usage
     */
    private function refreshHost(Host $host, array $usage, Carbon $now): void
    {
        $attributes = ['last_seen_at' => $now];

        if ($host->status !== HostStatus::Maintenance) {
            $attributes['status'] = $this->statusFromUsage($usage);
        }

        $host->forceFill($attributes)->save();
    }

    /**
     * @param  array<string, float>  $usage
     */
    private function statusFromUsage(array $usage): HostStatus
    {
        $critical = (float) config('platform.metrics.critical_percent');
        $warning = (float) config('platform.metrics.warning_percent');
        $peak = $usage === [] ? 0.0 : max($usage);

        if ($peak >= $critical) {
            return HostStatus::Critical;
        }

        if ($peak >= $warning) {
            return HostStatus::Warning;
        }

        return HostStatus::Online;
    }
}
