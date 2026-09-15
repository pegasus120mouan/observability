@extends('layouts.app', ['title' => 'Organizations'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Platform tenants</p>
        <a href="{{ route('organizations.create') }}" class="btn btn-primary btn-sm">Create organization</a>
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th>Timezone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $organization)
                        <tr>
                            <td class="fw-semibold">{{ $organization->name }}</td>
                            <td><code>{{ $organization->slug }}</code></td>
                            <td>
                                <x-status-badge :value="$organization->status->label()" :variant="$organization->status->badgeVariant()" />
                            </td>
                            <td>{{ $organization->users_count }}</td>
                            <td>{{ $organization->timezone }}</td>
                            <td class="text-end">
                                <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">No organizations yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($organizations->hasPages())
            <div class="p-3">{{ $organizations->links() }}</div>
        @endif
    </div>
@endsection
