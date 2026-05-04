@props([
    'images'        => [],
    'columns'       => 3,
    'showCaptions'  => true,
    'showOverlay'   => true,
    'overlayEffect' => 'slide',  // fade | slide | scale
    'icon'          => 'heroicon-o-photo',
    'rounded'       => true,
    'shadow'        => true,
    'label'         => '',
    'class'         => '',
    'containerClass'=> '',
])

@php
$gridClass    = match($columns) { 1=>'grid-cols-1', 2=>'grid-cols-2', 4=>'grid-cols-4', default=>'grid-cols-3' };
$overlayAnim  = match($overlayEffect) { 'fade'=>'opacity-0 group-hover:opacity-100', 'scale'=>'scale-0 group-hover:scale-100', default=>'translate-y-full group-hover:translate-y-0' };
@endphp

@if($label)
    <div class="flex items-center gap-2 mb-6">
        @if($icon) <x-dynamic-component :component="$icon" class="w-6 h-6" /> @endif
        <h2 class="text-2xl font-bold">{{ $label }}</h2>
    </div>
@endif

<div class="grid {{ $gridClass }} gap-4 {{ $containerClass }}">
    @forelse($images as $index => $image)
        <div class="group relative h-64 overflow-hidden {{ $rounded ? 'rounded-lg' : '' }} {{ $shadow ? 'shadow-md hover:shadow-xl' : '' }} transition-all duration-300 {{ $class }}">
            <img src="{{ $image['src'] ?? '' }}" alt="{{ $image['alt'] ?? '' }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />

            @if($showOverlay)
                <div class="absolute inset-0 bg-black/60 transition-all duration-500 {{ $overlayAnim }} flex flex-col items-center justify-center p-4">
                    @if(isset($image['title']))
                        <h3 class="text-white font-bold text-lg mb-2 line-clamp-2">{{ $image['title'] }}</h3>
                    @endif
                    @if(isset($image['description']))
                        <p class="text-white/80 text-sm mb-4 line-clamp-2">{{ $image['description'] }}</p>
                    @endif
                    @if(isset($image['viewUrl']))
                        <a href="{{ $image['viewUrl'] }}" class="btn btn-sm btn-outline btn-light">{{ __('View') }}</a>
                    @endif
                </div>
            @endif

            @if($showCaptions && isset($image['caption']))
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                    <p class="text-white text-sm font-medium">{{ $image['caption'] }}</p>
                </div>
            @endif
        </div>
    @empty
        <div class="col-span-full py-12 text-center text-base-content/50">
            <x-dynamic-component :component="$icon" class="w-12 h-12 mx-auto mb-4 opacity-30" />
            {{ __('No items') }}
        </div>
    @endforelse
</div>
