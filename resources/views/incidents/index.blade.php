@extends('layouts.app', ['title' => 'Incidents'])

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Open" :value="$open" hint="New" icon="bi-lightning" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Investigating" :value="$investigating" hint="In progress" icon="bi-search" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Mitigated" :value="$mitigated" hint="Impact reduced" icon="bi-shield-check" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Resolved" :value="$resolved" hint="Waiting to close" icon="bi-check2-circle" />
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" name="severity" onchange="this.form.submit()">
                <option value="">All severities</option>
                @foreach ($severities as $severity)
                    <option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
            <select class="form-select form-select-sm" name="priority" onchange="this.form.submit()">
                <option value="">All priorities</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                @endforeach
            </select>
        </form>
        @can('create', App\Models\Incident::class)
            <a class="btn btn-primary btn-sm" href="{{ route('incidents.create') }}">New incident</a>
        @endcan
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Host</th>
                        <th>Priority</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Assignee</th>
                        <th>Detected</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr>
                            <td>
                                <a href="{{ route('incidents.show', $incident) }}" class="fw-semibold text-decoration-none">{{ $incident->reference() }}</a>
                                <div class="small text-secondary">{{ $incident->title }}</div>
                            </td>
                            <td>
                                @if ($incident->host)
                                    <a href="{{ route('hosts.show', $incident->host) }}" class="text-decoration-none">{{ $incident->host->hostname }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-status-badge :value="$incident->priority->label()" :variant="$incident->priority->badgeVariant()" /></td>
                            <td><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badgeVariant()" /></td>
                            <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badgeVariant()" /></td>
                            <td>{{ $incident->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="small text-nowrap">{{ $incident->detected_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-secondary">No incidents match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidents->hasPages())
            <div class="p-3">{{ $incidents->links() }}</div>
        @endif
    </div>
@endsection
