<?php

namespace App\Services;

use App\Enums\MetricType;
use App\Models\Host;
use App\Models\MetricSample;
use App\Support\MetricCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MetricQuery
{
    /**
     * @return list<array{t: string, v: float}>
     */
    public function series(Host $host, MetricType $type, string $name, Carbon $from, Carbon $to): array
    {
        return MetricSample::query()
            ->where('host_id', $host->id)
            ->where('metric_type', $type)
            ->where('metric_name', $name)
            ->whereBetween('collected_at', [$from, $to])
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get(['collected_at', 'value'])
            ->map(fn (MetricSample $sample): array => [
                't' => $sample->collected_at?->toIso8601String() ?? '',
                'v' => (float) $sample->value,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $hostIds
     * @return Collection<int, Collection<int, MetricSample>>
     */
    public function latestUsageByHost(array $hostIds, Carbon $since): Collection
    {
        if ($hostIds === []) {
            return collect();
        }

        return MetricSample::query()
            ->whereIn('host_id', $hostIds)
            ->whereIn('metric_type', [MetricType::Cpu, MetricType::Memory, MetricType::Disk])
            ->where('metric_name', 'usage')
            ->where('collected_at', '>=', $since)
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('host_id')
            ->map(function (Collection $samples): Collection {
                return $samples->unique(fn (MetricSample $sample): string => $sample->metric_type->value);
            });
    }

    /**
     * @return array{labels: list<string>, values: list<float|null>, label: string, unit: string}
     */
    public function chart(Host $host, MetricType $type, string $name, Carbon $from, Carbon $to): array
    {
        $points = $this->series($host, $type, $name, $from, $to);

        return [
            'label' => MetricCatalog::label($type, $name),
            'unit' => MetricCatalog::unit($type, $name),
            'labels' => array_column($points, 't'),
            'values' => array_column($points, 'v'),
        ];
    }

    /**
     * Organization-wide average of a usage metric, bucketed in PHP for SQLite/MySQL parity.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function organizationAverage(MetricType $type, string $name, Carbon $from, int $bucketMinutes = 5): array
    {
        $samples = MetricSample::query()
            ->where('metric_type', $type)
            ->where('metric_name', $name)
            ->where('collected_at', '>=', $from)
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get(['collected_at', 'value']);

        $buckets = [];

        foreach ($samples as $sample) {
            if ($sample->collected_at === null) {
                continue;
            }

            $collectedAt = $sample->collected_at->copy()->seconds(0);
            $bucketMinute = intdiv((int) $collectedAt->minute, $bucketMinutes) * $bucketMinutes;
            $key = $collectedAt->setMinute($bucketMinute)->toIso8601String();

            $buckets[$key]['sum'] = ($buckets[$key]['sum'] ?? 0) + (float) $sample->value;
            $buckets[$key]['count'] = ($buckets[$key]['count'] ?? 0) + 1;
        }

        ksort($buckets);

        $labels = [];
        $values = [];

        foreach ($buckets as $label => $bucket) {
            $labels[] = $label;
            $values[] = round($bucket['sum'] / $bucket['count'], 2);
        }

        return [
            'label' => 'Avg '.MetricCatalog::label($type, $name),
            'unit' => MetricCatalog::unit($type, $name),
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function latestUsage(Host $host): array
    {
        $usage = [];

        foreach ([MetricType::Cpu, MetricType::Memory, MetricType::Disk] as $type) {
            $value = MetricSample::query()
                ->where('host_id', $host->id)
                ->where('metric_type', $type)
                ->where('metric_name', 'usage')
                ->orderByDesc('collected_at')
                ->orderByDesc('id')
                ->value('value');

            if ($value !== null) {
                $usage[$type->value] = (float) $value;
            }
        }

        return $usage;
    }

    /**
     * @return array{
     *     usage: array{cpu: float|null, memory: float|null, disk: float|null},
     *     status: string,
     *     status_label: string,
     *     status_variant: string,
     *     last_seen_at: string|null,
     *     charts: array<string, array{labels: list<string>, values: list<float|null>, label: string, unit: string}>
     * }
     */
    public function liveSnapshot(Host $host, Carbon $from, Carbon $to): array
    {
        $host->refresh();
        $usage = $this->latestUsage($host);

        return [
            'usage' => [
                'cpu' => $usage['cpu'] ?? null,
                'memory' => $usage['memory'] ?? null,
                'disk' => $usage['disk'] ?? null,
            ],
            'status' => $host->status->value,
            'status_label' => $host->status->label(),
            'status_variant' => $host->status->badgeVariant(),
            'last_seen_at' => $host->last_seen_at?->toIso8601String(),
            'charts' => [
                'cpu' => $this->chart($host, MetricType::Cpu, 'usage', $from, $to),
                'memory' => $this->chart($host, MetricType::Memory, 'usage', $from, $to),
                'disk' => $this->chart($host, MetricType::Disk, 'usage', $from, $to),
                'network' => $this->chart($host, MetricType::Network, 'rx_bytes', $from, $to),
            ],
        ];
    }
}
