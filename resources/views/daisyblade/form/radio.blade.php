@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'value'          => null,
    'hint'           => '',
    'disabled'       => false,
    'required'       => false,
    'color'          => 'primary',
    'size'           => 'md',   // xs | sm | md | lg | xl
    'inline'         => false,
    'wrapperClass'   => '',
])

@php
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'radio-neutral',
    'primary' => 'radio-primary',
    'secondary' => 'radio-secondary',
    'accent' => 'radio-accent',
    'info' => 'radio-info',
    'success' => 'radio-success',
    'warning' => 'radio-warning',
    'error' => 'radio-error',
    default => 'radio-primary',
};

// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'radio-xs',
    'sm' => 'radio-sm',
    'lg' => 'radio-lg',
    'xl' => 'radio-xl',
    default => 'radio-md',
};
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, min, max, step,
     autocomplete, x-model, wire:model, data-*...) goes to it, as in Breeze's text-input. The box around it (label,
     hint, error) takes its classes from `wrapper-class`. --}}
<div class="w-full {{ $wrapperClass }}">
    @if($label)
        <label class="mb-1 block text-sm font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    <div class="{{ $inline ? 'flex flex-wrap gap-4' : 'space-y-1' }}">
        @foreach($options as $optVal => $optLabel)
            <label class="flex w-full cursor-pointer items-center justify-start gap-3 py-1">
                <input
                    type="radio"
                    value="{{ $optVal }}"
                    @if($name) name="{{ $name }}" @endif
                    @if($value !== null && (string)$value === (string)$optVal) checked @endif
                    @if($disabled) disabled @endif
                    @if($required) required @endif
                    {{ $attributes->merge(['class' => "radio {$colorClass} {$sizeClass}"]) }}
                />
                <span class="text-sm">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if($hint)
        <p class="mt-1 text-xs text-base-content/60">{{ $hint }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
