@props([
    'src'      => '',
    'alt'      => 'Avatar',
    'size'     => 'md',
    'online'   => false,
    'offline'  => false,
    'initials' => '',
    'icon'     => '',
    'color'    => 'neutral',
    'label'    => '',
    'class'    => '',
])

@php
$sizeClass = match($size) {
    'xs'  => 'w-6 h-6',
    'sm'  => 'w-8 h-8',
    'lg'  => 'w-16 h-16',
    'xl'  => 'w-20 h-20',
    '2xl' => 'w-24 h-24',
    default => 'w-10 h-10',
};
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    @if($label)
        <span class="text-sm font-medium">{{ $label }}</span>
    @endif

    <div @class(['avatar', 'online' => $online, 'offline' => $offline, $class => $class])>
        <div class="{{ $sizeClass }} rounded-full">
            @if($src)
                <img src="{{ $src }}" alt="{{ $alt }}" class="rounded-full object-cover w-full h-full" />
            @elseif($initials)
                <div class="bg-{{ $color }} w-full h-full rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-sm">{{ $initials }}</span>
                </div>
            @elseif($icon)
                <div class="bg-{{ $color }} w-full h-full rounded-full flex items-center justify-center">
                    <x-dynamic-component :component="$icon" class="w-1/2 h-1/2 text-white" />
                </div>
            @else
                <div class="bg-{{ $color }} w-full h-full rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-sm">?</span>
                </div>
            @endif
        </div>
    </div>
</div>
