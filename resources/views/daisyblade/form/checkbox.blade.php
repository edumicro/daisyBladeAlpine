@props([
    'name'           => '',
    'label'          => '',
    'value'          => false,
    'hint'           => '',
    'disabled'       => false,
    'required'       => false,
    'color'          => 'primary',
    'class'          => '',
    'containerClass' => '',
])

@php
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'checkbox-neutral',
    'primary' => 'checkbox-primary',
    'secondary' => 'checkbox-secondary',
    'accent' => 'checkbox-accent',
    'info' => 'checkbox-info',
    'success' => 'checkbox-success',
    'warning' => 'checkbox-warning',
    'error' => 'checkbox-error',
    default => 'checkbox-primary',
};

// `value` is the current value of the control: checked when it is true, 1, '1', 'on'... and not when it is false, 0, '0', '' or null.
$checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="w-full {{ $containerClass }}">
    @if($name && isset($errors))
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif

    <label class="flex w-full cursor-pointer items-center justify-start gap-3 py-1">
        <input
            type="checkbox"
            @if($name) id="{{ $name }}" name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            @checked($checked)
            {{ $attributes->merge(['class' => trim("checkbox {$colorClass} " . $class)]) }}
        />
        @if($label)
            <span class="text-sm font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        @endif
    </label>

    @if($hint)
        <p class="text-sm text-base-content/60 ml-8 -mt-1">{{ $hint }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
