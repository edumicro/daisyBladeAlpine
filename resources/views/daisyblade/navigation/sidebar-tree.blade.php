@props([
    'items' => [],
    'depth' => 0,
])

@php $items = collect($items ?? []); @endphp

@if($items->isNotEmpty())
    <ul @class(['menu', 'menu-sm' => $depth === 0, 'w-full'])>
        @foreach($items as $item)
            @php
                $hasChildren = !empty($item['children']);
                $nodeKey     = $item['id'] ?? $item['url'] ?? $loop->index;
                $url         = $item['url'] ?? '#';
                $isActive    = $url !== '#' && request()->is(ltrim($url, '/') . '*');
            @endphp

            @if($hasChildren)
                <li x-data="dbSidebarTree()">
                    <button
                        type="button"
                        class="flex justify-between {{ $isActive ? 'active' : '' }}"
                        @click="toggle('{{ $nodeKey }}')"
                    >
                        @if(!empty($item['icon']))
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5 shrink-0" />
                        @endif
                        <span x-show="$root.closest('aside')?.__x?.$data?.open ?? true" class="flex-1 text-left truncate">
                            {{ __($item['label'] ?? '') }}
                        </span>
                        <x-heroicon-o-chevron-right
                            class="w-4 h-4 shrink-0 transition-transform duration-200"
                            ::class="{ 'rotate-90': isOpen('{{ $nodeKey }}') }"
                        />
                    </button>
                    <ul x-show="isOpen('{{ $nodeKey }}')" x-cloak>
                        @foreach($item['children'] as $child)
                            @php
                                $childUrl    = $child['url'] ?? '#';
                                $childActive = $childUrl !== '#' && request()->is(ltrim($childUrl, '/'));
                            @endphp
                            <li>
                                <a href="{{ $childUrl }}" @class(['active' => $childActive])>
                                    @if(!empty($child['icon']))
                                        <x-dynamic-component :component="$child['icon']" class="w-4 h-4 shrink-0" />
                                    @endif
                                    {{ __($child['label'] ?? '') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li>
                    <a href="{{ $url }}" @class(['active' => $isActive])>
                        @if(!empty($item['icon']))
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5 shrink-0" />
                        @endif
                        <span x-show="$root.closest('aside')?.__x?.$data?.open ?? true" class="truncate">
                            {{ __($item['label'] ?? '') }}
                        </span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
@endif
