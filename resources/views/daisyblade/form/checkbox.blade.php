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

    <label class="flex w-full cursor-pointer items-center justify-start gap-3 py-1">
        <input
            type="checkbox"
            @if($name) id="{{ $name }}" name="{{ $name }}" value="1" @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim("checkbox checkbox-{$color} " . $class)]) }}
        />
        @if($label)
            <span class="text-sm font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        @endif
    </label>

    @if($description)
        <p class="text-sm text-base-content/60 ml-8 -mt-1">{{ $description }}</p>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif
</div>
