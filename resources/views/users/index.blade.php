@extends('layouts.app', ['title' => 'Users'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-secondary mb-0">Members of {{ $organization->name }}</p>
        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">Add user</a>
        @endcan
    </div>

    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $member)
                        @php
                            $role = $member->memberships->first()?->role;
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $role?->name->label() ?? '—' }}</td>
                            <td>
                                <x-status-badge :value="$member->status->label()" :variant="$member->status->badgeVariant()" />
                            </td>
                            <td>{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                @can('update', $member)
                                    <a href="{{ route('users.edit', $member) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="p-3">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
