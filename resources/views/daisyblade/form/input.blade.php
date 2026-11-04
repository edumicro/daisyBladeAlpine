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
    'wrapperClass'   => '',
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
// The id that links the label to the control: the caller's, if it wrote one; else the name.
$controlId = $attributes->get('id', $name ?: null);
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, min, max, step,
     autocomplete, x-model, wire:model, data-*...) goes to it, as in Breeze's text-input. The box around it (label,
     hint, error) takes its classes from `wrapper-class`. --}}
<div class="w-full {{ $wrapperClass }}">
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($controlId) for="{{ $controlId }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    <div class="relative flex items-center">
        @if($icon && $iconSide === 'left')
            <div class="absolute left-0 pl-3 flex items-center pointer-events-none text-base-content/50">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </div>
        @endif

        <input
            {{ $attributes->merge([
                'type' => $type,
                'placeholder' => $placeholder,
                'value' => $value,
                'name' => $name ?: null,
                'disabled' => $disabled ?: null,
                'readonly' => $readonly ?: null,
                'required' => $required ?: null,
                'id' => $controlId,
                'class' => trim('input w-full ' . $sizeClass . ' '
                    . ($icon && $iconSide === 'left' ? 'pl-10 ' : '')
                    . ($icon && $iconSide === 'right' ? 'pr-10 ' : '')
                    . ($error ? 'input-error' : ($name && isset($errors) && $errors->has($name) ? 'input-error' : ''))),
            ]) }}
        />

        @if($icon && $iconSide === 'right')
            <div class="absolute right-0 pr-3 flex items-center pointer-events-none text-base-content/50">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </div>
        @endif
    </div>

    <x-dbl::form.field-error :name="$name" :message="$error" />

    @if($hint)
        <div class="mt-1">
            <span class="text-xs text-base-content/60">{{ $hint }}</span>
        </div>
    @endif
</div>
