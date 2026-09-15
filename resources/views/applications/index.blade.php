@extends('layouts.app', ['title' => 'Applications'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Request, error, and latency samples for services in this organization.</p>
        @can('create', App\Models\Application::class)
            <a class="btn btn-primary btn-sm" href="{{ route('applications.create') }}">New application</a>
        @endcan
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-stat-card label="Healthy" :value="$healthy" hint="Within thresholds" icon="bi-check-circle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Warning" :value="$warning" hint="Elevated errors or latency" icon="bi-exclamation-circle" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Critical" :value="$critical" hint="Needs attention" icon="bi-x-octagon" />
        </div>
        <div class="col-md-3">
            <x-stat-card label="Unknown" :value="$unknown" hint="No samples yet" icon="bi-question-circle" />
        </div>
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Type</th>
                        <th>Environment</th>
                        <th>Host</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Error rate</th>
                        <th>P95</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        @php $sample = $latest->get($application->id); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('applications.show', $application) }}" class="fw-semibold text-decoration-none">{{ $application->name }}</a>
                                @if ($application->endpoint)
                                    <div class="small text-secondary">{{ $application->endpoint }}</div>
                                @endif
                            </td>
                            <td>{{ $application->type->label() }}</td>
                            <td>{{ $application->environment->label() }}</td>
                            <td>
                                @if ($application->host)
                                    <div class="d-flex align-items-center gap-2">
                                        <x-os-logo :os="$application->host->operating_system" />
                                        <a href="{{ route('hosts.show', $application->host) }}" class="text-decoration-none">{{ $application->host->hostname }}</a>
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <x-status-badge :value="$application->status->label()" :variant="$application->status->badgeVariant()" />
                            </td>
                            <td>{{ $sample ? number_format($sample->request_count) : '—' }}</td>
                            <td>{{ $sample ? number_format(App\Support\ApmCatalog::errorRate($sample->request_count, $sample->error_count), 1).'%' : '—' }}</td>
                            <td>{{ $sample ? number_format($sample->response_time_p95).' ms' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-secondary">No applications yet. Register one or send samples from an agent.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($applications->hasPages())
            <div class="p-3">{{ $applications->links() }}</div>
        @endif
    </div>
@endsection
