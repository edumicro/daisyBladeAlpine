@props([
    'items'          => [],
    'ordered'        => false,
    'divided'        => true,
    'size'           => 'md',
    'icon'           => '',
    'hoverable'      => false,
    'label'          => '',
    'class'          => '',
    'containerClass' => '',
])

@php
$sizeClass = match($size) { 'xs'=>'text-xs','sm'=>'text-sm','lg'=>'text-lg', default=>'text-base' };
$tag = $ordered ? 'ol' : 'ul';
@endphp

<div class="{{ $containerClass }}">
    @if($label)
        <div class="mb-3 pb-2 border-b border-base-300">
            <h3 class="font-semibold">{{ $label }}</h3>
        </div>
    @endif

    <{{ $tag }} @class([$sizeClass, 'space-y-1', $class])>
        @forelse($items as $item)
            <li @class([
                'flex flex-col',
                'pb-2 border-b border-base-300/50 last:border-b-0' => $divided,
                'hover:bg-base-200/50 px-2 py-1 rounded transition-colors cursor-pointer' => $hoverable,
            ])>
                <span class="flex items-center gap-2">
                    @if($icon)
                        <x-dynamic-component :component="$icon" class="w-4 h-4 shrink-0" />
                    @endif
                    <span>{{ $item['title'] ?? $item }}</span>
                </span>
                @if(is_array($item) && isset($item['description']))
                    <span class="text-xs text-base-content/60 ml-6 mt-0.5">{{ $item['description'] }}</span>
                @endif
            </li>
        @empty
            <li class="text-base-content/50 italic">{{ __('No items') }}</li>
        @endforelse
    </{{ $tag }}>
</div>
