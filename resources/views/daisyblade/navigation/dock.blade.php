@props([
    'items'              => [],
    'position'           => 'bottom-center',
    'direction'          => 'horizontal',
    'showLabelsOnHover'  => true,
    'iconSize'           => 'md',   // xs | sm | md | lg | xl
    'barClass'           => '',
])

@php
$posClass = match($position) {
    'bottom-left'  => 'bottom-0 left-0',
    'bottom-right' => 'bottom-0 right-0',
    'top-center'   => 'top-0 left-1/2 -translate-x-1/2',
    'top-left'     => 'top-0 left-0',
    'top-right'    => 'top-0 right-0',
    default        => 'bottom-0 left-1/2 -translate-x-1/2',
};
// The size of the icons is a token; the class names are written out in full so Tailwind can find them.
$iconSizeClass = match($iconSize) {
    'xs' => 'w-4 h-4',
    'sm' => 'w-5 h-5',
    'lg' => 'w-8 h-8',
    'xl' => 'w-10 h-10',
    default => 'w-6 h-6',   // md
};
$dirClass = $direction === 'vertical' ? 'flex-col' : 'flex-row';
@endphp

<div {{ $attributes->merge(['class' => "fixed {$posClass} p-4 z-40"]) }}>
    <div class="flex {{ $dirClass }} gap-2 bg-base-100 shadow-2xl rounded-full p-3 backdrop-blur-sm border border-base-300 {{ $barClass }}">
        @foreach($items as $item)
            <div class="relative group">
                @if($item['route'] ?? false)
                    <a
                        href="{{ route($item['route']) }}"
                        class="btn btn-ghost btn-sm btn-circle relative transition-all duration-200 hover:bg-primary hover:text-primary-content"
                        title="{{ $item['label'] ?? '' }}"
                    >
                        <x-dynamic-component :component="$item['icon'] ?? 'heroicon-o-question-mark-circle'" class="{{ $iconSizeClass }}" />
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="absolute -top-1 -right-1 bg-error text-error-content text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @elseif($item['url'] ?? false)
                    <a
                        href="{{ $item['url'] }}"
                        class="btn btn-ghost btn-sm btn-circle relative transition-all duration-200 hover:bg-primary hover:text-primary-content"
                        title="{{ $item['label'] ?? '' }}"
                    >
                        <x-dynamic-component :component="$item['icon'] ?? 'heroicon-o-question-mark-circle'" class="{{ $iconSizeClass }}" />
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="absolute -top-1 -right-1 bg-error text-error-content text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @else
                    <button
                        type="button"
                        @if($item['action'] ?? false) @click="$dispatch(@js($item['action']))" @endif
                        class="btn btn-ghost btn-sm btn-circle relative transition-all duration-200 hover:bg-primary hover:text-primary-content"
                        title="{{ $item['label'] ?? '' }}"
                    >
                        <x-dynamic-component :component="$item['icon'] ?? 'heroicon-o-question-mark-circle'" class="{{ $iconSizeClass }}" />
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="absolute -top-1 -right-1 bg-error text-error-content text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $item['badge'] }}</span>
                        @endif
                    </button>
                @endif

                @if($showLabelsOnHover)
                    <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <div class="bg-base-300 text-base-content text-xs font-medium px-2 py-1 rounded whitespace-nowrap shadow-md">
                            {{ $item['label'] ?? '' }}
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
