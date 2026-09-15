@props([
    'value',
    'variant' => 'secondary',
])

<span {{ $attributes->merge(['class' => 'badge text-bg-'.$variant]) }}>{{ $value }}</span>
