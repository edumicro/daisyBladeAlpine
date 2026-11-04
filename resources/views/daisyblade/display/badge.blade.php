@props([
    'label'   => '',
    'text'    => '',
    'color'   => 'primary',   // primary | secondary | accent | neutral | info | success | warning | error | ghost
    'size'    => 'md',   // xs | sm | md | lg | xl
    'icon'    => '',
    'iconSide'=> 'left',
    'outline' => false,
])

@php
// Class names written out in full, not glued together from a prefix and the value: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'badge-neutral',
    'primary' => 'badge-primary',
    'secondary' => 'badge-secondary',
    'accent' => 'badge-accent',
    'info' => 'badge-info',
    'success' => 'badge-success',
    'warning' => 'badge-warning',
    'error' => 'badge-error',
    'ghost' => 'badge-ghost',
    default => 'badge-primary',
};
$variantClass = ($outline ? 'badge-outline ' : '') . $colorClass;
$sizeClass    = match($size) {
    'xs' => 'badge-xs',
    'sm' => 'badge-sm',
    'lg' => 'badge-lg',
    'xl' => 'badge-xl',
    default => 'badge-md',
};
@endphp

<span {{ $attributes->merge(['class' => "badge {$variantClass} {$sizeClass} gap-1"]) }}>
    @if($icon && $iconSide === 'left')
        <x-dynamic-component :component="$icon" class="w-3 h-3" />
    @endif

    {{ $text ?: $label }}
    {{ $slot }}

    @if($icon && $iconSide === 'right')
        <x-dynamic-component :component="$icon" class="w-3 h-3" />
    @endif
</span>
