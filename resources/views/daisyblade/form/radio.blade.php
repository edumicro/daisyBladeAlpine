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
                    {{ $attributes->merge(['class' => trim("radio radio-{$color} " . $class)]) }}
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
