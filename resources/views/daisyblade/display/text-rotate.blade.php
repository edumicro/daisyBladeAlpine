@props([
    'texts'      => [],
    'duration'   => 3000,
    'autoRotate' => true,
    'animation'  => 'fade',
    'size'       => 'lg',
    'color'      => 'primary',
    'bold'       => true,
    'label'      => '',
    'class'      => '',
])

@php
$sizeClass = match($size) { 'xs'=>'text-xs','sm'=>'text-sm','base'=>'text-base','xl'=>'text-xl','2xl'=>'text-2xl','3xl'=>'text-3xl','4xl'=>'text-4xl', default=>'text-lg' };
@endphp

<div
    x-data="{ current: 0, texts: @js($texts) }"
    @if($autoRotate)
    x-init="setInterval(() => current = (current + 1) % texts.length, {{ $duration }})"
    @endif
    {{ $attributes }}
>
    @if($label)
        <p class="text-sm font-medium text-base-content/70 text-center mb-4">{{ $label }}</p>
    @endif

    <div class="relative h-20 flex items-center justify-center">
        <template x-for="(text, i) in texts" :key="i">
            <div
                class="absolute inset-0 flex items-center justify-center transition-opacity duration-500"
                :class="{ 'opacity-100': i === current, 'opacity-0': i !== current }"
            >
                <span class="{{ $sizeClass }} {{ $bold ? 'font-bold' : '' }} text-{{ $color }} text-center px-4 {{ $class }}" x-text="text.text ?? text"></span>
            </div>
        </template>
    </div>

    <div class="flex justify-center gap-2 mt-4">
        <template x-for="(text, i) in texts" :key="i">
            <button
                type="button"
                @click="current = i"
                :class="i === current ? 'bg-{{ $color }} w-8' : 'bg-base-300 w-2'"
                class="h-2 rounded-full transition-all duration-300"
            ></button>
        </template>
    </div>
</div>
