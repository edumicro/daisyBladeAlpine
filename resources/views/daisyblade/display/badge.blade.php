@props([
    'label'   => '',
    'text'    => '',
    'color'   => '',
    'variant' => 'primary',
    'size'    => 'md',
    'icon'    => '',
    'iconSide'=> 'left',
    'outline' => false,
    'ghost'   => false,
    'class'   => '',
])

@php
$resolvedVariant = $color ?: $variant;
$variantClass = $ghost ? 'badge-ghost' : (($outline ? 'badge-outline ' : '') . "badge-{$resolvedVariant}");
$sizeClass    = match($size) { 'xs' => 'badge-xs', 'sm' => 'badge-sm', 'lg' => 'badge-lg', default => '' };
@endphp

<span {{ $attributes->merge(['class' => "badge {$variantClass} {$sizeClass} gap-1 {$class}"]) }}>
    @if($icon && $iconSide === 'left')
        <x-dynamic-component :component="$icon" class="w-3 h-3" />
    @endif

    {{ $text ?: $label }}
    {{ $slot }}

    @if($icon && $iconSide === 'right')
        <x-dynamic-component :component="$icon" class="w-3 h-3" />
    @endif
</span>
