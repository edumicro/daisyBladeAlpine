@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'value'          => null,
    'disabled'       => false,
    'required'       => false,
    'color'          => 'primary',
    'inline'         => false,
    'class'          => '',
    'containerClass' => '',
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
@endphp

<div class="w-full {{ $containerClass }}">
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
                    {{-- `required` goes to every radio of the group: the browser asks for one of them to be chosen. --}}
                    @if($required) required @endif
                    {{-- The `id` the caller writes goes only to the FIRST radio: the same id on all of them is invalid HTML
                         and getElementById or aria-describedby would only ever reach the first one anyway. --}}
                    {{ ($loop->first ? $attributes : $attributes->except('id'))->merge(['class' => trim("radio {$colorClass} " . $class)]) }}
                />
                <span class="text-sm">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
