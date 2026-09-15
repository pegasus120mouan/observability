@props([
    'title',
    'chart',
    'hint' => null,
])

@php
    $points = $chart['values'] ?? ($chart['series'][0]['values'] ?? []);
@endphp

<div class="panel">
    <div class="panel-header">
        <h2 class="h6 mb-0">{{ $title }}</h2>
        @if ($hint)
            <span class="small text-secondary">{{ $hint }}</span>
        @endif
    </div>
    @if (count($points) === 0)
        <p class="text-secondary mb-0">No samples in this window.</p>
    @else
        <div class="metric-chart">
            <canvas data-metric-chart data-config='@json($chart)'></canvas>
        </div>
    @endif
</div>
