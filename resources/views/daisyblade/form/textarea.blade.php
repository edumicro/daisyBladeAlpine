@props([
    'name'           => '',
    'label'          => '',
    'placeholder'    => '',
    'rows'           => 4,
    'maxLength'      => 0,
    'disabled'       => false,
    'readonly'       => false,
    'required'       => false,
    'description'    => '',
    'class'          => '',
    'containerClass' => '',
])

<div class="w-full {{ $containerClass }}">
    @if($label)
        {{-- Texto y contador en la misma linea: `flex justify-between` explicito, porque la
             clase `.label` de DaisyUI 4 que lo hacia ya no existe en la 5. --}}
        <label class="mb-1 flex items-center justify-between gap-2 text-sm font-medium"
               @if($name) for="{{ $name }}" @endif>
            <span>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
            @if($maxLength > 0) <span class="text-xs font-normal text-base-content/60">0/{{ $maxLength }}</span> @endif
        </label>
    @endif

    @if($description)
        <p class="text-sm text-base-content/70 mb-1">{{ $description }}</p>
    @endif

    <textarea
        placeholder="{{ $placeholder }}"
        rows="{{ $rows }}"
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($required) required @endif
        @if($maxLength > 0) maxlength="{{ $maxLength }}" @endif
        {{ $attributes->merge(['class' => trim('textarea w-full '
            . ($name && isset($errors) && $errors->has($name) ? 'textarea-error ' : '')
            . $class)]) }}
    ></textarea>

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</div>
        @enderror
    @endif
</div>
