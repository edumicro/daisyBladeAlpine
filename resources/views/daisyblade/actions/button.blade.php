@props([
    'label'        => 'Button',
    'type'         => 'button',
    'variant'      => 'btn-primary',
    'size'         => 'btn-md',
    'disabled'     => false,
    'loading'      => false,
    'icon'         => '',
    'iconPosition' => 'left',
    'iconOnly'     => false,
    'href'         => '',
    'class'        => '',
])

@if($href)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => "btn {$variant} {$size} {$class}" . ($iconOnly ? ' btn-circle' : '')]) }}
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
        {{ $attributes->merge(['class' => "btn {$variant} {$size} {$class}" . ($iconOnly ? ' btn-circle' : '') . ($loading ? ' btn-loading' : '')]) }}
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
