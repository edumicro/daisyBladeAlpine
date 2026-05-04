@props([
    'label'        => 'Button',
    'type'         => 'button',
    'variant'      => 'btn-primary',
    'size'         => 'btn-md',
    'disabled'     => false,
    'icon'         => '',
    'iconPosition' => 'left',
    'iconOnly'     => false,
    'class'        => '',
])

<button
    type="{{ $type }}"
    @disabled($disabled)
    {{ $attributes->merge(['class' => "btn {$variant} {$size} {$class}" . ($iconOnly ? ' btn-circle' : '')]) }}
>
    @if($icon && $iconPosition === 'left' && !$iconOnly)
        <x-dynamic-component :component="$icon" class="w-5 h-5" />
    @elseif($icon && $iconOnly)
        <x-dynamic-component :component="$icon" class="w-5 h-5" />
    @endif

    @if(!$iconOnly)
        <span>{{ $label }}</span>
        {{ $slot }}
    @endif

    @if($icon && $iconPosition === 'right' && !$iconOnly)
        <x-dynamic-component :component="$icon" class="w-5 h-5" />
    @endif
</button>
