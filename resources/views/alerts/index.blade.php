@extends('layouts.app', ['title' => 'Alerts'])

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Critical" :value="$critical" hint="Active" icon="bi-exclamation-octagon" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="High" :value="$high" hint="Active" icon="bi-exclamation-triangle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Medium" :value="$medium" hint="Active" icon="bi-exclamation-circle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Low" :value="$low" hint="Active" icon="bi-info-circle" />
        </div>
    </div>

    <form method="GET" class="panel mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="severity">Severity</label>
                <select class="form-select" id="severity" name="severity">
                    <option value="">All</option>
                    @foreach ($severities as $severity)
                        <option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="host_id">Host</label>
                <select class="form-select" id="host_id" name="host_id">
                    <option value="">All hosts</option>
                    @foreach ($hosts as $host)
                        <option value="{{ $host->id }}" @selected((string) ($filters['host_id'] ?? '') === (string) $host->id)>{{ $host->hostname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="from">From</label>
                <input class="form-control" id="from" type="datetime-local" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">To</label>
                <input class="form-control" id="to" type="datetime-local" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Triggered</th>
                        <th>Alert</th>
                        <th>Host</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alerts as $alert)
                        <tr>
                            <td class="text-nowrap small">{{ $alert->triggered_at?->toDateTimeString() }}</td>
                            <td>
                                <a href="{{ route('alerts.show', $alert) }}" class="fw-semibold text-decoration-none">{{ $alert->title }}</a>
                                <div class="small text-secondary">{{ $alert->rule?->summary() }}</div>
                            </td>
                            <td>
                                @if ($alert->host)
                                    <a href="{{ route('hosts.show', $alert->host) }}" class="text-decoration-none">{{ $alert->host->hostname }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><x-status-badge :value="$alert->severity->label()" :variant="$alert->severity->badgeVariant()" /></td>
                            <td><x-status-badge :value="$alert->status->label()" :variant="$alert->status->badgeVariant()" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-secondary">No alerts match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($alerts->hasPages())
            <div class="p-3">{{ $alerts->links() }}</div>
        @endif
    </div>
@endsection
