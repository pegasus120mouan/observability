<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Support\ApmCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApmQuery
{
    /**
     * @param  list<int>  $applicationIds
     * @return Collection<int, ApplicationMetric>
     */
    public function latestByApplication(array $applicationIds): Collection
    {
        if ($applicationIds === []) {
            return collect();
        }

        return ApplicationMetric::query()
            ->whereIn('application_id', $applicationIds)
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get()
            ->unique('application_id')
            ->keyBy('application_id');
    }

    /**
     * @return array{request_count: int, error_count: int, error_rate: float, response_time_avg: int, response_time_p95: int}
     */
    public function summary(Application $application, Carbon $from): array
    {
        $samples = ApplicationMetric::query()
            ->where('application_id', $application->id)
            ->where('collected_at', '>=', $from)
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get(['request_count', 'error_count', 'response_time_avg', 'response_time_p95']);

        $requestCount = (int) $samples->sum('request_count');
        $errorCount = (int) $samples->sum('error_count');
        $weightedAvg = 0;
        $latestP95 = (int) ($samples->last()?->response_time_p95 ?? 0);

        if ($requestCount > 0) {
            $weightedAvg = (int) round(
                $samples->sum(fn (ApplicationMetric $sample): int => $sample->request_count * $sample->response_time_avg) / $requestCount
            );
        }

        return [
            'request_count' => $requestCount,
            'error_count' => $errorCount,
            'error_rate' => ApmCatalog::errorRate($requestCount, $errorCount),
            'response_time_avg' => $weightedAvg,
            'response_time_p95' => $latestP95,
        ];
    }

    /**
     * @return array{success: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    public function charts(Application $application, Carbon $from): array
    {
        $samples = ApplicationMetric::query()
            ->where('application_id', $application->id)
            ->where('collected_at', '>=', $from)
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        $labels = $samples->map(fn (ApplicationMetric $sample): string => $sample->collected_at?->toIso8601String() ?? '')->all();

        return [
            'success' => [
                'type' => 'bar',
                'color' => 'green',
                'label' => 'Successful requests',
                'unit' => 'count',
                'labels' => $labels,
                'values' => $samples->map(
                    fn (ApplicationMetric $sample): int => max(0, $sample->request_count - $sample->error_count)
                )->all(),
            ],
            'errors' => [
                'type' => 'bar',
                'color' => 'orange',
                'label' => 'Failed requests',
                'unit' => 'count',
                'labels' => $labels,
                'values' => $samples->map(fn (ApplicationMetric $sample): int => $sample->error_count)->all(),
            ],
            'latency' => [
                'type' => 'line',
                'fill' => false,
                'legend' => true,
                'unit' => 'ms',
                'labels' => $labels,
                'series' => [
                    [
                        'label' => 'Average',
                        'color' => 'blue',
                        'values' => $samples->map(fn (ApplicationMetric $sample): int => $sample->response_time_avg)->all(),
                    ],
                    [
                        'label' => 'P95',
                        'color' => 'yellow',
                        'values' => $samples->map(fn (ApplicationMetric $sample): int => $sample->response_time_p95)->all(),
                    ],
                ],
            ],
        ];
    }
}
