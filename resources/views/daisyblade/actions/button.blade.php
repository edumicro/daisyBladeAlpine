@props([
    'label'        => 'Button',
    'type'         => 'button',
    'color'        => 'primary',   // primary | secondary | accent | neutral | info | success | warning | error | ghost | link
    'size'         => 'md',   // xs | sm | md | lg | xl
    'disabled'     => false,
    'loading'      => false,
    'icon'         => '',
    'iconPosition' => 'left',
    'iconOnly'     => false,
    'href'         => '',
])

@php
// The color is a token; the class name is written out in full so Tailwind can find it.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'btn-neutral',
    'primary' => 'btn-primary',
    'secondary' => 'btn-secondary',
    'accent' => 'btn-accent',
    'info' => 'btn-info',
    'success' => 'btn-success',
    'warning' => 'btn-warning',
    'error' => 'btn-error',
    'ghost' => 'btn-ghost',
    'link' => 'btn-link',
    default => 'btn-primary',
};

// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'btn-xs',
    'sm' => 'btn-sm',
    'lg' => 'btn-lg',
    'xl' => 'btn-xl',
    default => 'btn-md',
};
@endphp

@if($href)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => "btn {$colorClass} {$sizeClass}" . ($iconOnly ? ' btn-circle' : '')]) }}
    >
        @if($icon && $iconPosition === 'left' && !$iconOnly)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @elseif($icon && $iconOnly)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @endif
        @if(!$iconOnly) <span>{{ $label }}</span>{{ $slot }} @endif
        @if($icon && $iconPosition === 'right' && !$iconOnly)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled || $loading)
        {{ $attributes->merge(['class' => "btn {$colorClass} {$sizeClass}" . ($iconOnly ? ' btn-circle' : '') . ($loading ? ' btn-loading' : '')]) }}
    >
        @if($loading)
            <span class="loading loading-spinner loading-sm"></span>
        @elseif($icon && $iconPosition === 'left' && !$iconOnly)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @elseif($icon && $iconOnly)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @endif

        @if(!$iconOnly)
            <span>{{ $label }}</span>
            {{ $slot }}
        @endif

        @if($icon && $iconPosition === 'right' && !$iconOnly && !$loading)
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        @endif
    </button>
@endif
