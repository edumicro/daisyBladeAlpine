@props([
    'value' => 0,
    'max'   => 100,
    'size'  => 'md',
    'color' => 'primary',
    'thick' => false,
    'unit'  => '%',
    'icon'  => '',
    'label' => '',
    'class' => '',
])

@php
$sizeClass  = match($size) { 'xs' => 'w-12 h-12 text-xs', 'sm' => 'w-16 h-16 text-sm', 'lg' => 'w-32 h-32 text-lg', 'xl' => 'w-40 h-40 text-xl', default => 'w-24 h-24 text-base' };
$colorClass = "text-{$color}";
$pct        = $max > 0 ? round(($value / $max) * 100) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2']) }}>
    @if($label)
        <h3 class="font-semibold">{{ $label }}</h3>
    @endif

    <div
        class="radial-progress {{ $sizeClass }} {{ $colorClass }} {{ $thick ? 'font-bold' : '' }} transition-all duration-500 {{ $class }}"
        style="--value:{{ $pct }}"
        role="progressbar"
    >
        <div class="flex flex-col items-center justify-center">
            @if($icon)
                <x-dynamic-component :component="$icon" class="w-1/2 h-1/2 mb-1" />
            @endif
            <span>{{ number_format($value, 0) }}{{ $unit }}</span>
        </div>
    </div>
</div>
