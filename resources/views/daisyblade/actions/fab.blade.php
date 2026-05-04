@props([
    'icon'     => 'heroicon-o-plus',
    'label'    => '',
    'tooltip'  => '',
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

<div class="fixed {{ $posClass }} z-40">
    <div class="tooltip tooltip-left" @if($tooltip) data-tip="{{ __($tooltip) }}" @endif>
        <button
            type="button"
            @disabled($disabled)
            {{ $attributes->merge(['class' => "btn btn-circle {$variant} {$size} shadow-lg hover:shadow-xl transition-all duration-200 {$class}"]) }}
        >
            <x-dynamic-component :component="$icon" class="w-6 h-6" />
            @if($label)
                <span class="ml-2">{{ __($label) }}</span>
            @endif
        </button>
    </div>
</div>
