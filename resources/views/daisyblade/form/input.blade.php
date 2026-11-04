@props([
    'name'           => '',
    'label'          => '',
    'type'           => 'text',
    'size'           => 'md',   // xs | sm | md | lg | xl
    'placeholder'    => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'disabled'       => false,
    'readonly'       => false,
    'required'       => false,
    'error'          => '',
    'value'          => null,
    'hint'           => '',
    'inputClass'     => '',
])

@php
// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'input-xs',
    'sm' => 'input-sm',
    'lg' => 'input-lg',
    'xl' => 'input-xl',
    default => 'input-md',
};
@endphp

{{-- Everything the caller writes (class, id, data-*, aria-*...) goes to the root, as in any Laravel component.
     The one exception is the binding of the value (x-model, wire:model): it only means something on the control. --}}
<div {{ $attributes->whereDoesntStartWith(['x-model', 'wire:model'])->merge(['class' => 'w-full']) }}>
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($name) for="{{ $name }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    <div class="relative flex items-center">
        @if($icon && $iconSide === 'left')
            <div class="absolute left-0 pl-3 flex items-center pointer-events-none text-base-content/50">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </div>
        @endif

        <input
            type="{{ $type }}"
            placeholder="{{ $placeholder }}"
            @if($value !== null) value="{{ $value }}" @endif
            @if($name) id="{{ $name }}" name="{{ $name }}" @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            @if($required) required @endif
            {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }}
            class="{{ trim('input w-full ' . $sizeClass . ' '
                . ($icon && $iconSide === 'left' ? 'pl-10 ' : '')
                . ($icon && $iconSide === 'right' ? 'pr-10 ' : '')
                . ($error ? 'input-error ' : ($name && isset($errors) && $errors->has($name) ? 'input-error ' : ''))
                . $inputClass) }}"
        />

        @if($icon && $iconSide === 'right')
            <div class="absolute right-0 pr-3 flex items-center pointer-events-none text-base-content/50">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </div>
        @endif
    </div>

    @if($error)
        <div class="mt-1">
            <span class="text-xs text-error font-semibold">{{ $error }}</span>
        </div>
    @elseif($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif

    @if($hint)
        <div class="mt-1">
            <span class="text-xs text-base-content/60">{{ $hint }}</span>
        </div>
    @endif
</div>
