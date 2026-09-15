@extends('layouts.app', ['title' => 'Log Explorer'])

@section('content')
    <form method="GET" class="panel mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="q">Keyword</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search message, source, process">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="host_id">Host</label>
                <select class="form-select" id="host_id" name="host_id">
                    <option value="">All hosts</option>
                    @foreach ($hosts as $host)
                        <option value="{{ $host->id }}" @selected((string) ($filters['host_id'] ?? '') === (string) $host->id)>
                            {{ $host->displayName() }} · {{ $host->hostname }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="source_id">Source</label>
                <select class="form-select" id="source_id" name="source_id">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected((string) ($filters['source_id'] ?? '') === (string) $source->id)>
                            {{ $source->name }} · {{ $source->host?->hostname }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="level">Level</label>
                <select class="form-select" id="level" name="level">
                    <option value="">All levels</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->value }}" @selected(($filters['level'] ?? '') === $level->value)>{{ $level->label() }}</option>
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
            <div class="col-md-2">
                <label class="form-label" for="ip">IP</label>
                <input class="form-control" id="ip" name="ip" value="{{ $filters['ip'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="user">User</label>
                <input class="form-control" id="user" name="user" value="{{ $filters['user'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="process">Process</label>
                <input class="form-control" id="process" name="process" value="{{ $filters['process'] ?? '' }}">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline-secondary" href="{{ route('logs.index') }}">Reset</a>
            <a class="btn btn-outline-secondary" href="{{ route('logs.export', array_merge($filters, ['format' => 'csv'])) }}">Export CSV</a>
            <a class="btn btn-outline-secondary" href="{{ route('logs.export', array_merge($filters, ['format' => 'json'])) }}">Export JSON</a>
        </div>
    </form>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Host</th>
                        <th>Level</th>
                        <th>Source</th>
                        <th>Process</th>
                        <th>User</th>
                        <th>IP</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="text-nowrap small">{{ $entry->logged_at?->toDateTimeString() }}</td>
                            <td>
                                @if ($entry->host)
                                    <a href="{{ route('hosts.show', $entry->host) }}" class="text-decoration-none">{{ $entry->host->hostname }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <x-status-badge :value="$entry->level->label()" :variant="$entry->level->badgeVariant()" />
                            </td>
                            <td>{{ $entry->source ?? '—' }}</td>
                            <td>{{ $entry->process ?? '—' }}</td>
                            <td>{{ $entry->username ?? '—' }}</td>
                            <td>{{ $entry->ip_address ?? '—' }}</td>
                            <td class="small">{{ $entry->message }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-secondary">No log entries match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="p-3">{{ $entries->links() }}</div>
        @endif
    </div>
@endsection
