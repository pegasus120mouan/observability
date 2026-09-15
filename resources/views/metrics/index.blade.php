@extends('layouts.app', ['title' => 'Metrics'])

@section('content')
    <form method="GET" class="panel mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="host_id">Host</label>
                <select class="form-select" id="host_id" name="host_id">
                    @forelse ($hosts as $host)
                        <option value="{{ $host->id }}" @selected($selectedHost?->id === $host->id)>{{ $host->displayName() }} · {{ $host->hostname }}</option>
                    @empty
                        <option value="">No hosts</option>
                    @endforelse
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="metric">Metric</label>
                <select class="form-select" id="metric" name="metric">
                    @foreach ($catalog as $item)
                        <option value="{{ $item['type']->value }}.{{ $item['name'] }}" @selected($metricKey === $item['type']->value.'.'.$item['name'])>
                            {{ $item['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="range">Range</label>
                <select class="form-select" id="range" name="range">
                    <option value="1h" @selected($range === '1h')>1 hour</option>
                    <option value="6h" @selected($range === '6h')>6 hours</option>
                    <option value="24h" @selected($range === '24h')>24 hours</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </div>
    </form>

    <x-metric-chart :title="$chart['label']" :chart="$chart" :hint="$selectedHost?->displayName()" />
@endsection
