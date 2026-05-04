@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'center'         => false,
    'direction'      => 'vertical',
    'bgClass'        => 'bg-base-200 text-base-content',
    'class'          => '',
    'containerClass' => '',
])

@php
$footerClasses = trim(implode(' ', array_filter([
    'footer', $bgClass,
    $center ? 'footer-center' : '',
    $direction === 'horizontal' ? 'footer-horizontal' : 'footer-vertical',
    'p-10', $class,
])));
@endphp

<footer {{ $attributes->merge(['class' => $footerClasses]) }}>
    @if($label || $icon)
        <div class="flex items-center gap-2">
            @if($icon && $iconSide === 'left')
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            @endif
            @if($label)
                <span class="footer-title">{{ $label }}</span>
            @endif
            @if($icon && $iconSide === 'right')
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            @endif
        </div>
    @endif

    {{ $slot }}
</footer>
