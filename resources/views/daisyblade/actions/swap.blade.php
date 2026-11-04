@props([
    'offIcon'   => 'heroicon-o-moon',
    'onIcon'    => 'heroicon-o-sun',
    'offLabel'  => '',
    'onLabel'   => '',
    'animation' => 'rotate',   // rotate | flip | bounce
    'color'     => 'ghost',   // primary | secondary | accent | neutral | info | success | warning | error | ghost | link
    'size'      => 'md',   // xs | sm | md | lg | xl
    'disabled'  => false,
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
    default => 'btn-ghost',
};

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

{{-- Everything the caller writes goes to the root; the binding of the value (x-model, wire:model) goes to the checkbox. --}}
<label {{ $attributes->whereDoesntStartWith(['x-model', 'wire:model'])->merge(['class' => "swap swap-animate {$animClass}"]) }}>
    <input type="checkbox" @disabled($disabled) {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }} />

    <div class="swap-off">
        <button type="button" @disabled($disabled) class="btn {{ $colorClass }} {{ $sizeClass }} {{ $buttonClass }}">
            @if($offIcon)
                <x-dynamic-component :component="$offIcon" class="w-5 h-5" />
            @endif
            @if($offLabel)
                <span class="ml-1">{{ __($offLabel) }}</span>
            @endif
        </button>
    </div>

    <div class="swap-on">
        <button type="button" @disabled($disabled) class="btn {{ $colorClass }} {{ $sizeClass }} {{ $buttonClass }}">
            @if($onIcon)
                <x-dynamic-component :component="$onIcon" class="w-5 h-5" />
            @endif
            @if($onLabel)
                <span class="ml-1">{{ __($onLabel) }}</span>
            @endif
        </button>
    </div>
</label>
