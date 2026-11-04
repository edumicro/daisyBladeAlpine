@props([
    'name'           => '',
    'label'          => '',
    'value'          => false,
    'hint'           => '',
    'disabled'       => false,
    'required'       => false,
    'color'          => 'primary',
    'size'           => 'md',   // xs | sm | md | lg | xl
    'toggleClass'    => '',
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

{{-- Everything the caller writes (class, id, data-*, aria-*...) goes to the root, as in any Laravel component.
     The one exception is the binding of the value (x-model, wire:model): it only means something on the control. --}}
<div {{ $attributes->whereDoesntStartWith(['x-model', 'wire:model'])->merge(['class' => 'w-full']) }}>
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
            {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }}
            class="{{ trim("toggle {$colorClass} {$sizeClass} " . $toggleClass) }}"
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
