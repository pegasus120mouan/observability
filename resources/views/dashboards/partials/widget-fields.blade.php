@php
    $widget = $widget ?? null;
    $config = $widget?->config ?? [];
    $prefix = $prefix ?? '';
@endphp

<div class="mb-3">
    <label class="form-label" for="{{ $prefix }}title">Title</label>
    <input class="form-control" id="{{ $prefix }}title" name="title" value="{{ old('title', $widget?->title) }}" required>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}type">Type</label>
        <select class="form-select" id="{{ $prefix }}type" name="type" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $widget?->type->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}width">Width</label>
        <select class="form-select" id="{{ $prefix }}width" name="width">
            @foreach ($widths as $width)
                <option value="{{ $width }}" @selected((int) old('width', $widget?->width ?? 6) === $width)>{{ $width }} / 12</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}sort_order">Order</label>
        <input class="form-control" id="{{ $prefix }}sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $widget?->sort_order) }}">
    </div>
</div>
<div class="row g-3 mt-0">
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}stat_metric">Statistic</label>
        <select class="form-select" id="{{ $prefix }}stat_metric" name="stat_metric">
            @foreach ($statMetrics as $metric)
                <option value="{{ $metric->value }}" @selected(old('stat_metric', $config['metric'] ?? '') === $metric->value)>{{ $metric->label() }}</option>
            @endforeach
        </select>
        <div class="form-text">Used when type is Statistic.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}metric">Timeseries metric</label>
        <select class="form-select" id="{{ $prefix }}metric" name="metric_pair">
            @foreach ($catalog as $item)
                @php $pair = $item['type']->value.'.'.$item['name']; @endphp
                <option value="{{ $pair }}" @selected(old('metric_pair', ($config['metric_type'] ?? 'cpu').'.'.($config['metric_name'] ?? 'usage')) === $pair)>{{ $item['label'] }}</option>
            @endforeach
        </select>
        <div class="form-text">Used when type is Timeseries.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}range">Range</label>
        <select class="form-select" id="{{ $prefix }}range" name="range">
            @foreach ($ranges as $range)
                <option value="{{ $range }}" @selected(old('range', $config['range'] ?? '6h') === $range)>{{ $range }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row g-3 mt-0">
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}host_id">Host (optional)</label>
        <select class="form-select" id="{{ $prefix }}host_id" name="host_id">
            <option value="">Organization average</option>
            @foreach ($hosts as $host)
                <option value="{{ $host->id }}" @selected((string) old('host_id', $config['host_id'] ?? '') === (string) $host->id)>{{ $host->hostname }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}limit">List limit</label>
        <input class="form-control" id="{{ $prefix }}limit" name="limit" type="number" min="1" max="50" value="{{ old('limit', $config['limit'] ?? 8) }}">
    </div>
</div>
