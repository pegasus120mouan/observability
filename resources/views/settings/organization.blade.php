@extends('layouts.app', ['title' => 'Organization'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('settings.organization.update') }}">
            @csrf
            @method('PUT')
            @include('organizations.partials.form', ['includeRetention' => true])
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
@endsection
