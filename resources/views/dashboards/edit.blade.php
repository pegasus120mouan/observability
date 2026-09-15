@extends('layouts.app', ['title' => 'Edit '.$dashboard->name])

@section('content')
    <div class="mb-3">
        <a href="{{ route('dashboards.show', $dashboard) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to dashboard</a>
    </div>

    <div class="panel mb-3" style="max-width: 720px;">
        <form method="POST" action="{{ route('dashboards.update', $dashboard) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $dashboard->name) }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $dashboard->description) }}</textarea>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" @checked(old('is_default', $dashboard->is_default))>
                <label class="form-check-label" for="is_default">Default dashboard</label>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" type="submit">Save</button>
            </div>
        </form>
        <form method="POST" action="{{ route('dashboards.destroy', $dashboard) }}" class="mt-3" onsubmit="return confirm('Delete this dashboard?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm" type="submit">Delete dashboard</button>
        </form>
    </div>

    <h2 class="h5 mb-3">Widgets</h2>
    @foreach ($dashboard->widgets as $widget)
        <div class="panel mb-3">
            <div class="panel-header">
                <h3 class="h6 mb-0">{{ $widget->title }}</h3>
                <span class="small text-secondary">{{ $widget->type->label() }}</span>
            </div>
            <form method="POST" action="{{ route('dashboards.widgets.update', [$dashboard, $widget]) }}">
                @csrf
                @method('PUT')
                @include('dashboards.partials.widget-fields', ['widget' => $widget, 'prefix' => 'w'.$widget->id.'_'])
                <button class="btn btn-primary btn-sm mt-3" type="submit">Update widget</button>
            </form>
            <form method="POST" action="{{ route('dashboards.widgets.destroy', [$dashboard, $widget]) }}" class="mt-2">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
            </form>
        </div>
    @endforeach

    <div class="panel">
        <div class="panel-header">
            <h3 class="h6 mb-0">Add widget</h3>
        </div>
        <form method="POST" action="{{ route('dashboards.widgets.store', $dashboard) }}">
            @csrf
            @include('dashboards.partials.widget-fields', ['widget' => null, 'prefix' => 'new_'])
            <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Add widget</button>
        </form>
    </div>
@endsection
