@extends('layouts.app', ['title' => 'Overview'])

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Organization" :value="$organization?->name ?? 'Platform'" hint="{{ $organization?->status?->label() ?? 'All tenants' }}" icon="bi-building" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Users" :value="$userCount" hint="Members in scope" icon="bi-people" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Admins" :value="$adminCount" hint="Organization administrators" icon="bi-shield-check" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Organizations" :value="$organizationCount" hint="Visible tenants" icon="bi-diagram-3" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Hosts" :value="$hostCount" hint="{{ $hostOnlineCount }} online" icon="bi-hdd-network" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Agents" :value="$agentCount" hint="Registered collectors" icon="bi-cpu" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Online hosts" :value="$hostOnlineCount" hint="Reporting a recent heartbeat" icon="bi-heart-pulse" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Error logs" :value="$errorLogCount" hint="Last 24 hours" icon="bi-journal-x" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Open alerts" :value="$openAlertCount" hint="Needs attention" icon="bi-exclamation-triangle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Open incidents" :value="$openIncidentCount" hint="Needs response" icon="bi-lightning" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Infrastructure snapshot</h2>
                    @can('viewAny', App\Models\Host::class)
                        <a href="{{ route('metrics.index') }}" class="small">Metrics explorer</a>
                    @endcan
                    @can('viewAny', App\Models\Alert::class)
                        <a href="{{ route('alerts.index') }}" class="small ms-2">Alerts</a>
                    @endcan
                    @can('viewAny', App\Models\Incident::class)
                        <a href="{{ route('incidents.index') }}" class="small ms-2">Incidents</a>
                    @endcan
                </div>
                @if (count($cpuChart['values'] ?? []) === 0)
                    <p class="text-secondary mb-0">Average CPU appears here after collectors report samples.</p>
                @else
                    <div class="metric-chart">
                        <canvas data-metric-chart data-config='@json($cpuChart)'></canvas>
                    </div>
                @endif
            </div>
            @can('viewAny', App\Models\Incident::class)
                <div class="panel mt-3">
                    <div class="panel-header">
                        <h2 class="h6 mb-0">Recent incidents</h2>
                        <a href="{{ route('incidents.index') }}" class="small">All incidents</a>
                    </div>
                    @forelse ($recentIncidents as $incident)
                        <div class="activity-item">
                            <a href="{{ route('incidents.show', $incident) }}" class="fw-semibold text-decoration-none">{{ $incident->reference() }}</a>
                            <div>{{ $incident->title }}</div>
                            <div class="small text-secondary">
                                {{ $incident->host?->hostname ?? 'No host' }}
                                · {{ $incident->status->label() }}
                                · {{ $incident->detected_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">No incidents yet.</p>
                    @endforelse
                </div>
            @endcan
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Recent activity</h2>
                </div>
                @forelse ($recentAuditLogs as $log)
                    <div class="activity-item">
                        <div class="fw-semibold">{{ $log->action->value }}</div>
                        <div class="small text-secondary">
                            {{ $log->user?->name ?? 'System' }}
                            · {{ $log->created_at?->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No audit events yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
