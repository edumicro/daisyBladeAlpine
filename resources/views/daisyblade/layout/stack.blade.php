@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'align'          => 'bottom',
    'class'          => '',
    'containerClass' => '',
])

@php
$stackClasses = trim(implode(' ', array_filter([
    'stack',
    $align === 'top'    ? 'stack-top'    : '',
    $align === 'bottom' ? 'stack-bottom' : '',
    $align === 'start'  ? 'stack-start'  : '',
    $align === 'end'    ? 'stack-end'    : '',
    $class,
])));
@endphp

<div {{ $attributes->merge(['class' => trim("{$stackClasses} {$containerClass}")]) }}>
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

    {{ $slot }}
</div>
