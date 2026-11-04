{{-- The labels show while the sidebar around the tree is open (outside a sidebar there is no `open`: they always show).
     `Alpine.$data(element)` is the public way to read the data of the scope of an element; `el.__x.$data` was Alpine 2 and does not exist in 3. --}}
@props([
    'items' => [],
    'depth' => 0,
])

@php $items = collect($items ?? []); @endphp

@if($items->isNotEmpty())
    <ul {{ $attributes->class(['menu', 'menu-sm' => $depth === 0, 'w-full']) }}>
        @foreach($items as $item)
            @php
                $hasChildren = !empty($item['children']);
                $nodeKey     = $item['id'] ?? $item['url'] ?? $loop->index;
                $url         = $item['url'] ?? '#';
                $isActive    = $url !== '#' && request()->is(ltrim($url, '/') . '*');
            @endphp

            @if($hasChildren)
                <li x-data="dblSidebarTree()">
                    <button
                        type="button"
                        class="flex justify-between {{ $isActive ? 'active' : '' }}"
                        @click="toggle(@js($nodeKey))"
                    >
                        @if(!empty($item['icon']))
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5 shrink-0" />
                        @endif
                        <span x-show="Alpine.$data($el.closest('aside') ?? $el)?.open ?? true" class="flex-1 text-left truncate">
                            {{ __($item['label'] ?? '') }}
                        </span>
                        {{-- In a <span> and not on the icon: `@js()` inside the tag of a Blade component would not see the variables of this view. --}}
                        <span class="shrink-0 transition-transform duration-200" :class="{ 'rotate-90': isOpen(@js($nodeKey)) }">
                            <x-heroicon-o-chevron-right class="w-4 h-4" />
                        </span>
                    </button>
                    <ul x-show="isOpen(@js($nodeKey))" x-cloak>
                        @foreach($item['children'] as $child)
                            @php
                                $childUrl    = $child['url'] ?? '#';
                                $childActive = $childUrl !== '#' && request()->is(ltrim($childUrl, '/'));
                            @endphp
                            <li>
                                <a href="{{ $childUrl }}" @dblClass(['active' => $childActive])>
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
                    <a href="{{ $url }}" @dblClass(['active' => $isActive])>
                        @if(!empty($item['icon']))
                            <x-dynamic-component :component="$item['icon']" class="w-5 h-5 shrink-0" />
                        @endif
                        <span x-show="Alpine.$data($el.closest('aside') ?? $el)?.open ?? true" class="truncate">
                            {{ __($item['label'] ?? '') }}
                        </span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
@endif
