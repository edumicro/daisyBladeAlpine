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
    <img {{ $attributes->merge(['src' => $src, 'alt' => $alt, 'class' => $maskClass]) }} />
@else
    <div {{ $attributes->merge(['class' => $maskClass]) }}>
        {{ $slot }}
    </div>
@endif
