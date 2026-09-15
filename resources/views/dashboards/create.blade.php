@extends('layouts.app', ['title' => 'New dashboard'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('dashboards.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" @checked(old('is_default'))>
                <label class="form-check-label" for="is_default">Make this the default dashboard</label>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="seed_layout" name="seed_layout" value="1" @checked(old('seed_layout', true))>
                <label class="form-check-label" for="seed_layout">Add starter widgets (stats, CPU/memory, alerts, incidents)</label>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Create dashboard</button>
                <a href="{{ route('dashboards.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
