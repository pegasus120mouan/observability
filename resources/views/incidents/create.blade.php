@extends('layouts.app', ['title' => 'New incident'])

@section('content')
    <div class="panel" style="max-width: 720px;">
        <form method="POST" action="{{ route('incidents.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="title">Title</label>
                <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="severity">Severity</label>
                    <select class="form-select" id="severity" name="severity" required>
                        @foreach ($severities as $severity)
                            <option value="{{ $severity->value }}" @selected(old('severity', 'high') === $severity->value)>{{ $severity->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'p2') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="host_id">Host</label>
                    <select class="form-select" id="host_id" name="host_id">
                        <option value="">None</option>
                        @foreach ($hosts as $host)
                            <option value="{{ $host->id }}" @selected((string) old('host_id') === (string) $host->id)>{{ $host->hostname }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-4 mt-3">
                <label class="form-label" for="assigned_to">Assignee</label>
                <select class="form-select" id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected((string) old('assigned_to') === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Open incident</button>
                <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
