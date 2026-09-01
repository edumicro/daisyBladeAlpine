@props([
    'name'           => '',
    'label'          => '',
    'type'           => 'text',
    'placeholder'    => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'disabled'       => false,
    'readonly'       => false,
    'required'       => false,
    'error'          => '',
    'hint'           => '',
    'description'    => '',
    'class'          => '',
    'containerClass' => '',
])

<div class="w-full {{ $containerClass }}">
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($name) for="{{ $name }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    @if($description)
        <p class="text-sm text-base-content/70 mb-1">{{ $description }}</p>
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
            @if($name) id="{{ $name }}" name="{{ $name }}" @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim('input w-full '
                . ($icon && $iconSide === 'left' ? 'pl-10 ' : '')
                . ($icon && $iconSide === 'right' ? 'pr-10 ' : '')
                . ($error ? 'input-error ' : ($name && isset($errors) && $errors->has($name) ? 'input-error ' : ''))
                . $class)]) }}
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
