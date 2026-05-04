@props([
    'message'        => '',
    'type'           => 'spinner',
    'size'           => 'md',
    'color'          => 'primary',
    'fullScreen'     => false,
    'showMessage'    => true,
    'class'          => '',
    'containerClass' => '',
])

@php
$sizeClass  = match($size) { 'sm'=>'loading-sm','lg'=>'loading-lg', default=>'loading-md' };
$colorClass = "text-{$color}";
$typeClass  = match($type) { 'dots'=>'loading-dots','bars'=>'loading-bars','ring'=>'loading-ring','ball'=>'loading-ball', default=>'loading-spinner' };
@endphp

<div class="{{ $fullScreen ? 'fixed inset-0 z-50 bg-base-content/20 backdrop-blur-sm' : '' }} flex items-center justify-center {{ $containerClass }}">
    <div class="flex flex-col items-center gap-4 {{ !$fullScreen ? 'p-6' : '' }} {{ $class }}">
        <div class="loading {{ $typeClass }} {{ $sizeClass }} {{ $colorClass }}"></div>

        @if($showMessage && $message)
            <p class="text-center text-sm font-medium {{ $fullScreen ? 'text-base-content' : 'text-base-content/70' }}">
                {{ $message }}
            </p>
        @endif
    </div>
</div>
