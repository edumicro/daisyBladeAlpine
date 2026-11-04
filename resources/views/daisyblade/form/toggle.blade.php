@props([
    'name'           => '',
    'label'          => '',
    'description'    => '',
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
@endphp

<div class="w-full {{ $containerClass }}">
    @if($name && isset($errors))
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif

    <label class="flex w-full cursor-pointer items-center justify-between">
        @if($label)
            <span class="text-sm font-medium">{{ $label }}</span>
        @endif
        <input
            type="checkbox"
            {{-- The `id` is the one the caller writes or, if none, the `name` (written once: the `id` is taken out of $attributes below). --}}
            @if($attributes->get('id', $name)) id="{{ $attributes->get('id', $name) }}" @endif
            @if($name) name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            {{-- `required` must reach the <input>, or the browser lets the form go with the toggle off. --}}
            @if($required) required @endif
            {{ $attributes->except('id')->merge(['class' => trim("toggle {$colorClass} " . $class)]) }}
        />
    </label>

    @if($description)
        <p class="text-sm text-base-content/60 mt-0.5">{{ $description }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
