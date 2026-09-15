@extends('layouts.app', ['title' => 'Edit '.$application->name])

@section('content')
    <div class="mb-3">
        <a href="{{ route('applications.show', $application) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to application</a>
    </div>

    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('applications.update', $application) }}">
            @csrf
            @method('PUT')
            @include('applications.partials.form', ['application' => $application])
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save</button>
                <a href="{{ route('applications.show', $application) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
        <form method="POST" action="{{ route('applications.destroy', $application) }}" class="mt-3" onsubmit="return confirm('Delete this application?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm" type="submit">Delete application</button>
        </form>
    </div>
@endsection
