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

<div class="form-control w-full {{ $containerClass }}">
    @if($label)
        <label class="label">
            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        </label>
    @endif

    <div class="{{ $inline ? 'flex flex-wrap gap-4' : 'space-y-1' }}">
        @foreach($options as $optVal => $optLabel)
            <label class="label cursor-pointer justify-start gap-3 py-1">
                <input
                    type="radio"
                    value="{{ $optVal }}"
                    @if($name) name="{{ $name }}" @endif
                    @if($value !== null && (string)$value === (string)$optVal) checked @endif
                    @if($disabled) disabled @endif
                    {{ $attributes->merge(['class' => trim("radio radio-{$color} " . $class)]) }}
                />
                <span class="label-text">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
