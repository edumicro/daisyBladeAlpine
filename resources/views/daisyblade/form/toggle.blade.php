@props([
    'name'           => '',
    'label'          => '',
    'value'          => false,
    'hint'           => '',
    'disabled'       => false,
    'required'       => false,
    'color'          => 'primary',
    'size'           => 'md',   // xs | sm | md | lg | xl
    'wrapperClass'   => '',
])

@php
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'toggle-neutral',
    'primary' => 'toggle-primary',
    'secondary' => 'toggle-secondary',
    'accent' => 'toggle-accent',
    'info' => 'toggle-info',
    'success' => 'toggle-success',
    'warning' => 'toggle-warning',
    'error' => 'toggle-error',
    default => 'toggle-primary',
};

// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'toggle-xs',
    'sm' => 'toggle-sm',
    'lg' => 'toggle-lg',
    'xl' => 'toggle-xl',
    default => 'toggle-md',
};

// `value` is the current value of the control: on when it is true, 1, '1', 'on'... and off when it is false, 0, '0', '' or null.
$checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, min, max, step,
     autocomplete, x-model, wire:model, data-*...) goes to it, as in Breeze's text-input. The box around it (label,
     hint, error) takes its classes from `wrapper-class`. --}}
<div class="w-full {{ $wrapperClass }}">
    @if($name && isset($errors))
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif

    <label class="flex w-full cursor-pointer items-center justify-between">
        @if($label)
            <span class="text-sm font-medium">{{ $label }}</span>
        @endif
        <input
            type="checkbox"
            @if($name) name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            @checked($checked)
            {{ $attributes->merge(['class' => "toggle {$colorClass} {$sizeClass}"]) }}
        />
    </label>

    @if($hint)
        <p class="text-sm text-base-content/60 mt-0.5">{{ $hint }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
