@extends('layouts.app', ['title' => 'Alert rules'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Thresholds evaluated every minute against metrics, heartbeats, and error logs.</p>
        @can('create', App\Models\AlertRule::class)
            <a class="btn btn-primary btn-sm" href="{{ route('alert-rules.create') }}">New rule</a>
        @endcan
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Condition</th>
                        <th>Severity</th>
                        <th>Enabled</th>
                        <th>Active alerts</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td class="fw-semibold">{{ $rule->name }}</td>
                            <td class="small">{{ $rule->summary() }}</td>
                            <td><x-status-badge :value="$rule->severity->label()" :variant="$rule->severity->badgeVariant()" /></td>
                            <td>{{ $rule->enabled ? 'Yes' : 'No' }}</td>
                            <td>{{ number_format($rule->open_alerts_count) }}</td>
                            <td class="text-end">
                                @can('update', $rule)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('alert-rules.edit', $rule) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">No alert rules yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rules->hasPages())
            <div class="p-3">{{ $rules->links() }}</div>
        @endif
    </div>
@endsection
