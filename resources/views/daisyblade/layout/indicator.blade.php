@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'horizontal'     => 'end',
    'vertical'       => 'top',
    'indicatorClass' => '',
    'contentClass'   => '',
    'class'          => '',
    'containerClass' => '',
])

@php
$indicatorItemClasses = trim(implode(' ', array_filter([
    'indicator-item',
    "indicator-{$horizontal}",
    "indicator-{$vertical}",
    $indicatorClass,
])));
@endphp

<div {{ $attributes->merge(['class' => trim("indicator {$containerClass}")]) }}>
    <span class="{{ $indicatorItemClasses }}">
        @if($icon && $iconSide === 'left')
            <x-dynamic-component :component="$icon" class="h-4 w-4" />
        @endif

        @if($label)
            {{ $label }}
        @elseif(isset($indicator))
            {{ $indicator }}
        @endif

        @if($icon && $iconSide === 'right')
            <x-dynamic-component :component="$icon" class="h-4 w-4" />
        @endif
    </span>

    <div class="{{ trim("{$contentClass} {$class}") }}">
        {{ $slot }}
    </div>
</div>
