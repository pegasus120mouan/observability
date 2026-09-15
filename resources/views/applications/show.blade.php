@extends('layouts.app', ['title' => $application->name])

@section('content')
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
            <form method="GET">
                <select class="form-select form-select-sm" name="range" onchange="this.form.submit()">
                    <option value="1h" @selected($range === '1h')>Last 1 hour</option>
                    <option value="6h" @selected($range === '6h')>Last 6 hours</option>
                    <option value="24h" @selected($range === '24h')>Last 24 hours</option>
                </select>
            </form>
            @can('update', $application)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('applications.edit', $application) }}">Edit</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <x-stat-card label="Requests" :value="number_format($summary['request_count'])" hint="In this window" icon="bi-arrow-left-right" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Error rate" :value="number_format($summary['error_rate'], 1).'%'" hint="Errors / requests" icon="bi-exclamation-triangle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Average response" :value="number_format($summary['response_time_avg']).' ms'" hint="Weighted by requests" icon="bi-stopwatch" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="P95" :value="number_format($summary['response_time_p95']).' ms'" hint="Latest sample" icon="bi-graph-up" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-metric-chart title="Successful requests" :chart="$charts['success']" :hint="$range" />
        </div>
        <div class="col-lg-6">
            <x-metric-chart title="Failed requests" :chart="$charts['errors']" :hint="$range" />
        </div>
        <div class="col-12">
            <x-metric-chart title="Latency" :chart="$charts['latency']" :hint="$range" />
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h6 mb-0">Application details</h2>
            <x-status-badge :value="$application->status->label()" :variant="$application->status->badgeVariant()" />
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
            <dd class="col-sm-9">{{ $application->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9">{{ $application->description ?: '—' }}</dd>
        </dl>
        <p class="small text-secondary mb-0 mt-3">Distributed traces are not stored yet. This view uses request, error, and latency samples aligned with OpenTelemetry golden signals.</p>
    </div>
@endsection
