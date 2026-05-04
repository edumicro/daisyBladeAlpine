@props([
    'label'        => '',
    'icon'         => '',
    'iconSide'     => 'left',
    'bgImage'      => '',
    'overlay'      => false,
    'overlayClass' => 'hero-overlay bg-opacity-60',
    'contentClass' => '',
    'minHeight'    => 'min-h-screen',
    'class'        => '',
    'containerClass' => '',
])

@php
$heroClasses    = trim("hero {$minHeight} {$containerClass}");
$contentClasses = trim("hero-content text-center {$contentClass} {$class}");
@endphp

<div
    {{ $attributes->merge(['class' => $heroClasses]) }}
    @if($bgImage) style="background-image: url({{ $bgImage }});" @endif
>
    @if($overlay && $bgImage)
        <div class="{{ $overlayClass }}"></div>
    @endif

    <div class="{{ $contentClasses }}">
        @if($label || $icon)
            <div class="flex items-center gap-2 justify-center mb-4">
                @if($icon && $iconSide === 'left')
                    <x-dynamic-component :component="$icon" class="h-8 w-8" />
                @endif
                @if($label)
                    <h1 class="text-5xl font-bold">{{ $label }}</h1>
                @endif
                @if($icon && $iconSide === 'right')
                    <x-dynamic-component :component="$icon" class="h-8 w-8" />
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
