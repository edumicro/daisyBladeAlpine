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

<div class="w-full {{ $containerClass }}">
    @if($name && isset($errors))
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif

    <label class="flex w-full cursor-pointer items-center justify-between">
        @if($label)
            <span class="text-sm font-medium">{{ $label }}</span>
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
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
