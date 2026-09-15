@extends('layouts.app', ['title' => $application->name])

@php
    $busyWorkers = (int) ($runtime['busy_workers'] ?? 0);
    $idleWorkers = (int) ($runtime['idle_workers'] ?? 0);
    $workerTotal = $busyWorkers + $idleWorkers;
    $workerPct = $workerTotal > 0 ? (int) round(100 * $busyWorkers / $workerTotal) : 0;
    $errorsHint = number_format($summary['error_count']).' 5xx';
    if (($summary['client_error_count'] ?? 0) > 0) {
        $errorsHint .= ' · '.number_format($summary['client_error_count']).' 4xx';
    }
    $latencyValue = ! empty($summary['has_duration'])
        ? number_format($summary['response_time_avg']).' ms'
        : '—';
    $p95Value = ! empty($summary['has_duration'])
        ? number_format($summary['response_time_p95']).' ms'
        : '—';
    $latencyHint = empty($summary['has_duration'])
        ? 'No duration in access log'
        : ($hasHttpSamples ? 'p50 / p75 / p90 / p95 / p99 / Max' : 'Average / P95');
@endphp

@section('content')
    <div
        class="application-live"
        data-live-application="{{ route('applications.live', $application) }}"
        data-live-range="{{ $range }}"
    >
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <a href="{{ route('applications.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All applications</a>
            <h1 class="h4 mb-0 mt-1">{{ $application->name }}</h1>
            <div class="small text-secondary">
                {{ $application->type->label() }}
                · {{ $application->environment->label() }}
                @if ($application->host)
                    · {{ $application->host->displayName() }}
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge text-bg-success live-badge" data-live-indicator>Live</span>
            <form method="GET">
                <select class="form-select form-select-sm" name="range" onchange="this.form.submit()">
                    @foreach (App\Support\ApmCatalog::ranges() as $value => $label)
                        <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            @can('update', $application)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('applications.edit', $application) }}">Edit</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <x-stat-card data-live-kpi="request_count" label="Requests" :value="number_format($summary['request_count'])" hint="In this window" icon="bi-arrow-left-right" />
        </div>
        <div class="col-md-3">
            <x-stat-card data-live-kpi="error_rate" label="Error rate" :value="number_format($summary['error_rate'], 2).'%'" hint="5xx / requests" icon="bi-exclamation-triangle" />
        </div>
        <div class="col-md-3">
            <x-stat-card data-live-kpi="response_time_avg" label="Average" :value="$latencyValue" hint="When duration is logged" icon="bi-stopwatch" />
        </div>
        <div class="col-md-3">
            <x-stat-card data-live-kpi="response_time_p95" label="P95" :value="$p95Value" hint="When duration is logged" icon="bi-graph-up" />
        </div>
    </div>

    @if ($application->type->collectsHttpTraffic())
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <x-stat-card data-live-kpi="req_per_sec" label="Requests / sec" :value="isset($runtime['req_per_sec']) ? number_format((float) $runtime['req_per_sec'], 2) : '—'" hint="Live from Apache" icon="bi-activity" />
            </div>
            <div class="col-md-8">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                    <div class="flex-grow-1">
                        <div class="stat-card-label">Workers</div>
                        <div class="stat-card-value">
                            <span data-live-kpi-inline="busy_workers">{{ $busyWorkers }}</span>
                            <span class="stat-card-hint">busy</span>
                            <span class="text-secondary mx-1">/</span>
                            <span data-live-kpi-inline="idle_workers">{{ $idleWorkers }}</span>
                            <span class="stat-card-hint">idle</span>
                        </div>
                        <div class="worker-meter mt-2" title="Busy worker share">
                            <div class="worker-meter-bar" data-live-worker-bar style="width: {{ $workerPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-metric-chart title="Requests" :chart="$charts['requests']" :hint="number_format($summary['request_count']).' total'" chart-key="requests" size="lg" />
        </div>
        <div class="col-lg-4">
            <x-metric-chart title="Errors" :chart="$charts['errors']" :hint="$errorsHint" chart-key="errors" size="lg" />
        </div>
        <div class="col-lg-4">
            <x-metric-chart title="Latency" :chart="$charts['latency']" :hint="$latencyHint" chart-key="latency" size="lg" />
        </div>
    </div>

    <div class="panel p-0 mb-3">
        <div class="panel-header px-3">
            <h2 class="h6 mb-0">Recent requests</h2>
            <span class="small text-secondary" data-live-recent-count>{{ number_format(count($recent)) }} shown</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 request-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Resource</th>
                        <th>Duration</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody data-live-recent>
                    @forelse ($recent as $hit)
                        <tr>
                            <td class="text-nowrap request-time">{{ $hit['occurred_at_label'] }}</td>
                            <td class="text-secondary">{{ $hit['service'] }}</td>
                            <td><code class="request-resource">{{ $hit['resource'] }}</code></td>
                            <td class="text-nowrap text-secondary">{{ $hit['duration_label'] }}</td>
                            <td><span class="request-method">{{ $hit['method'] ?: '—' }}</span></td>
                            <td>
                                @if ($hit['status_code'] >= 500)
                                    <span class="badge text-bg-danger">{{ $hit['status_code'] }}</span>
                                @elseif ($hit['status_code'] >= 400)
                                    <span class="badge text-bg-warning">{{ $hit['status_code'] }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ $hit['status_code'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">Waiting for live HTTP samples from the host access log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h6 mb-0">Application details</h2>
            <x-status-badge data-live-status :value="$health['status_label']" :variant="$health['status_variant']" />
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Endpoint</dt>
            <dd class="col-sm-9">{{ $application->endpoint ?? '—' }}</dd>
            <dt class="col-sm-3">Host</dt>
            <dd class="col-sm-9">
                @if ($application->host)
                    <div class="d-flex align-items-center gap-2">
                        <x-os-logo :os="$application->host->operating_system" />
                        <a href="{{ route('hosts.show', $application->host) }}">{{ $application->host->displayName() }}</a>
                    </div>
                @else
                    —
                @endif
            </dd>
            <dt class="col-sm-3">Last sample</dt>
            <dd class="col-sm-9" data-live-last-seen>{{ $application->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9">{{ $application->description ?: '—' }}</dd>
        </dl>
    </div>
    </div>
@endsection
