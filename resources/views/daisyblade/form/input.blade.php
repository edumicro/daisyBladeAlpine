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

<div class="form-control w-full {{ $containerClass }}">
    @if($label)
        <label class="label" @if($name) for="{{ $name }}" @endif>
            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        </label>
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
            {{ $attributes->merge(['class' => trim('input input-bordered w-full '
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
        <label class="label p-0 mt-1">
            <span class="label-text-alt text-error font-semibold">{{ $error }}</span>
        </label>
    @elseif($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif

    @if($hint)
        <label class="label p-0 mt-1">
            <span class="label-text-alt text-base-content/60">{{ $hint }}</span>
        </label>
    @endif
</div>
