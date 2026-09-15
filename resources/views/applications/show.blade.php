@extends('layouts.app', ['title' => $application->name])

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
                @if ($application->version)
                    · v{{ $application->version }}
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge text-bg-success" data-live-indicator>Live</span>
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
            <x-stat-card data-live-kpi="error_rate" label="Error rate" :value="number_format($summary['error_rate'], 1).'%'" hint="4xx and 5xx / requests" icon="bi-exclamation-triangle" />
        </div>
        <div class="col-md-3">
            <x-stat-card data-live-kpi="response_time_avg" label="Average response" :value="number_format($summary['response_time_avg']).' ms'" hint="In this window" icon="bi-stopwatch" />
        </div>
        <div class="col-md-3">
            <x-stat-card data-live-kpi="response_time_p95" label="P95" :value="number_format($summary['response_time_p95']).' ms'" hint="In this window" icon="bi-graph-up" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-metric-chart title="Requests" :chart="$charts['requests']" :hint="number_format($summary['request_count']).' total'" chart-key="requests" />
        </div>
        <div class="col-lg-4">
            <x-metric-chart title="Errors" :chart="$charts['errors']" :hint="number_format($summary['error_count']).' · '.number_format($summary['error_rate'], 2).'%'" chart-key="errors" />
        </div>
        <div class="col-lg-4">
            <x-metric-chart title="Latency" :chart="$charts['latency']" hint="p50 / p75 / p90 / p95 / p99 / Max" chart-key="latency" />
        </div>
    </div>

    <div class="panel p-0 mb-3">
        <div class="panel-header px-3">
            <h2 class="h6 mb-0">Recent requests</h2>
            <span class="small text-secondary">{{ number_format(count($recent)) }} in this window</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Resource</th>
                        <th>Duration</th>
                        <th>HTTP method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody data-live-recent>
                    @forelse ($recent as $hit)
                        <tr>
                            <td class="text-nowrap small">{{ $hit['occurred_at_label'] }}</td>
                            <td>{{ $hit['service'] }}</td>
                            <td class="small text-break">{{ $hit['resource'] }}</td>
                            <td class="text-nowrap">{{ $hit['duration_label'] }}</td>
                            <td>{{ $hit['method'] ?: '—' }}</td>
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
                            <td colspan="6" class="text-secondary">No HTTP requests in this window. The collector tails Apache and Nginx access logs on the host.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h6 mb-0">Application details</h2>
            <x-status-badge data-live-status :value="$application->status->label()" :variant="$application->status->badgeVariant()" />
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
        <p class="small text-secondary mb-0 mt-3">Request, error, and latency charts are built from access-log samples. Duration is shown when the log includes it.</p>
    </div>
    </div>
@endsection
