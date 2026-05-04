@props([
    'items'          => [],
    'separator'      => '/',
    'showHomeIcon'   => true,
    'class'          => '',
    'containerClass' => '',
])

<nav {{ $attributes->merge(['class' => "text-sm breadcrumbs {$containerClass}"]) }}>
    <ul class="flex items-center gap-2 {{ $class }}">
        @foreach($items as $item)
            <li>
                @if($loop->last)
                    <span class="text-base-content font-medium">
                        @if($item['icon'] ?? false)
                            <x-dynamic-component :component="$item['icon']" class="w-4 h-4 inline-block mr-1" />
                        @endif
                        {{ $item['label'] ?? '' }}
                    </span>
                @else
                    @if($item['route'] ?? false)
                        <a href="{{ route($item['route']) }}" class="link link-hover text-base-content/70">
                            @if($showHomeIcon && $loop->first && ($item['icon'] ?? false))
                                <x-dynamic-component :component="$item['icon']" class="w-4 h-4 inline-block mr-1" />
                            @endif
                            {{ $item['label'] ?? '' }}
                        </a>
                    @elseif($item['url'] ?? false)
                        <a href="{{ $item['url'] }}" class="link link-hover text-base-content/70">
                            @if($showHomeIcon && $loop->first && ($item['icon'] ?? false))
                                <x-dynamic-component :component="$item['icon']" class="w-4 h-4 inline-block mr-1" />
                            @endif
                            {{ $item['label'] ?? '' }}
                        </a>
                    @else
                        <span class="text-base-content/70">
                            @if($showHomeIcon && $loop->first && ($item['icon'] ?? false))
                                <x-dynamic-component :component="$item['icon']" class="w-4 h-4 inline-block mr-1" />
                            @endif
                            {{ $item['label'] ?? '' }}
                        </span>
                    @endif
                @endif
            </li>

            @if(!$loop->last)
                <li class="text-base-content/50">{{ $separator }}</li>
            @endif
        @endforeach
    </ul>
</nav>
