@extends('layouts.app', ['title' => 'Add user'])

@section('content')
    <div class="panel" style="max-width: 640px;">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            @include('users.partials.form')
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Create user</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
