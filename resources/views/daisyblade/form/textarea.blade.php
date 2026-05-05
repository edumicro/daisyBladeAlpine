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

<div class="form-control w-full {{ $containerClass }}">
    @if($label)
        <label class="label" @if($name) for="{{ $name }}" @endif>
            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
            @if($maxLength > 0) <span class="label-text-alt text-xs">0/{{ $maxLength }}</span> @endif
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
        {{ $attributes->merge(['class' => trim('textarea textarea-bordered w-full '
            . ($name && isset($errors) && $errors->has($name) ? 'textarea-error ' : '')
            . $class)]) }}
    ></textarea>

    @if($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
