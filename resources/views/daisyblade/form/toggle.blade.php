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

    <label class="label cursor-pointer justify-between">
        @if($label)
            <span class="label-text font-medium">{{ $label }}</span>
        @endif
        <input
            type="checkbox"
            @if($name) name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            {{ $attributes->merge(['class' => trim("toggle toggle-{$color} " . $class)]) }}
        />
    </label>

    @if($description)
        <p class="text-sm text-base-content/60 mt-0.5">{{ $description }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
