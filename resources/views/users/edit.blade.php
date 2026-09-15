@extends('layouts.app', ['title' => 'Edit user'])

@section('content')
    <div class="panel" style="max-width: 640px;">
        <form method="POST" action="{{ route('users.update', $member) }}">
            @csrf
            @method('PUT')
            @include('users.partials.form', ['member' => $member, 'currentRole' => $currentRole])
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save changes</button>
                @can('delete', $member)
                    <button class="btn btn-outline-danger ms-auto" form="delete-user" type="submit" onclick="return confirm('Remove this user from the organization?')">Remove</button>
                @endcan
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>

        @can('delete', $member)
            <form id="delete-user" method="POST" action="{{ route('users.destroy', $member) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </div>
@endsection
