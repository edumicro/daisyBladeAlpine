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
// Class names written out in full, not glued together from a prefix and the value: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default position.
$horizontalClass = match($horizontal) {
    'start' => 'indicator-start',
    'center' => 'indicator-center',
    'end' => 'indicator-end',
    default => 'indicator-end',
};
$verticalClass = match($vertical) {
    'top' => 'indicator-top',
    'middle' => 'indicator-middle',
    'bottom' => 'indicator-bottom',
    default => 'indicator-top',
};
$indicatorItemClasses = trim(implode(' ', array_filter([
    'indicator-item',
    $horizontalClass,
    $verticalClass,
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
