@extends('layouts.app', ['title' => 'Dashboards'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Saved views of hosts, metrics, alerts, and incidents for this organization.</p>
        @can('create', App\Models\Dashboard::class)
            <a class="btn btn-primary btn-sm" href="{{ route('dashboards.create') }}">New dashboard</a>
        @endcan
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Widgets</th>
                        <th>Default</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dashboards as $dashboard)
                        <tr>
                            <td>
                                <a href="{{ route('dashboards.show', $dashboard) }}" class="fw-semibold text-decoration-none">{{ $dashboard->name }}</a>
                                @if ($dashboard->description)
                                    <div class="small text-secondary">{{ $dashboard->description }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($dashboard->widgets_count) }}</td>
                            <td>{{ $dashboard->is_default ? 'Yes' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">No dashboards yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($dashboards->hasPages())
            <div class="p-3">{{ $dashboards->links() }}</div>
        @endif
    </div>
@endsection
