@props([
    'title',
    'chart',
    'hint' => null,
    'chartKey' => null,
])

<div class="panel">
    <div class="panel-header">
        <h2 class="h6 mb-0">{{ $title }}</h2>
        @if ($hint)
            <span class="small text-secondary" @if ($chartKey) data-live-chart-hint @endif>{{ $hint }}</span>
        @endif
    </div>
    <div class="metric-chart">
        <canvas data-metric-chart @if ($chartKey) data-chart-key="{{ $chartKey }}" @endif data-config='@json($chart)'></canvas>
    </div>
</div>
