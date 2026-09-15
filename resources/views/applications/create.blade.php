@extends('layouts.app', ['title' => 'New application'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('applications.store') }}">
            @csrf
            @include('applications.partials.form')
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Create application</button>
                <a href="{{ route('applications.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
