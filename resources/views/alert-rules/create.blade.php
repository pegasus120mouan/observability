@extends('layouts.app', ['title' => 'New alert rule'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('alert-rules.store') }}">
            @csrf
            @include('alert-rules.partials.form')
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Create rule</button>
                <a href="{{ route('alert-rules.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
