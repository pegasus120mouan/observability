@props([
    'label',
    'value',
    'unit' => '%',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'stat-card']) }}>
    <div>
        <div class="stat-card-label">{{ $label }}</div>
        <div class="stat-card-value" data-live-value>
            {{ $value === null ? '—' : $value }}@if ($value !== null)<span class="stat-card-hint">{{ $unit }}</span>@endif
        </div>
        @if ($hint)
            <div class="stat-card-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
