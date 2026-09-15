@props([
    'title',
    'chart',
    'hint' => null,
    'chartKey' => null,
    'size' => null,
])

@php
    $empty = $chart['empty'] ?? null;
@endphp

<div class="panel">
    <div class="panel-header">
        <h2 class="h6 mb-0">{{ $title }}</h2>
        @if ($hint)
            <span class="small text-secondary" @if ($chartKey) data-live-chart-hint="{{ $chartKey }}" @endif>{{ $hint }}</span>
        @endif
    </div>
    <div class="metric-chart {{ $size === 'lg' ? 'metric-chart-lg' : '' }}">
        <canvas data-metric-chart @if ($chartKey) data-chart-key="{{ $chartKey }}" @endif data-config='@json($chart)'></canvas>
        <div class="metric-chart-empty" data-chart-empty="{{ $chartKey }}" @if (! $empty) hidden @endif>{{ $empty }}</div>
    </div>
</div>
