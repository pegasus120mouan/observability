@extends('layouts.app', ['title' => $host->displayName()])

@section('content')
    <div
        class="host-live"
        data-live-host="{{ route('hosts.live', $host) }}"
        data-live-range="{{ $range }}"
    >
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="{{ route('hosts.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All hosts</a>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge text-bg-success" data-live-indicator>Live</span>
            <form method="GET" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="range" onchange="this.form.submit()">
                    <option value="1h" @selected($range === '1h')>Last 1 hour</option>
                    <option value="6h" @selected($range === '6h')>Last 6 hours</option>
                    <option value="24h" @selected($range === '24h')>Last 24 hours</option>
                </select>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <x-metric-card data-live-metric="cpu" label="CPU" :value="isset($usage['cpu']) ? number_format($usage['cpu'], 1) : null" hint="Updated every 5s" />
        </div>
        <div class="col-md-3">
            <x-metric-card data-live-metric="memory" label="Memory" :value="isset($usage['memory']) ? number_format($usage['memory'], 1) : null" hint="Updated every 5s" />
        </div>
        <div class="col-md-3">
            <x-metric-card data-live-metric="disk" label="Disk" :value="isset($usage['disk']) ? number_format($usage['disk'], 1) : null" hint="Updated every 5s" />
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-card-label">Status</div>
                    <div class="mt-1"><x-status-badge data-live-status :value="$host->status->label()" :variant="$host->status->badgeVariant()" /></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-metric-chart title="CPU usage" chart-key="cpu" :chart="$cpuChart" hint="{{ $range }}" />
        </div>
        <div class="col-lg-6">
            <x-metric-chart title="Memory usage" chart-key="memory" :chart="$memoryChart" hint="{{ $range }}" />
        </div>
        <div class="col-lg-6">
            <x-metric-chart title="Disk usage" chart-key="disk" :chart="$diskChart" hint="{{ $range }}" />
        </div>
        <div class="col-lg-6">
            <x-metric-chart title="Network received" chart-key="network" :chart="$networkInChart" hint="{{ $range }}" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Host details</h2>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Hostname</dt>
                    <dd class="col-sm-8">{{ $host->hostname }}</dd>
                    <dt class="col-sm-4">IP address</dt>
                    <dd class="col-sm-8">{{ $host->ip_address ?? '—' }}</dd>
                    <dt class="col-sm-4">Operating system</dt>
                    <dd class="col-sm-8 d-flex align-items-center gap-2">
                        <x-os-logo :os="$host->operating_system" />
                        <span>{{ $host->operating_system ?? '—' }} {{ $host->os_version }}</span>
                    </dd>
                    <dt class="col-sm-4">Architecture</dt>
                    <dd class="col-sm-8">{{ $host->architecture ?? '—' }}</dd>
                    <dt class="col-sm-4">Last seen</dt>
                    <dd class="col-sm-8">{{ $host->last_seen_at?->toDayDateTimeString() ?? 'Never' }}</dd>
                    <dt class="col-sm-4">Registered</dt>
                    <dd class="col-sm-8">{{ $host->registered_at?->toDayDateTimeString() ?? '—' }}</dd>
                    <dt class="col-sm-4">Agent</dt>
                    <dd class="col-sm-8">
                        @if ($host->agent)
                            {{ $host->agent->agent_uid }}
                            · {{ $host->agent->platform?->label() ?? '—' }}
                            · v{{ $host->agent->version ?? '—' }}
                            · <x-status-badge :value="$host->agent->status->label()" :variant="$host->agent->status->badgeVariant()" />
                        @else
                            No agent linked
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Display settings</h2>
                </div>
                @can('update', $host)
                    <form method="POST" action="{{ route('hosts.update', $host) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="display_name">Display name</label>
                            <input class="form-control" id="display_name" name="display_name" value="{{ old('display_name', $host->display_name) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="environment">Environment</label>
                            <select class="form-select" id="environment" name="environment">
                                @foreach (App\Enums\HostEnvironment::cases() as $environment)
                                    <option value="{{ $environment->value }}" @selected(old('environment', $host->environment->value) === $environment->value)>
                                        {{ $environment->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    </form>
                @else
                    <p class="mb-1"><span class="text-secondary">Display name:</span> {{ $host->display_name ?: '—' }}</p>
                    <p class="mb-0"><span class="text-secondary">Environment:</span> {{ $host->environment->label() }}</p>
                @endcan
            </div>
        </div>
    </div>

    @if ($applications->isNotEmpty())
        <div class="panel mt-3">
            <div class="panel-header">
                <h2 class="h6 mb-0">Applications</h2>
                @can('viewAny', App\Models\Application::class)
                    <a href="{{ route('applications.index') }}" class="small">All applications</a>
                @endcan
            </div>
            @foreach ($applications as $application)
                <div class="activity-item">
                    <a href="{{ route('applications.show', $application) }}" class="fw-semibold text-decoration-none">{{ $application->name }}</a>
                    <div class="small text-secondary">{{ $application->type->label() }} · {{ $application->environment->label() }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="panel mt-3 p-0">
        <div class="panel-header px-3 pt-3 mb-2">
            <h2 class="h6 mb-0">Active alerts</h2>
            @can('viewAny', App\Models\Alert::class)
                <a href="{{ route('alerts.index', ['host_id' => $host->id]) }}" class="small">All alerts</a>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Alert</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentAlerts as $alert)
                        <tr>
                            <td>
                                <a href="{{ route('alerts.show', $alert) }}" class="text-decoration-none">{{ $alert->title }}</a>
                                <div class="small text-secondary">{{ $alert->triggered_at?->diffForHumans() }}</div>
                            </td>
                            <td><x-status-badge :value="$alert->severity->label()" :variant="$alert->severity->badgeVariant()" /></td>
                            <td><x-status-badge :value="$alert->status->label()" :variant="$alert->status->badgeVariant()" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">No active alerts for this host.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel mt-3 p-0">
        <div class="panel-header px-3 pt-3 mb-2">
            <h2 class="h6 mb-0">Recent logs</h2>
            @can('viewAny', App\Models\LogEntry::class)
                <a href="{{ route('logs.index', ['host_id' => $host->id]) }}" class="small">Open in explorer</a>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Level</th>
                        <th>Source</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentLogs as $entry)
                        <tr>
                            <td class="text-nowrap small">{{ $entry->logged_at?->diffForHumans() }}</td>
                            <td>
                                <x-status-badge :value="$entry->level->label()" :variant="$entry->level->badgeVariant()" />
                            </td>
                            <td>{{ $entry->source ?? '—' }}</td>
                            <td class="small">{{ $entry->message }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-secondary">No log entries reported for this host yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
@endsection
