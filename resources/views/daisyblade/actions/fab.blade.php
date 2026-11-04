@props([
    'icon'     => 'heroicon-o-plus',
    'label'    => '',
    'tooltip'  => '',
    'href'     => '',
    'color'    => 'primary',   // primary | secondary | accent | neutral | info | success | warning | error | ghost | link
    'size'     => 'lg',   // xs | sm | md | lg | xl
    'position' => 'bottom-right',
    'disabled' => false,
    'buttonClass' => '',
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
$posClass = match($position) {
    'bottom-left'  => 'bottom-6 left-6',
    'top-right'    => 'top-6 right-6',
    'top-left'     => 'top-6 left-6',
    default        => 'bottom-6 right-6',
};
@endphp

<div {{ $attributes->merge(['class' => "fixed {$posClass} z-40"]) }}>
    <div x-data="{ menuOpen: false }" class="contents">
        {{-- Slot actions (speed-dial menu) --}}
        @if($slot->isNotEmpty())
            <div x-show="menuOpen" x-cloak class="absolute bottom-full pb-3 flex flex-col items-center gap-2">
                {{ $slot }}
            </div>
        @endif

        <div class="tooltip tooltip-left" @if($tooltip) data-tip="{{ __($tooltip) }}" @endif>
            @if($href)
                <a
                    href="{{ $href }}"
                    class="btn btn-circle {{ $colorClass }} {{ $sizeClass }} shadow-lg hover:shadow-xl transition-all duration-200 {{ $buttonClass }}"
                >
                    <x-dynamic-component :component="$icon" class="w-6 h-6" />
                    @if($label) <span class="ml-2">{{ __($label) }}</span> @endif
                </a>
            @else
                <button
                    type="button"
                    @if($slot->isNotEmpty()) @click="menuOpen = !menuOpen" @endif
                    @disabled($disabled)
                    class="btn btn-circle {{ $colorClass }} {{ $sizeClass }} shadow-lg hover:shadow-xl transition-all duration-200 {{ $buttonClass }}"
                >
                    <x-dynamic-component :component="$icon" class="w-6 h-6" />
                    @if($label) <span class="ml-2">{{ __($label) }}</span> @endif
                </button>
            @endif
        </div>
    </div>
</div>
