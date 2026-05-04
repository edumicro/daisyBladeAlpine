@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'direction'      => 'horizontal',
    'responsive'     => '',
    'class'          => '',
    'containerClass' => '',
])

@php
$joinClasses = trim(implode(' ', array_filter([
    'join',
    $responsive ?: ($direction === 'vertical' ? 'join-vertical' : 'join-horizontal'),
    $class,
])));
@endphp

<div class="{{ $containerClass }}">
    @if($label || $icon)
        <div class="flex items-center gap-2 mb-2">
            @if($icon && $iconSide === 'left')
                <x-dynamic-component :component="$icon" class="h-5 w-5" />
            @endif
            @if($label)
                <span class="font-semibold">{{ $label }}</span>
            @endif
            @if($icon && $iconSide === 'right')
                <x-dynamic-component :component="$icon" class="h-5 w-5" />
            @endif
        </div>
    @endif

    <div {{ $attributes->merge(['class' => $joinClasses]) }}>
        {{ $slot }}
    </div>
</div>
