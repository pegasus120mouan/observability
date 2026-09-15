@php
    $rule = $rule ?? null;
    $mailTargets = old('mail_targets', collect($rule?->channels() ?? [])->where('channel', 'mail')->pluck('target')->implode(', '));
    $webhookUrl = old('webhook_url', collect($rule?->channels() ?? [])->firstWhere('channel', 'webhook')['target'] ?? '');
    $notifyMail = old('notify_mail', $mailTargets !== '');
    $notifyWebhook = old('notify_webhook', $webhookUrl !== '');
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $rule?->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2">{{ old('description', $rule?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="metric_type">Metric</label>
        <select class="form-select @error('metric_type') is-invalid @enderror" id="metric_type" name="metric_type" required>
            @foreach ($metrics as $metric)
                <option value="{{ $metric->value }}" @selected(old('metric_type', $rule?->metric_type->value) === $metric->value)>{{ $metric->label() }}</option>
            @endforeach
        </select>
        @error('metric_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="condition">Condition</label>
        <select class="form-select @error('condition') is-invalid @enderror" id="condition" name="condition">
            @foreach ($conditions as $condition)
                <option value="{{ $condition->value }}" @selected(old('condition', $rule?->condition->value) === $condition->value)>{{ $condition->symbol() }}</option>
            @endforeach
        </select>
        @error('condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label" for="threshold">Threshold</label>
        <input class="form-control @error('threshold') is-invalid @enderror" id="threshold" name="threshold" type="number" step="0.01" min="0" value="{{ old('threshold', $rule?->threshold) }}">
        @error('threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="duration">Duration (minutes)</label>
        <input class="form-control @error('duration') is-invalid @enderror" id="duration" name="duration" type="number" min="1" max="1440" value="{{ old('duration', $rule?->duration ?? 5) }}" required>
        @error('duration') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-md-4">
        <label class="form-label" for="severity">Severity</label>
        <select class="form-select @error('severity') is-invalid @enderror" id="severity" name="severity" required>
            @foreach ($severities as $severity)
                <option value="{{ $severity->value }}" @selected(old('severity', $rule?->severity->value ?? 'high') === $severity->value)>{{ $severity->label() }}</option>
            @endforeach
        </select>
        @error('severity') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="enabled" name="enabled" @checked(old('enabled', $rule?->enabled ?? true))>
            <label class="form-check-label" for="enabled">Enabled</label>
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" value="1" id="notify_mail" name="notify_mail" @checked($notifyMail)>
        <label class="form-check-label" for="notify_mail">Email notification</label>
    </div>
    <input class="form-control @error('mail_targets') is-invalid @enderror" id="mail_targets" name="mail_targets" value="{{ $mailTargets }}" placeholder="ops@acme.test, oncall@acme.test">
    @error('mail_targets') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" value="1" id="notify_webhook" name="notify_webhook" @checked($notifyWebhook)>
        <label class="form-check-label" for="notify_webhook">Webhook notification</label>
    </div>
    <input class="form-control @error('webhook_url') is-invalid @enderror" id="webhook_url" name="webhook_url" type="url" value="{{ $webhookUrl }}" placeholder="https://example.test/hooks/saha">
    @error('webhook_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
