@props([
    'src'      => '',
    'alt'      => 'Avatar',
    'size'     => 'md',   // xs | sm | md | lg | xl | 2xl
    'online'   => false,
    'offline'  => false,
    'initials' => '',
    'icon'     => '',
    'color'    => 'neutral',
    'label'    => '',
    'class'    => '',
])

@php
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'bg-neutral',
    'primary' => 'bg-primary',
    'secondary' => 'bg-secondary',
    'accent' => 'bg-accent',
    'info' => 'bg-info',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'error' => 'bg-error',
    'base-100' => 'bg-base-100',
    'base-200' => 'bg-base-200',
    'base-300' => 'bg-base-300',
    default => 'bg-neutral',
};
$sizeClass = match($size) {
    'xs'  => 'w-6 h-6',
    'sm'  => 'w-8 h-8',
    'lg'  => 'w-16 h-16',
    'xl'  => 'w-20 h-20',
    '2xl' => 'w-24 h-24',
    default => 'w-10 h-10',   // md
};
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    @if($label)
        <span class="text-sm font-medium">{{ $label }}</span>
    @endif

    <div @class(['avatar', 'avatar-online' => $online, 'avatar-offline' => $offline, $class => $class])>
        <div class="{{ $sizeClass }} rounded-full">
            @if($src)
                <img src="{{ $src }}" alt="{{ $alt }}" class="rounded-full object-cover w-full h-full" />
            @elseif($initials)
                <div class="{{ $colorClass }} w-full h-full rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-sm">{{ $initials }}</span>
                </div>
            @elseif($icon)
                <div class="{{ $colorClass }} w-full h-full rounded-full flex items-center justify-center">
                    <x-dynamic-component :component="$icon" class="w-1/2 h-1/2 text-white" />
                </div>
            @else
                <div class="{{ $colorClass }} w-full h-full rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-sm">?</span>
                </div>
            @endif
        </div>
    </div>
</div>
