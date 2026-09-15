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
     *     summary: array{
     *         request_count: int,
     *         error_count: int,
     *         client_error_count: int,
     *         failed_count: int,
     *         error_rate: float,
     *         client_error_rate: float,
     *         response_time_avg: int,
     *         response_time_p95: int,
     *         has_duration: bool
     *     },
     *     charts: array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>},
     *     recent: list<array<string, mixed>>,
     *     has_http_samples: bool,
     *     health: array{status: string, status_label: string, status_variant: string}
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
        $summary = $hasHttpSamples
            ? $this->summaryFromRequests($requests)
            : $this->summaryFromMetrics($application, $from);
        $health = $summary['request_count'] > 0
            ? ApmCatalog::statusFromSample(
                $summary['request_count'],
                $summary['error_count'],
                $summary['response_time_p95'],
            )
            : $application->status;

        return [
            'summary' => $summary,
            'charts' => $hasHttpSamples
                ? $this->chartsFromRequests($requests, $from, $range)
                : $this->chartsFromMetrics($application, $from, $range),
            'recent' => $this->recentFrom($application, $requests),
            'has_http_samples' => $hasHttpSamples,
            'health' => [
                'status' => $health->value,
                'status_label' => $health->label(),
                'status_variant' => $health->badgeVariant(),
            ],
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
     * @return array{
     *     request_count: int,
     *     error_count: int,
     *     client_error_count: int,
     *     failed_count: int,
     *     error_rate: float,
     *     client_error_rate: float,
     *     response_time_avg: int,
     *     response_time_p95: int,
     *     has_duration: bool
     * }
     */
    private function summaryFromRequests(Collection $requests): array
    {
        $requestCount = $requests->count();
        $serverErrors = $requests->filter(fn (ApplicationRequest $request): bool => $request->status_code >= 500)->count();
        $clientErrors = $requests->filter(
            fn (ApplicationRequest $request): bool => $request->status_code >= 400 && $request->status_code < 500
        )->count();
        $durations = $requests
            ->filter(fn (ApplicationRequest $request): bool => $request->duration_us > 0)
            ->map(fn (ApplicationRequest $request): int => (int) round($request->duration_us / 1000))
            ->values()
            ->all();
        $avg = $durations === [] ? 0 : (int) round(array_sum($durations) / count($durations));

        return [
            'request_count' => $requestCount,
            'error_count' => $serverErrors,
            'client_error_count' => $clientErrors,
            'failed_count' => $serverErrors + $clientErrors,
            'error_rate' => ApmCatalog::errorRate($requestCount, $serverErrors),
            'client_error_rate' => ApmCatalog::errorRate($requestCount, $clientErrors),
            'response_time_avg' => $avg,
            'response_time_p95' => ApmCatalog::percentile($durations, 95),
            'has_duration' => $durations !== [],
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
            'client_error_count' => 0,
            'failed_count' => $errorCount,
            'error_rate' => ApmCatalog::errorRate($requestCount, $errorCount),
            'client_error_rate' => 0.0,
            'response_time_avg' => $weightedAvg,
            'response_time_p95' => $latestP95,
            'has_duration' => $weightedAvg > 0 || $latestP95 > 0,
        ];
    }

    /**
     * @param  Collection<int, ApplicationRequest>  $requests
     * @return array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    private function chartsFromRequests(Collection $requests, Carbon $from, string $range): array
    {
        $bucketSeconds = ApmCatalog::bucketSecondsForRange($range);
        $labels = $this->timeline($from, now(), $bucketSeconds);
        $requestSeries = array_fill_keys($labels, 0);
        $errorBuckets = [];
        $latencyBuckets = [];
        $hasDuration = false;

        foreach ($labels as $label) {
            $latencyBuckets[$label] = [];
        }

        foreach ($requests as $request) {
            $label = $this->bucketLabel($request->occurred_at, $bucketSeconds);

            if (! array_key_exists($label, $requestSeries)) {
                continue;
            }

            $requestSeries[$label]++;

            if ($request->status_code >= 400) {
                $code = (string) $request->status_code;
                $errorBuckets[$code][$label] = ($errorBuckets[$code][$label] ?? 0) + 1;
            }

            if ($request->duration_us > 0) {
                $hasDuration = true;
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
                'histogram' => true,
                'labels' => $labels,
                'values' => array_values($requestSeries),
            ],
            'errors' => [
                'type' => 'bar',
                'stacked' => true,
                'legend' => $errorCodes !== [],
                'histogram' => true,
                'unit' => 'count',
                'labels' => $labels,
                'series' => $errorCodes === []
                    ? [[
                        'label' => '5xx / 4xx',
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
                'legend' => $hasDuration,
                'unit' => 'ms',
                'empty' => $hasDuration ? null : 'Duration is not in the access log. Add %D (Apache) or $request_time (Nginx).',
                'labels' => $labels,
                'series' => $hasDuration
                    ? [
                        $this->latencySeries('p50', 'teal', $labels, $latencyBuckets, 50),
                        $this->latencySeries('p75', 'green', $labels, $latencyBuckets, 75),
                        $this->latencySeries('p90', 'blue', $labels, $latencyBuckets, 90),
                        $this->latencySeries('p95', 'yellow', $labels, $latencyBuckets, 95),
                        $this->latencySeries('p99', 'orange', $labels, $latencyBuckets, 99),
                        $this->latencySeries('Max', 'red', $labels, $latencyBuckets, 100),
                    ]
                    : [[
                        'label' => 'Latency',
                        'color' => 'blue',
                        'values' => array_fill(0, count($labels), null),
                    ]],
            ],
        ];
    }

    /**
     * @return array{requests: array<string, mixed>, errors: array<string, mixed>, latency: array<string, mixed>}
     */
    private function chartsFromMetrics(Application $application, Carbon $from, string $range): array
    {
        $samples = ApplicationMetric::query()
            ->where('application_id', $application->id)
            ->where('collected_at', '>=', $from)
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        $bucketSeconds = ApmCatalog::bucketSecondsForRange($range);
        $labels = $this->timeline($from, now(), $bucketSeconds);
        $requestSeries = array_fill_keys($labels, 0);
        $errorBuckets = [];
        $avgNumerator = array_fill_keys($labels, 0);
        $avgDenominator = array_fill_keys($labels, 0);
        $p95Buckets = array_fill_keys($labels, []);

        foreach ($samples as $sample) {
            $label = $this->bucketLabel($sample->collected_at, $bucketSeconds);

            if (! array_key_exists($label, $requestSeries)) {
                continue;
            }

            $requestSeries[$label] += $sample->request_count;
            $avgNumerator[$label] += $sample->request_count * $sample->response_time_avg;
            $avgDenominator[$label] += $sample->request_count;

            if ($sample->response_time_p95 > 0) {
                $p95Buckets[$label][] = $sample->response_time_p95;
            }

            $statusCodes = $sample->status_codes ?? [];

            if ($statusCodes === [] && $sample->error_count > 0) {
                $errorBuckets['Errors'][$label] = ($errorBuckets['Errors'][$label] ?? 0) + $sample->error_count;

                continue;
            }

            foreach ($statusCodes as $code => $count) {
                if ((int) $code >= 400) {
                    $errorBuckets[(string) $code][$label] = ($errorBuckets[(string) $code][$label] ?? 0) + (int) $count;
                }
            }
        }

        $errorCodes = array_keys($errorBuckets);
        sort($errorCodes);

        $hasDuration = $samples->contains(
            fn (ApplicationMetric $sample): bool => $sample->response_time_avg > 0 || $sample->response_time_p95 > 0
        );

        return [
            'requests' => [
                'type' => 'bar',
                'color' => 'blue',
                'label' => 'Requests',
                'unit' => 'count',
                'histogram' => true,
                'labels' => $labels,
                'values' => array_values($requestSeries),
            ],
            'errors' => [
                'type' => 'bar',
                'stacked' => count($errorCodes) > 1,
                'legend' => count($errorCodes) > 1,
                'histogram' => true,
                'unit' => 'count',
                'labels' => $labels,
                'series' => $errorCodes === []
                    ? [[
                        'label' => '5xx / 4xx',
                        'color' => 'red',
                        'values' => array_fill(0, count($labels), 0),
                    ]]
                    : array_map(function (string $code) use ($errorBuckets, $labels): array {
                        return [
                            'label' => $code,
                            'color' => $this->statusColor($code === 'Errors' ? '500' : $code),
                            'values' => array_map(fn (string $label): int => $errorBuckets[$code][$label] ?? 0, $labels),
                        ];
                    }, $errorCodes),
            ],
            'latency' => [
                'type' => 'line',
                'fill' => false,
                'legend' => $hasDuration,
                'unit' => 'ms',
                'empty' => $hasDuration ? null : 'No latency samples in this window.',
                'labels' => $labels,
                'series' => [
                    [
                        'label' => 'Average',
                        'color' => 'blue',
                        'values' => array_map(
                            fn (string $label): ?int => $avgDenominator[$label] > 0
                                ? (int) round($avgNumerator[$label] / $avgDenominator[$label])
                                : null,
                            $labels
                        ),
                    ],
                    [
                        'label' => 'P95',
                        'color' => 'yellow',
                        'values' => array_map(
                            fn (string $label): ?int => $p95Buckets[$label] === []
                                ? null
                                : (int) round(max($p95Buckets[$label])),
                            $labels
                        ),
                    ],
                ],
            ],
        ];
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
                'occurred_at_label' => $request->occurred_at?->format('H:i:s'),
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
    private function timeline(Carbon $from, Carbon $to, int $bucketSeconds): array
    {
        $start = intdiv($from->getTimestamp(), $bucketSeconds) * $bucketSeconds;
        $cursor = Carbon::createFromTimestamp($start, $from->getTimezone());
        $labels = [];

        while ($cursor <= $to) {
            $labels[] = $cursor->toIso8601String();
            $cursor->addSeconds($bucketSeconds);
        }

        return $labels;
    }

    private function bucketLabel(?Carbon $time, int $bucketSeconds): string
    {
        if ($time === null) {
            return '';
        }

        $start = intdiv($time->getTimestamp(), $bucketSeconds) * $bucketSeconds;

        return Carbon::createFromTimestamp($start, $time->getTimezone())->toIso8601String();
    }

    /**
     * @param  list<string>  $labels
     * @param  array<string, list<float>>  $latencyBuckets
     * @return array{label: string, color: string, values: list<int|null>}
     */
    private function latencySeries(string $label, string $color, array $labels, array $latencyBuckets, float $percentile): array
    {
        return [
            'label' => $label,
            'color' => $color,
            'values' => array_map(function (string $bucket) use ($latencyBuckets, $percentile): ?int {
                $values = $latencyBuckets[$bucket] ?? [];

                if ($values === []) {
                    return null;
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
