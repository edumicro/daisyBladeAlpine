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

<div class="form-control w-full {{ $containerClass }}">
    @if($name && isset($errors))
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif

    <label class="label cursor-pointer justify-start gap-3 py-1">
        <input
            type="checkbox"
            @if($name) id="{{ $name }}" name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim("checkbox checkbox-{$color} " . $class)]) }}
        />
        @if($label)
            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        @endif
    </label>

    @if($description)
        <p class="text-sm text-base-content/60 ml-8 -mt-1">{{ $description }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
