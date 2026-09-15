@extends('layouts.app', ['title' => 'Edit organization'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('organizations.update', $organization) }}">
            @csrf
            @method('PUT')
            @include('organizations.partials.form', ['includeRetention' => true])
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <button class="btn btn-outline-danger ms-auto" form="delete-organization" type="submit" onclick="return confirm('Delete this organization and its memberships?')">Delete</button>
                <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
        <form id="delete-organization" method="POST" action="{{ route('organizations.destroy', $organization) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection
