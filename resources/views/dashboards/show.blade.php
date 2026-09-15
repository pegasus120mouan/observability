@extends('layouts.app', ['title' => $dashboard->name])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('dashboards.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All dashboards</a>
            <h1 class="h4 mb-0 mt-1">{{ $dashboard->name }}</h1>
            @if ($dashboard->description)
                <p class="text-secondary mb-0">{{ $dashboard->description }}</p>
            @endif
        </div>
        @can('update', $dashboard)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboards.edit', $dashboard) }}">Edit</a>
        @endcan
    </div>

    @if (count($widgets) === 0)
        <div class="panel">
            <p class="text-secondary mb-0">This dashboard has no widgets yet.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach ($widgets as $item)
                @php
                    $widget = $item['widget'];
                    $payload = $item['payload'];
                @endphp
                <div class="col-md-{{ $item['width'] }}">
                    @switch ($widget->type)
                        @case (App\Enums\DashboardWidgetType::Stat)
                            <x-stat-card :label="$payload['label']" :value="$payload['value']" :hint="$payload['hint']" :icon="$payload['icon']" />
                            @break
                        @case (App\Enums\DashboardWidgetType::Timeseries)
                            <x-metric-chart :title="$widget->title" :chart="$payload['chart']" :hint="$payload['hint']" />
                            @break
                        @case (App\Enums\DashboardWidgetType::Hosts)
                            <div class="panel">
                                <div class="panel-header">
                                    <h2 class="h6 mb-0">{{ $widget->title }}</h2>
                                </div>
                                @forelse ($payload['hosts'] as $host)
                                    <div class="activity-item d-flex align-items-center gap-2">
                                        <x-os-logo :os="$host->operating_system" />
                                        <div>
                                            <a href="{{ route('hosts.show', $host) }}" class="fw-semibold text-decoration-none">{{ $host->displayName() }}</a>
                                            <div class="small text-secondary">{{ $host->hostname }} · {{ $host->status->label() }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-secondary mb-0">No hosts.</p>
                                @endforelse
                            </div>
                            @break
                        @case (App\Enums\DashboardWidgetType::Alerts)
                            <div class="panel">
                                <div class="panel-header">
                                    <h2 class="h6 mb-0">{{ $widget->title }}</h2>
                                    <a href="{{ route('alerts.index') }}" class="small">Alerts</a>
                                </div>
                                @forelse ($payload['alerts'] as $alert)
                                    <div class="activity-item">
                                        <a href="{{ route('alerts.show', $alert) }}" class="fw-semibold text-decoration-none">{{ $alert->title }}</a>
                                        <div class="small text-secondary">{{ $alert->host?->hostname }} · {{ $alert->severity->label() }}</div>
                                    </div>
                                @empty
                                    <p class="text-secondary mb-0">No active alerts.</p>
                                @endforelse
                            </div>
                            @break
                        @case (App\Enums\DashboardWidgetType::Incidents)
                            <div class="panel">
                                <div class="panel-header">
                                    <h2 class="h6 mb-0">{{ $widget->title }}</h2>
                                    <a href="{{ route('incidents.index') }}" class="small">Incidents</a>
                                </div>
                                @forelse ($payload['incidents'] as $incident)
                                    <div class="activity-item">
                                        <a href="{{ route('incidents.show', $incident) }}" class="fw-semibold text-decoration-none">{{ $incident->reference() }}</a>
                                        <div>{{ $incident->title }}</div>
                                        <div class="small text-secondary">{{ $incident->status->label() }}</div>
                                    </div>
                                @empty
                                    <p class="text-secondary mb-0">No incidents.</p>
                                @endforelse
                            </div>
                            @break
                        @case (App\Enums\DashboardWidgetType::Logs)
                            <div class="panel">
                                <div class="panel-header">
                                    <h2 class="h6 mb-0">{{ $widget->title }}</h2>
                                    <a href="{{ route('logs.index') }}" class="small">Log explorer</a>
                                </div>
                                @forelse ($payload['logs'] as $entry)
                                    <div class="activity-item">
                                        <div class="fw-semibold">{{ $entry->level->label() }}</div>
                                        <div>{{ \Illuminate\Support\Str::limit($entry->message, 120) }}</div>
                                        <div class="small text-secondary">{{ $entry->host?->hostname }} · {{ $entry->logged_at?->diffForHumans() }}</div>
                                    </div>
                                @empty
                                    <p class="text-secondary mb-0">No logs.</p>
                                @endforelse
                            </div>
                            @break
                    @endswitch
                </div>
            @endforeach
        </div>
    @endif
@endsection
