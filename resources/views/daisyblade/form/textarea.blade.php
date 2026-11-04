@props([
    'name'           => '',
    'label'          => '',
    'placeholder'    => '',
    'rows'           => 4,
    'size'           => 'md',   // xs | sm | md | lg | xl
    'maxLength'      => 0,
    'disabled'       => false,
    'readonly'       => false,
    'required'       => false,
    'hint'           => '',
    'wrapperClass'   => '',
    'value'          => null,
])

@php
// The size is a token; the class name is written out in full so Tailwind can find it.
$sizeClass = match($size) {
    'xs' => 'textarea-xs',
    'sm' => 'textarea-sm',
    'lg' => 'textarea-lg',
    'xl' => 'textarea-xl',
    default => 'textarea-md',
};
// The id that links the label to the control: the caller's, if it wrote one; else the name.
$controlId = $attributes->get('id', $name ?: null);
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, min, max, step,
     autocomplete, x-model, wire:model, data-*...) goes to it, as in Breeze's text-input. The box around it (label,
     hint, error) takes its classes from `wrapper-class`. --}}
{{-- With `max-length` the counter (dblCharacterCounter, in the label) counts what is typed and follows the model. It is its own
     little scope BESIDE the textarea, never around it: a scope around the textarea would hide the `x-ref` and the data of the caller. --}}
<div class="w-full {{ $wrapperClass }}" data-dbl-textarea-box>
    @if($label)
        {{-- Texto y contador en la misma linea: `flex justify-between` explicito, porque la
             clase `.label` de DaisyUI 4 que lo hacia ya no existe en la 5. --}}
        <label class="mb-1 flex items-center justify-between gap-2 text-sm font-medium"
               @if($controlId) for="{{ $controlId }}" @endif>
            <span>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
            @if($maxLength > 0) <span class="text-xs font-normal text-base-content/60"><span x-data="dblCharacterCounter(@js(['start' => mb_strlen((string) $value)]))" x-effect="count()" x-text="typed">{{ mb_strlen((string) $value) }}</span>/{{ $maxLength }}</span> @endif
        </label>
    @endif

    <textarea
        placeholder="{{ $placeholder }}"
        rows="{{ $rows }}"
        @if($name) name="{{ $name }}" @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($required) required @endif
        @if($maxLength > 0) maxlength="{{ $maxLength }}" @endif
        {{ $attributes->merge(['id' => $controlId, 'class' => trim('textarea w-full ' . $sizeClass . ' '
            . ($name && isset($errors) && $errors->has($name) ? 'textarea-error' : ''))]) }}
    >{{ $value }}</textarea>

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif

    @if($hint)
        <p class="mt-1 text-xs text-base-content/60">{{ $hint }}</p>
    @endif
</div>
