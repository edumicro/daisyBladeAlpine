@props([
    'label'        => '',
    'icon'         => '',
    'iconSide'     => 'left',
    'bgImage'      => '',
    'overlay'      => false,
    'overlayClass' => 'hero-overlay bg-opacity-60',
    'contentClass' => '',
    'minHeight'    => 'min-h-screen',
])

@php
$heroClasses    = trim("hero {$minHeight}");
$contentClasses = trim("hero-content text-center {$contentClass}");
// The address goes in quotes inside the CSS function, and the quote and the backslash of the address are escaped for CSS: an address with a
// `)` or a `'` cannot get out of it. (The attribute around it is escaped for HTML by `merge`.)
$backgroundImage = $bgImage ? "background-image: url('" . addcslashes(str_replace(["\r", "\n"], '', $bgImage), "\\'") . "')" : null;
@endphp

<div {{ $attributes->merge(['class' => $heroClasses])->style($backgroundImage ? [$backgroundImage] : []) }}>
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
