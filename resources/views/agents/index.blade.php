@extends('layouts.app', ['title' => 'Agents'])

@section('content')
    @if (session('enrollment_token_plain'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Enrollment token (shown once)</div>
            <code class="user-select-all">{{ session('enrollment_token_plain') }}</code>
            <div class="small mt-2 mb-0">Store this in <code>agent/agent.yml</code>. It will not be displayed again.</div>
        </div>
    @endif

    @if (session('rotated_api_key'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">New API key for {{ session('rotated_agent_uid') }} (shown once)</div>
            <code class="user-select-all">{{ session('rotated_api_key') }}</code>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel p-0">
                <div class="panel-header px-3 pt-3">
                    <h2 class="h6 mb-0">Registered agents</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Host</th>
                                <th>Status</th>
                                <th>Last seen</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($agents as $agent)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $agent->name }}</div>
                                        <div class="small text-secondary">{{ $agent->agent_uid }} · v{{ $agent->version ?? '—' }}</div>
                                    </td>
                                    <td>
                                        @if ($agent->host)
                                            <div class="d-flex align-items-center gap-2">
                                                <x-os-logo :os="$agent->host->operating_system" />
                                                <a href="{{ route('hosts.show', $agent->host) }}">{{ $agent->host->displayName() }}</a>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-badge :value="$agent->status->label()" :variant="$agent->status->badgeVariant()" />
                                    </td>
                                    <td>{{ $agent->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="text-end">
                                        @can('update', $agent)
                                            @if (! $agent->isRevoked())
                                                <form method="POST" action="{{ route('agents.rotate', $agent) }}" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Rotate key</button>
                                                </form>
                                                <form method="POST" action="{{ route('agents.revoke', $agent) }}" class="d-inline" onsubmit="return confirm('Revoke this agent? Heartbeats will be rejected.')">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-secondary">No agents yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($agents->hasPages())
                    <div class="p-3">{{ $agents->links() }}</div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h6 mb-0">Enrollment tokens</h2>
                </div>
                @can('create', App\Models\Agent::class)
                    <form method="POST" action="{{ route('agents.tokens.store') }}" class="mb-3">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="name">Label</label>
                            <input class="form-control" id="name" name="name" required placeholder="Linux fleet" value="{{ old('name') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="expires_in_days">Expires in days</label>
                            <input class="form-control" id="expires_in_days" name="expires_in_days" type="number" min="1" max="365" value="{{ old('expires_in_days', 30) }}">
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Create token</button>
                    </form>
                @endcan

                @forelse ($tokens as $token)
                    <div class="border-top py-2">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">{{ $token->name }}</div>
                                <div class="small text-secondary">
                                    {{ $token->creator?->name ?? 'System' }}
                                    · {{ $token->revoked_at ? 'Revoked' : ($token->expires_at?->isPast() ? 'Expired' : 'Expires '.$token->expires_at?->diffForHumans()) }}
                                </div>
                            </div>
                            @can('create', App\Models\Agent::class)
                                @if ($token->revoked_at === null)
                                    <form method="POST" action="{{ route('agents.tokens.destroy', $token) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No enrollment tokens.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
