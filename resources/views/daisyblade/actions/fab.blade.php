@props([
    'icon'     => 'heroicon-o-plus',
    'label'    => '',
    'tooltip'  => '',
    'href'     => '',
    'variant'  => 'btn-primary',
    'size'     => 'btn-lg',
    'position' => 'bottom-right',
    'disabled' => false,
    'class'    => '',
])

@php
$posClass = match($position) {
    'bottom-left'  => 'bottom-6 left-6',
    'top-right'    => 'top-6 right-6',
    'top-left'     => 'top-6 left-6',
    default        => 'bottom-6 right-6',
};
@endphp

<div
    x-data="{ menuOpen: false }"
    class="fixed {{ $posClass }} z-40"
>
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
                {{ $attributes->merge(['class' => "btn btn-circle {$variant} {$size} shadow-lg hover:shadow-xl transition-all duration-200 {$class}"]) }}
            >
                <x-dynamic-component :component="$icon" class="w-6 h-6" />
                @if($label) <span class="ml-2">{{ __($label) }}</span> @endif
            </a>
        @else
            <button
                type="button"
                @if($slot->isNotEmpty()) @click="menuOpen = !menuOpen" @endif
                @disabled($disabled)
                {{ $attributes->merge(['class' => "btn btn-circle {$variant} {$size} shadow-lg hover:shadow-xl transition-all duration-200 {$class}"]) }}
            >
                <x-dynamic-component :component="$icon" class="w-6 h-6" />
                @if($label) <span class="ml-2">{{ __($label) }}</span> @endif
            </button>
        @endif
    </div>
</div>
