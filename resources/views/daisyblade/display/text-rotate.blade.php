@props([
    'texts'      => [],
    'duration'   => 3000,
    'autoRotate' => true,
    'animation'  => 'fade',
    'size'       => 'lg',   // xs | sm | md | lg | xl | 2xl | 3xl | 4xl
    'color'      => 'primary',
    'bold'       => true,
    'label'      => '',
    'class'      => '',
])

@php
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$textColorClass = match($color) {
    'neutral' => 'text-neutral',
    'primary' => 'text-primary',
    'secondary' => 'text-secondary',
    'accent' => 'text-accent',
    'info' => 'text-info',
    'success' => 'text-success',
    'warning' => 'text-warning',
    'error' => 'text-error',
    'base-100' => 'text-base-100',
    'base-200' => 'text-base-200',
    'base-300' => 'text-base-300',
    default => 'text-primary',
};
$bgColorClass = match($color) {
    'neutral' => 'bg-neutral',
    'primary' => 'bg-primary',
    'secondary' => 'bg-secondary',
    'accent' => 'bg-accent',
    'info' => 'bg-info',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'error' => 'bg-error',
    'base-100' => 'bg-base-100',
    'base-200' => 'bg-base-200',
    'base-300' => 'bg-base-300',
    default => 'bg-primary',
};
$sizeClass = match($size) {
    'xs'  => 'text-xs',
    'sm'  => 'text-sm',
    'md'  => 'text-base',
    'xl'  => 'text-xl',
    '2xl' => 'text-2xl',
    '3xl' => 'text-3xl',
    '4xl' => 'text-4xl',
    default => 'text-lg',   // lg
};
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
                <span class="{{ $sizeClass }} {{ $bold ? 'font-bold' : '' }} {{ $textColorClass }} text-center px-4 {{ $class }}" x-text="text.text ?? text"></span>
            </div>
        </template>
    </div>

    <div class="flex justify-center gap-2 mt-4">
        <template x-for="(text, i) in texts" :key="i">
            <button
                type="button"
                @click="current = i"
                :class="i === current ? '{{ $bgColorClass }} w-8' : 'bg-base-300 w-2'"
                class="h-2 rounded-full transition-all duration-300"
            ></button>
        </template>
    </div>
</div>
