@extends('layouts.app', ['title' => $alert->title])

@section('content')
    <div class="mb-3">
        <a href="{{ route('alerts.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All alerts</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">{{ $alert->title }}</h2>
                    <div class="d-flex gap-2">
                        <x-status-badge :value="$alert->severity->label()" :variant="$alert->severity->badgeVariant()" />
                        <x-status-badge :value="$alert->status->label()" :variant="$alert->status->badgeVariant()" />
                    </div>
                </div>
                <p class="mb-3">{{ $alert->description }}</p>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Host</dt>
                    <dd class="col-sm-8">
                        @if ($alert->host)
                            <a href="{{ route('hosts.show', $alert->host) }}">{{ $alert->host->hostname }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">Rule</dt>
                    <dd class="col-sm-8">{{ $alert->rule?->name ?? '—' }} · {{ $alert->rule?->summary() }}</dd>
                    <dt class="col-sm-4">Triggered</dt>
                    <dd class="col-sm-8">{{ $alert->triggered_at?->toDayDateTimeString() }}</dd>
                    <dt class="col-sm-4">Acknowledged</dt>
                    <dd class="col-sm-8">
                        {{ $alert->acknowledged_at?->toDayDateTimeString() ?? '—' }}
                        @if ($alert->acknowledgedBy)
                            · {{ $alert->acknowledgedBy->name }}
                        @endif
                    </dd>
                    <dt class="col-sm-4">Resolved</dt>
                    <dd class="col-sm-8">
                        {{ $alert->resolved_at?->toDayDateTimeString() ?? '—' }}
                        @if ($alert->resolvedBy)
                            · {{ $alert->resolvedBy->name }}
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Actions</h2>
                </div>
                <div class="d-flex flex-column gap-2">
                    @can('update', $alert)
                        @if ($alert->status === App\Enums\AlertStatus::Open)
                            <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                                @csrf
                                <button class="btn btn-outline-warning w-100" type="submit">Acknowledge</button>
                            </form>
                        @endif
                        @if ($alert->isActive())
                            <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                                @csrf
                                <button class="btn btn-outline-success w-100" type="submit">Resolve</button>
                            </form>
                        @endif
                    @endcan
                    @can('create', App\Models\Incident::class)
                        @if ($alert->incident_id)
                            <a class="btn btn-outline-primary w-100" href="{{ route('incidents.show', $alert->incident_id) }}">Open incident</a>
                        @else
                            <form method="POST" action="{{ route('alerts.incident', $alert) }}">
                                @csrf
                                <button class="btn btn-outline-primary w-100" type="submit">Create incident</button>
                            </form>
                        @endif
                    @elsecan('viewAny', App\Models\Incident::class)
                        @if ($alert->incident_id)
                            <a class="btn btn-outline-primary w-100" href="{{ route('incidents.show', $alert->incident_id) }}">Open incident</a>
                        @endif
                    @endcan
                    @cannot('update', $alert)
                        @if (! $alert->incident_id)
                            <p class="text-secondary mb-0">You can view this alert but cannot change its status.</p>
                        @endif
                    @endcannot
                </div>
            </div>
        </div>
    </div>
@endsection
