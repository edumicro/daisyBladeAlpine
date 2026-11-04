@props([
    'label'        => 'Button',
    'type'         => 'button',
    'variant'      => 'btn-primary',
    'size'         => 'md',   // xs | sm | md | lg | xl
    'disabled'     => false,
    'loading'      => false,
    'icon'         => '',
    'iconPosition' => 'left',
    'iconOnly'     => false,
    'href'         => '',
    'class'        => '',
])

@php
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
        {{ $attributes->merge(['class' => "btn {$variant} {$sizeClass} {$class}" . ($iconOnly ? ' btn-circle' : '')]) }}
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
        {{ $attributes->merge(['class' => "btn {$variant} {$sizeClass} {$class}" . ($iconOnly ? ' btn-circle' : '') . ($loading ? ' btn-loading' : '')]) }}
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
