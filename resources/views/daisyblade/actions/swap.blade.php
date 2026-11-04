@props([
    'offIcon'   => 'heroicon-o-moon',
    'onIcon'    => 'heroicon-o-sun',
    'offLabel'  => '',
    'onLabel'   => '',
    'animation' => 'rotate',   // rotate | flip | bounce
    'variant'   => 'btn-ghost',
    'size'      => 'md',   // xs | sm | md | lg | xl
    'disabled'  => false,
    'class'     => '',
])

@php
// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'btn-xs',
    'sm' => 'btn-sm',
    'lg' => 'btn-lg',
    'xl' => 'btn-xl',
    default => 'btn-md',
};
$animClass = match($animation) {
    'flip'   => 'swap-flip',
    'bounce' => 'swap-bounce',
    default  => 'swap-rotate',
};
@endphp

<label class="swap swap-animate {{ $animClass }}">
    <input type="checkbox" @disabled($disabled) {{ $attributes->whereStartsWith('x-model') }} />

    <div class="swap-off">
        <button type="button" @disabled($disabled) class="btn {{ $variant }} {{ $sizeClass }} {{ $class }}">
            @if($offIcon)
                <x-dynamic-component :component="$offIcon" class="w-5 h-5" />
            @endif
            @if($offLabel)
                <span class="ml-1">{{ __($offLabel) }}</span>
            @endif
        </button>
    </div>

    <div class="swap-on">
        <button type="button" @disabled($disabled) class="btn {{ $variant }} {{ $sizeClass }} {{ $class }}">
            @if($onIcon)
                <x-dynamic-component :component="$onIcon" class="w-5 h-5" />
            @endif
            @if($onLabel)
                <span class="ml-1">{{ __($onLabel) }}</span>
            @endif
        </button>
    </div>
</label>
