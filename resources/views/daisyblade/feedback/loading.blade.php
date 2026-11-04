@props([
    'message'        => '',
    'type'           => 'spinner',
    'size'           => 'md',   // xs | sm | md | lg | xl
    'color'          => 'primary',
    'fullScreen'     => false,
    'showMessage'    => true,
    'contentClass'   => '',
])

@php
$sizeClass = match($size) {
    'xs' => 'loading-xs',
    'sm' => 'loading-sm',
    'lg' => 'loading-lg',
    'xl' => 'loading-xl',
    default => 'loading-md',
};
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
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
$typeClass  = match($type) { 'dots'=>'loading-dots','bars'=>'loading-bars','ring'=>'loading-ring','ball'=>'loading-ball', default=>'loading-spinner' };
@endphp

<div {{ $attributes->merge(['class' => trim(($fullScreen ? 'fixed inset-0 z-50 bg-base-content/20 backdrop-blur-sm ' : '') . 'flex items-center justify-center')]) }}>
    <div class="flex flex-col items-center gap-4 {{ !$fullScreen ? 'p-6' : '' }} {{ $contentClass }}">
        <div class="loading {{ $typeClass }} {{ $sizeClass }} {{ $colorClass }}"></div>

        @if($showMessage && $message)
            <p class="text-center text-sm font-medium {{ $fullScreen ? 'text-base-content' : 'text-base-content/70' }}">
                {{ $message }}
            </p>
        @endif
    </div>
</div>
