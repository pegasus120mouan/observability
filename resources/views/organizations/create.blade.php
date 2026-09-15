@extends('layouts.app', ['title' => 'Create organization'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('organizations.store') }}">
            @csrf
            @include('organizations.partials.form')
            <button class="btn btn-primary" type="submit">Create organization</button>
            <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
@endsection
