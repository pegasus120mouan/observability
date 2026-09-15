@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'bi-dot',
])

<div {{ $attributes->merge(['class' => 'stat-card']) }}>
    <div class="stat-card-icon"><i class="bi {{ $icon }}"></i></div>
    <div>
        <div class="stat-card-label">{{ $label }}</div>
        <div class="stat-card-value">{{ $value }}</div>
        @if ($hint)
            <div class="stat-card-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
