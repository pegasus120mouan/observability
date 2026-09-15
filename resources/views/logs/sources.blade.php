@extends('layouts.app', ['title' => 'Log sources'])

@section('content')
    <div class="panel p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Host</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Entries</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sources as $source)
                        <tr>
                            <td class="fw-semibold">{{ $source->name }}</td>
                            <td>
                                @if ($source->host)
                                    <a href="{{ route('hosts.show', $source->host) }}" class="text-decoration-none">{{ $source->host->hostname }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $source->type->label() }}</td>
                            <td>
                                <x-status-badge :value="$source->status->label()" :variant="$source->status->badgeVariant()" />
                            </td>
                            <td>{{ number_format($source->entries_count) }}</td>
                            <td class="text-end">
                                @can('update', $source)
                                    @if ($source->isPaused())
                                        <form method="POST" action="{{ route('log-sources.resume', $source) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Resume</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('log-sources.pause', $source) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Pause</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">No log sources yet. They appear when an agent reports logs.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sources->hasPages())
            <div class="p-3">{{ $sources->links() }}</div>
        @endif
    </div>
@endsection
