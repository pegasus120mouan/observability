@extends('layouts.app', ['title' => 'Hosts'])

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Online" :value="$online" hint="Reporting" icon="bi-check-circle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Warning" :value="$warning" hint="Degraded" icon="bi-exclamation-circle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Critical" :value="$critical" hint="Needs attention" icon="bi-x-octagon" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Offline" :value="$offline" hint="Missed heartbeat" icon="bi-plug" />
        </div>
    </div>

    <div class="panel p-0" data-live-hosts="{{ route('hosts.live-index') }}">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th>IP</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>CPU</th>
                        <th>Memory</th>
                        <th>Disk</th>
                        <th>Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hosts as $host)
                        <tr data-host-id="{{ $host->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-os-logo :os="$host->operating_system" />
                                    <div>
                                        <a href="{{ route('hosts.show', $host) }}" class="fw-semibold text-decoration-none">{{ $host->displayName() }}</a>
                                        <div class="small text-secondary">{{ $host->hostname }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $host->ip_address ?? '—' }}</td>
                            <td>{{ $host->environment->label() }}</td>
                            <td data-host-col="status">
                                <x-status-badge :value="$host->status->label()" :variant="$host->status->badgeVariant()" />
                            </td>
                            @php
                                $samples = $latest->get($host->id, collect());
                                $cpu = $samples->first(fn ($sample) => $sample->metric_type === App\Enums\MetricType::Cpu)?->value;
                                $memory = $samples->first(fn ($sample) => $sample->metric_type === App\Enums\MetricType::Memory)?->value;
                                $disk = $samples->first(fn ($sample) => $sample->metric_type === App\Enums\MetricType::Disk)?->value;
                            @endphp
                            <td data-host-col="cpu">{{ $cpu === null ? '—' : number_format($cpu, 1).'%' }}</td>
                            <td data-host-col="memory">{{ $memory === null ? '—' : number_format($memory, 1).'%' }}</td>
                            <td data-host-col="disk">{{ $disk === null ? '—' : number_format($disk, 1).'%' }}</td>
                            <td data-host-col="last-seen">{{ $host->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-secondary">No hosts registered yet. Create an enrollment token on the Agents page.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($hosts->hasPages())
            <div class="p-3">{{ $hosts->links() }}</div>
        @endif
    </div>
@endsection
