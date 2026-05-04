@props([
    'offIcon'   => 'heroicon-o-moon',
    'onIcon'    => 'heroicon-o-sun',
    'offLabel'  => '',
    'onLabel'   => '',
    'animation' => 'rotate',   // rotate | flip | bounce
    'variant'   => 'btn-ghost',
    'size'      => 'btn-md',
    'disabled'  => false,
    'class'     => '',
])

@php
$animClass = match($animation) {
    'flip'   => 'swap-flip',
    'bounce' => 'swap-bounce',
    default  => 'swap-rotate',
};
@endphp

<label class="swap swap-animate {{ $animClass }}">
    <input type="checkbox" @disabled($disabled) {{ $attributes->whereStartsWith('x-model') }} />

    <div class="swap-off">
        <button type="button" @disabled($disabled) class="btn {{ $variant }} {{ $size }} {{ $class }}">
            @if($offIcon)
                <x-dynamic-component :component="$offIcon" class="w-5 h-5" />
            @endif
            @if($offLabel)
                <span class="ml-1">{{ __($offLabel) }}</span>
            @endif
        </button>
    </div>

    <div class="swap-on">
        <button type="button" @disabled($disabled) class="btn {{ $variant }} {{ $size }} {{ $class }}">
            @if($onIcon)
                <x-dynamic-component :component="$onIcon" class="w-5 h-5" />
            @endif
            @if($onLabel)
                <span class="ml-1">{{ __($onLabel) }}</span>
            @endif
        </button>
    </div>
</label>
