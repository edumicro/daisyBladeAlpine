@props([
    'type'           => 'card',
    'lines'          => 3,
    'animated'       => true,
    'size'           => 'md',
    'width'          => 100,
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

@php
$pulse    = $animated ? 'animate-pulse' : '';
$sizeH    = match($size) { 'xs'=>'h-2','sm'=>'h-3','lg'=>'h-5','xl'=>'h-6', default=>'h-4' };
$gridCols = $width < 50 ? 2 : ($width < 75 ? 3 : 4);
@endphp

<div {{ $attributes->merge(['class' => $containerClass]) }}>
    @if($label)
        <div class="mb-4">
            <div class="skeleton rounded w-1/3 h-6 {{ $pulse }}"></div>
        </div>
    @endif

    @switch($type)
        @case('card')
            <div class="space-y-4">
                <div class="skeleton rounded-lg w-full h-48 {{ $pulse }} {{ $class }}"></div>
                <div class="space-y-3">
                    <div class="skeleton rounded w-3/4 h-4 {{ $pulse }}"></div>
                    <div class="skeleton rounded w-full h-4 {{ $pulse }}"></div>
                    <div class="skeleton rounded w-full h-4 {{ $pulse }}"></div>
                    <div class="skeleton rounded w-1/2 h-4 {{ $pulse }}"></div>
                </div>
                <div class="flex gap-2 pt-4">
                    <div class="skeleton rounded w-16 h-8 {{ $pulse }}"></div>
                    <div class="skeleton rounded w-16 h-8 {{ $pulse }}"></div>
                </div>
            </div>
        @break

        @case('avatar')
            <div class="flex items-center gap-4">
                <div class="skeleton rounded-full w-12 h-12 {{ $pulse }} {{ $class }}"></div>
                <div class="space-y-2 flex-1">
                    <div class="skeleton rounded w-32 h-4 {{ $pulse }}"></div>
                    <div class="skeleton rounded w-48 h-3 {{ $pulse }}"></div>
                </div>
            </div>
        @break

        @case('text')
            <div class="space-y-3">
                @for($i = 0; $i < $lines; $i++)
                    <div class="skeleton rounded {{ $i === $lines - 1 ? 'w-3/4' : 'w-full' }} {{ $sizeH }} {{ $pulse }} {{ $class }}"></div>
                @endfor
            </div>
        @break

        @case('circle')
            <div class="flex items-center justify-center">
                <div class="skeleton rounded-full w-24 h-24 {{ $pulse }} {{ $class }}"></div>
            </div>
        @break

        @case('image')
            <div class="skeleton rounded-lg w-full h-64 {{ $pulse }} {{ $class }}"></div>
        @break

        @case('table')
            <div class="space-y-3">
                <div class="flex gap-4 pb-3 border-b border-base-300">
                    @foreach(['w-12','w-24','w-full','w-16'] as $w)
                        <div class="skeleton rounded {{ $w }} h-4 {{ $pulse }}"></div>
                    @endforeach
                </div>
                @for($i = 0; $i < 3; $i++)
                    <div class="flex gap-4 py-3">
                        @foreach(['w-12','w-24','w-full','w-16'] as $w)
                            <div class="skeleton rounded {{ $w }} h-4 {{ $pulse }}"></div>
                        @endforeach
                    </div>
                @endfor
            </div>
        @break

        @case('grid')
            <div class="grid grid-cols-{{ $gridCols }} gap-4">
                @for($i = 0; $i < $lines; $i++)
                    <div class="space-y-2">
                        <div class="skeleton rounded-lg w-full h-32 {{ $pulse }}"></div>
                        <div class="skeleton rounded w-3/4 h-4 {{ $pulse }}"></div>
                        <div class="skeleton rounded w-1/2 h-3 {{ $pulse }}"></div>
                    </div>
                @endfor
            </div>
        @break

        @default
            <div class="skeleton rounded {{ $pulse }} {{ $class }}" style="width: {{ $width }}%"></div>
    @endswitch
</div>
