@props([
    'shape' => 'squircle',
    'half'  => '',
    'src'   => '',
    'alt'   => '',
    'class' => '',
])

@php
$maskClass = 'mask mask-' . $shape . ($half ? ' mask-' . $half : '') . ' ' . $class;
@endphp

@if($src)
    <img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => $maskClass]) }} />
@else
    <div {{ $attributes->merge(['class' => $maskClass]) }}>
        {{ $slot }}
    </div>
@endif
