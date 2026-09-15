@php
    $application = $application ?? null;
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $application?->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="type">Runtime</label>
        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $application?->type->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="environment">Environment</label>
        <select class="form-select" id="environment" name="environment" required>
            @foreach ($environments as $environment)
                <option value="{{ $environment->value }}" @selected(old('environment', $application?->environment->value ?? 'production') === $environment->value)>{{ $environment->label() }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row g-3 mt-0">
    <div class="col-md-6">
        <label class="form-label" for="version">Version</label>
        <input class="form-control" id="version" name="version" value="{{ old('version', $application?->version) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="endpoint">Endpoint</label>
        <input class="form-control @error('endpoint') is-invalid @enderror" id="endpoint" name="endpoint" value="{{ old('endpoint', $application?->endpoint) }}" placeholder="https://api.example.test/orders">
        @error('endpoint') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mb-3 mt-3">
    <label class="form-label" for="host_id">Host (optional)</label>
    <select class="form-select @error('host_id') is-invalid @enderror" id="host_id" name="host_id">
        <option value="">None</option>
        @foreach ($hosts as $host)
            <option value="{{ $host->id }}" @selected((string) old('host_id', $application?->host_id) === (string) $host->id)>{{ $host->hostname }}</option>
        @endforeach
    </select>
    @error('host_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $application?->description) }}</textarea>
</div>
