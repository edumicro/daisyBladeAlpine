@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'center'         => false,
    'direction'      => 'vertical',
    'background'     => 'base-200',   // base-100 | base-200 | base-300 | primary | secondary | accent | neutral | info | success | warning | error
])

@php
// The background is a token; the class names are written out in full so Tailwind can find them.
// A value that is not listed gets the default look.
$backgroundClass = match($background) {
    'base-100' => 'bg-base-100 text-base-content',
    'base-300' => 'bg-base-300 text-base-content',
    'neutral' => 'bg-neutral text-neutral-content',
    'primary' => 'bg-primary text-primary-content',
    'secondary' => 'bg-secondary text-secondary-content',
    'accent' => 'bg-accent text-accent-content',
    'info' => 'bg-info text-info-content',
    'success' => 'bg-success text-success-content',
    'warning' => 'bg-warning text-warning-content',
    'error' => 'bg-error text-error-content',
    default => 'bg-base-200 text-base-content',
};
$footerClasses = trim(implode(' ', array_filter([
    'footer', $backgroundClass,
    $center ? 'footer-center' : '',
    $direction === 'horizontal' ? 'footer-horizontal' : 'footer-vertical',
    'p-10',
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
