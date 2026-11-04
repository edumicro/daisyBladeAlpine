@props([
    'shape' => 'squircle',
    'half'  => '',
    'src'   => '',
    'alt'   => '',
])

@php
$maskClass = 'mask mask-' . $shape . ($half ? ' mask-' . $half : '');
@endphp

@if($src)
    <img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => $maskClass]) }} />
@else
    <div {{ $attributes->merge(['class' => $maskClass]) }}>
        {{ $slot }}
    </div>
@endif
