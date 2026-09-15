@extends('layouts.app', ['title' => $incident->reference()])

@section('content')
    <div class="mb-3">
        <a href="{{ route('incidents.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All incidents</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel mb-3">
                <div class="panel-header">
                    <div>
                        <div class="small text-secondary mb-1">{{ $incident->reference() }}</div>
                        <h2 class="h5 mb-0">{{ $incident->title }}</h2>
                    </div>
                    <div class="d-flex gap-2">
                        <x-status-badge :value="$incident->priority->label()" :variant="$incident->priority->badgeVariant()" />
                        <x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badgeVariant()" />
                        <x-status-badge :value="$incident->status->label()" :variant="$incident->status->badgeVariant()" />
                    </div>
                </div>
                <p class="mb-3">{{ $incident->description ?: 'No description.' }}</p>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Host</dt>
                    <dd class="col-sm-8">
                        @if ($incident->host)
                            <a href="{{ route('hosts.show', $incident->host) }}">{{ $incident->host->hostname }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">Assignee</dt>
                    <dd class="col-sm-8">{{ $incident->assignee?->name ?? 'Unassigned' }}</dd>
                    <dt class="col-sm-4">Detected</dt>
                    <dd class="col-sm-8">{{ $incident->detected_at?->toDayDateTimeString() }}</dd>
                    <dt class="col-sm-4">Resolved</dt>
                    <dd class="col-sm-8">{{ $incident->resolved_at?->toDayDateTimeString() ?? '—' }}</dd>
                    <dt class="col-sm-4">Closed</dt>
                    <dd class="col-sm-8">{{ $incident->closed_at?->toDayDateTimeString() ?? '—' }}</dd>
                </dl>
            </div>

            @if ($incident->alerts->isNotEmpty())
                <div class="panel mb-3">
                    <div class="panel-header">
                        <h3 class="h6 mb-0">Linked alerts</h3>
                    </div>
                    @foreach ($incident->alerts as $alert)
                        <div class="activity-item">
                            <a href="{{ route('alerts.show', $alert) }}">{{ $alert->title }}</a>
                            <div class="small text-secondary">{{ $alert->host?->hostname }} · {{ $alert->status->label() }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="panel">
                <div class="panel-header">
                    <h3 class="h6 mb-0">Timeline</h3>
                </div>
                <ol class="timeline mb-0">
                    @forelse ($incident->events as $event)
                        <li class="timeline-item">
                            <div class="fw-semibold">{{ $event->type->label() }}</div>
                            <div>{{ $event->message }}</div>
                            <div class="small text-secondary">
                                {{ $event->user?->name ?? 'System' }}
                                · {{ $event->created_at?->toDayDateTimeString() }}
                            </div>
                        </li>
                    @empty
                        <li class="text-secondary">No timeline events yet.</li>
                    @endforelse
                </ol>
            </div>
        </div>
        <div class="col-lg-5">
            @can('update', $incident)
                @unless ($incident->isClosed())
                    <div class="panel mb-3">
                        <div class="panel-header">
                            <h3 class="h6 mb-0">Update</h3>
                        </div>
                        <form method="POST" action="{{ route('incidents.update', $incident) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected(old('status', $incident->status->value) === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="assigned_to">Assignee</label>
                                <select class="form-select" id="assigned_to" name="assigned_to">
                                    <option value="">Unassigned</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}" @selected((string) old('assigned_to', $incident->assigned_to) === (string) $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="priority">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority->value }}" @selected(old('priority', $incident->priority->value) === $priority->value)>{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="severity">Severity</label>
                                    <select class="form-select" id="severity" name="severity">
                                        @foreach ($severities as $severity)
                                            <option value="{{ $severity->value }}" @selected(old('severity', $incident->severity->value) === $severity->value)>{{ $severity->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label" for="root_cause">Root cause</label>
                                <textarea class="form-control" id="root_cause" name="root_cause" rows="2">{{ old('root_cause', $incident->root_cause) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="resolution">Resolution</label>
                                <textarea class="form-control" id="resolution" name="resolution" rows="2">{{ old('resolution', $incident->resolution) }}</textarea>
                            </div>
                            <button class="btn btn-primary btn-sm" type="submit">Save changes</button>
                        </form>
                    </div>
                    <div class="panel">
                        <div class="panel-header">
                            <h3 class="h6 mb-0">Add comment</h3>
                        </div>
                        <form method="POST" action="{{ route('incidents.comment', $incident) }}">
                            @csrf
                            <textarea class="form-control mb-2 @error('message') is-invalid @enderror" name="message" rows="3" required>{{ old('message') }}</textarea>
                            @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <button class="btn btn-outline-primary btn-sm" type="submit">Add to timeline</button>
                        </form>
                    </div>
                @else
                    <div class="panel">
                        <p class="text-secondary mb-0">This incident is closed.</p>
                    </div>
                @endunless
            @else
                <div class="panel">
                    <p class="text-secondary mb-0">You can view this incident but cannot change it.</p>
                </div>
            @endcan
        </div>
    </div>
@endsection
