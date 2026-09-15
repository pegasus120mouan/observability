@php
    $organization = $organization ?? null;
    $includeRetention = $includeRetention ?? false;
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $organization?->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="slug">Slug</label>
    <input id="slug" name="slug" type="text" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $organization?->slug) }}" placeholder="generated-from-name">
    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $organization?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="email">Email</label>
        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $organization?->email) }}">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="phone">Phone</label>
        <input id="phone" name="phone" type="text" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $organization?->phone) }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="address">Address</label>
    <input id="address" name="address" type="text" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $organization?->address) }}">
    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $organization?->status->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="timezone">Timezone</label>
        <input id="timezone" name="timezone" type="text" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $organization?->timezone ?? 'UTC') }}" required>
        @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@if ($includeRetention)
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label" for="metric_retention_days">Metric retention (days)</label>
            <input id="metric_retention_days" name="metric_retention_days" type="number" min="1" class="form-control @error('metric_retention_days') is-invalid @enderror" value="{{ old('metric_retention_days', $organization?->metric_retention_days ?? 30) }}" required>
            @error('metric_retention_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" for="log_retention_days">Log retention (days)</label>
            <input id="log_retention_days" name="log_retention_days" type="number" min="1" class="form-control @error('log_retention_days') is-invalid @enderror" value="{{ old('log_retention_days', $organization?->log_retention_days ?? 90) }}" required>
            @error('log_retention_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label" for="audit_retention_days">Audit retention (days)</label>
            <input id="audit_retention_days" name="audit_retention_days" type="number" min="1" class="form-control @error('audit_retention_days') is-invalid @enderror" value="{{ old('audit_retention_days', $organization?->audit_retention_days ?? 365) }}" required>
            @error('audit_retention_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
@endif
