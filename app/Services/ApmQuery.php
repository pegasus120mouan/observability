<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\ApplicationRequest;
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
     * @return array{
     *     summary: array{request_count: int, error_count: int, error_rate: float, response_time_avg: int, response_time_p95: int},
     *     charts: array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>},
     *     recent: list<array<string, mixed>>,
     *     has_http_samples: bool
     * }
     */
    public function snapshot(Application $application, Carbon $from, string $range): array
    {
        $requests = ApplicationRequest::query()
            ->where('application_id', $application->id)
            ->where('occurred_at', '>=', $from)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['occurred_at', 'method', 'resource', 'status_code', 'duration_us']);

        $hasHttpSamples = $requests->isNotEmpty();

        return [
            'summary' => $hasHttpSamples
                ? $this->summaryFromRequests($requests)
                : $this->summaryFromMetrics($application, $from),
            'charts' => $hasHttpSamples
                ? $this->chartsFromRequests($requests, $from, $range)
                : $this->chartsFromMetrics($application, $from),
            'recent' => $this->recentFrom($application, $requests),
            'has_http_samples' => $hasHttpSamples,
        ];
    }

    /**
     * @return array{request_count: int, error_count: int, error_rate: float, response_time_avg: int, response_time_p95: int}
     */
    public function summary(Application $application, Carbon $from): array
    {
        return $this->snapshot($application, $from, ApmCatalog::defaultRange())['summary'];
    }

    /**
     * @return array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    public function charts(Application $application, Carbon $from, string $range = '15m'): array
    {
        return $this->snapshot($application, $from, $range)['charts'];
    }

    /**
     * @param  Collection<int, ApplicationRequest>  $requests
     * @return array{request_count: int, error_count: int, error_rate: float, response_time_avg: int, response_time_p95: int}
     */
    private function summaryFromRequests(Collection $requests): array
    {
        $requestCount = $requests->count();
        $errorCount = $requests->filter(fn (ApplicationRequest $request): bool => $request->status_code >= 400)->count();
        $durations = $requests
            ->filter(fn (ApplicationRequest $request): bool => $request->duration_us > 0)
            ->map(fn (ApplicationRequest $request): int => (int) round($request->duration_us / 1000))
            ->values()
            ->all();
        $avg = $durations === [] ? 0 : (int) round(array_sum($durations) / count($durations));

        return [
            'request_count' => $requestCount,
            'error_count' => $errorCount,
            'error_rate' => ApmCatalog::errorRate($requestCount, $errorCount),
            'response_time_avg' => $avg,
            'response_time_p95' => ApmCatalog::percentile($durations, 95),
        ];
    }

    /**
     * @return array{request_count: int, error_count: int, error_rate: float, response_time_avg: int, response_time_p95: int}
     */
    private function summaryFromMetrics(Application $application, Carbon $from): array
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
     * @param  Collection<int, ApplicationRequest>  $requests
     * @return array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    private function chartsFromRequests(Collection $requests, Carbon $from, string $range): array
    {
        $labels = $this->timeline($from, now(), ApmCatalog::bucketMinutesForRange($range));
        $requestSeries = array_fill_keys($labels, 0);
        $errorBuckets = [];
        $latencyBuckets = [];

        foreach ($labels as $label) {
            $latencyBuckets[$label] = [];
        }

        foreach ($requests as $request) {
            $label = $this->bucketLabel($request->occurred_at, ApmCatalog::bucketMinutesForRange($range));

            if (! array_key_exists($label, $requestSeries)) {
                continue;
            }

            $requestSeries[$label]++;

            if ($request->status_code >= 400) {
                $code = (string) $request->status_code;
                $errorBuckets[$code][$label] = ($errorBuckets[$code][$label] ?? 0) + 1;
            }

            if ($request->duration_us > 0) {
                $latencyBuckets[$label][] = $request->duration_us / 1000;
            }
        }

        $errorCodes = array_keys($errorBuckets);
        sort($errorCodes);

        return [
            'requests' => [
                'type' => 'bar',
                'color' => 'blue',
                'label' => 'Requests',
                'unit' => 'count',
                'labels' => $labels,
                'values' => array_values($requestSeries),
            ],
            'errors' => [
                'type' => 'bar',
                'stacked' => true,
                'legend' => true,
                'unit' => 'count',
                'labels' => $labels,
                'series' => $errorCodes === []
                    ? [[
                        'label' => 'Errors',
                        'color' => 'red',
                        'values' => array_fill(0, count($labels), 0),
                    ]]
                    : array_map(function (string $code) use ($errorBuckets, $labels): array {
                        return [
                            'label' => $code,
                            'color' => $this->statusColor($code),
                            'values' => array_map(fn (string $label): int => $errorBuckets[$code][$label] ?? 0, $labels),
                        ];
                    }, $errorCodes),
            ],
            'latency' => [
                'type' => 'line',
                'fill' => false,
                'legend' => true,
                'unit' => 'ms',
                'labels' => $labels,
                'series' => [
                    $this->latencySeries('p50', 'teal', $labels, $latencyBuckets, 50),
                    $this->latencySeries('p75', 'green', $labels, $latencyBuckets, 75),
                    $this->latencySeries('p90', 'blue', $labels, $latencyBuckets, 90),
                    $this->latencySeries('p95', 'yellow', $labels, $latencyBuckets, 95),
                    $this->latencySeries('p99', 'orange', $labels, $latencyBuckets, 99),
                    $this->latencySeries('Max', 'red', $labels, $latencyBuckets, 100),
                ],
            ],
        ];
    }

    /**
     * @return array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    private function chartsFromMetrics(Application $application, Carbon $from): array
    {
        $samples = ApplicationMetric::query()
            ->where('application_id', $application->id)
            ->where('collected_at', '>=', $from)
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        $labels = $samples->map(fn (ApplicationMetric $sample): string => $sample->collected_at?->toIso8601String() ?? '')->all();
        $errorSeries = $this->metricErrorSeries($samples);

        return [
            'requests' => [
                'type' => 'bar',
                'color' => 'blue',
                'label' => 'Requests',
                'unit' => 'count',
                'labels' => $labels,
                'values' => $samples->map(fn (ApplicationMetric $sample): int => $sample->request_count)->all(),
            ],
            'errors' => [
                'type' => 'bar',
                'stacked' => count($errorSeries) > 1,
                'legend' => count($errorSeries) > 1,
                'unit' => 'count',
                'labels' => $labels,
                'series' => $errorSeries,
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

    /**
     * @param  Collection<int, ApplicationMetric>  $samples
     * @return list<array{label: string, color: string, values: list<int>}>
     */
    private function metricErrorSeries(Collection $samples): array
    {
        $codes = [];

        foreach ($samples as $sample) {
            foreach ($sample->status_codes ?? [] as $code => $count) {
                if ((int) $code >= 400) {
                    $codes[(string) $code] = true;
                }
            }
        }

        $errorCodes = array_keys($codes);
        sort($errorCodes);

        if ($errorCodes === []) {
            return [[
                'label' => 'Errors',
                'color' => 'red',
                'values' => $samples->map(fn (ApplicationMetric $sample): int => $sample->error_count)->all(),
            ]];
        }

        return array_map(function (string $code) use ($samples): array {
            return [
                'label' => $code,
                'color' => $this->statusColor($code),
                'values' => $samples->map(function (ApplicationMetric $sample) use ($code): int {
                    $statusCodes = $sample->status_codes ?? [];

                    return (int) ($statusCodes[$code] ?? 0);
                })->all(),
            ];
        }, $errorCodes);
    }

    /**
     * @param  Collection<int, ApplicationRequest>  $requests
     * @return list<array<string, mixed>>
     */
    private function recentFrom(Application $application, Collection $requests): array
    {
        return $requests
            ->sortByDesc(fn (ApplicationRequest $request): string => $request->occurred_at?->toIso8601String() ?? '')
            ->take(ApmCatalog::recentRequestLimit())
            ->values()
            ->map(fn (ApplicationRequest $request): array => [
                'occurred_at' => $request->occurred_at?->toIso8601String(),
                'occurred_at_label' => $request->occurred_at?->format('M j H:i:s'),
                'service' => $application->name,
                'resource' => $request->resource,
                'duration_us' => $request->duration_us,
                'duration_label' => ApmCatalog::formatDurationUs((int) $request->duration_us),
                'method' => $request->method,
                'status_code' => $request->status_code,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function timeline(Carbon $from, Carbon $to, int $bucketMinutes): array
    {
        $cursor = $from->copy()->seconds(0)->microsecond(0);
        $cursor->minute((int) (intdiv($cursor->minute, $bucketMinutes) * $bucketMinutes));
        $labels = [];

        while ($cursor <= $to) {
            $labels[] = $cursor->toIso8601String();
            $cursor->addMinutes($bucketMinutes);
        }

        return $labels;
    }

    private function bucketLabel(?Carbon $time, int $bucketMinutes): string
    {
        if ($time === null) {
            return '';
        }

        $cursor = $time->copy()->seconds(0)->microsecond(0);
        $cursor->minute((int) (intdiv($cursor->minute, $bucketMinutes) * $bucketMinutes));

        return $cursor->toIso8601String();
    }

    /**
     * @param  list<string>  $labels
     * @param  array<string, list<float>>  $latencyBuckets
     * @return array{label: string, color: string, values: list<int>}
     */
    private function latencySeries(string $label, string $color, array $labels, array $latencyBuckets, float $percentile): array
    {
        return [
            'label' => $label,
            'color' => $color,
            'values' => array_map(function (string $bucket) use ($latencyBuckets, $percentile): int {
                $values = $latencyBuckets[$bucket] ?? [];

                if ($values === []) {
                    return 0;
                }

                if ($percentile >= 100) {
                    return (int) round(max($values));
                }

                return ApmCatalog::percentile(array_map('intval', $values), $percentile);
            }, $labels),
        ];
    }

    private function statusColor(string $code): string
    {
        $family = (int) floor(((int) $code) / 100);

        return match ($family) {
            4 => 'orange',
            5 => 'red',
            default => 'yellow',
        };
    }
}
