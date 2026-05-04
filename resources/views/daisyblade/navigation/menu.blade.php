@props([
    'items'          => [],
    'size'           => 'md',
    'direction'      => 'vertical',
    'showIcons'      => true,
    'compact'        => false,
    'fullWidth'      => false,
    'class'          => '',
    'containerClass' => '',
])

@php
$sizeClass = match($size) { 'xs'=>'menu-xs','sm'=>'menu-sm','lg'=>'menu-lg','xl'=>'menu-xl', default=>'menu-md' };
$dirClass  = $direction === 'horizontal' ? 'menu-horizontal' : 'menu-vertical';
@endphp

<ul
    {{ $attributes->merge(['class' => trim("menu {$sizeClass} {$dirClass} " . ($fullWidth ? 'w-full ' : '') . (!$compact ? 'bg-base-100 rounded-lg ' : '') . $class)]) }}
>
    @foreach($items as $item)
        @php $type = $item['type'] ?? 'item'; @endphp

        @if($type === 'divider')
            <li class="menu-title"><hr></li>

        @elseif($type === 'title')
            <li class="menu-title"><span>{{ $item['label'] ?? '' }}</span></li>

        @else
            <li class="{{ ($item['disabled'] ?? false) ? 'disabled' : '' }}">
                @if(!empty($item['children']))
                    <details @unless($item['collapsed'] ?? true) open @endunless>
                        <summary class="{{ ($item['active'] ?? false) ? 'menu-active' : '' }}">
                            @if($showIcons && ($item['icon'] ?? false))
                                <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                            @endif
                            <span>{{ $item['label'] ?? '' }}</span>
                            @if($item['badge'] ?? false)
                                <span class="badge badge-sm badge-{{ $item['badgeColor'] ?? 'primary' }}">{{ $item['badge'] }}</span>
                            @endif
                        </summary>
                        <ul class="ml-2">
                            @foreach($item['children'] as $child)
                                <li class="{{ ($child['disabled'] ?? false) ? 'disabled' : '' }}">
                                    @if(!empty($child['children']))
                                        <details @unless($child['collapsed'] ?? true) open @endunless>
                                            <summary class="{{ ($child['active'] ?? false) ? 'menu-active' : '' }}">
                                                @if($showIcons && ($child['icon'] ?? false))
                                                    <x-dynamic-component :component="$child['icon']" class="w-4 h-4" />
                                                @endif
                                                <span>{{ $child['label'] ?? '' }}</span>
                                                @if($child['badge'] ?? false)
                                                    <span class="badge badge-sm badge-{{ $child['badgeColor'] ?? 'primary' }}">{{ $child['badge'] }}</span>
                                                @endif
                                            </summary>
                                            <ul class="ml-2">
                                                @foreach($child['children'] as $nested)
                                                    <li class="{{ ($nested['disabled'] ?? false) ? 'disabled' : '' }}">
                                                        @if($nested['route'] ?? false)
                                                            <a href="{{ route($nested['route']) }}" class="{{ ($nested['active'] ?? false) ? 'menu-active' : '' }}">
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm badge-{{ $nested['badgeColor'] ?? 'primary' }}">{{ $nested['badge'] }}</span> @endif
                                                            </a>
                                                        @elseif($nested['url'] ?? false)
                                                            <a href="{{ $nested['url'] }}" class="{{ ($nested['active'] ?? false) ? 'menu-active' : '' }}">
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm badge-{{ $nested['badgeColor'] ?? 'primary' }}">{{ $nested['badge'] }}</span> @endif
                                                            </a>
                                                        @else
                                                            <button type="button" @if($nested['action'] ?? false) @click="$dispatch('{{ $nested['action'] }}')" @endif>
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm badge-{{ $nested['badgeColor'] ?? 'primary' }}">{{ $nested['badge'] }}</span> @endif
                                                            </button>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @elseif($child['route'] ?? false)
                                        <a href="{{ route($child['route']) }}" class="{{ ($child['active'] ?? false) ? 'menu-active' : '' }}">
                                            @if($showIcons && ($child['icon'] ?? false)) <x-dynamic-component :component="$child['icon']" class="w-4 h-4" /> @endif
                                            <span>{{ $child['label'] ?? '' }}</span>
                                            @if($child['badge'] ?? false) <span class="badge badge-sm badge-{{ $child['badgeColor'] ?? 'primary' }}">{{ $child['badge'] }}</span> @endif
                                        </a>
                                    @elseif($child['url'] ?? false)
                                        <a href="{{ $child['url'] }}" class="{{ ($child['active'] ?? false) ? 'menu-active' : '' }}">
                                            @if($showIcons && ($child['icon'] ?? false)) <x-dynamic-component :component="$child['icon']" class="w-4 h-4" /> @endif
                                            <span>{{ $child['label'] ?? '' }}</span>
                                            @if($child['badge'] ?? false) <span class="badge badge-sm badge-{{ $child['badgeColor'] ?? 'primary' }}">{{ $child['badge'] }}</span> @endif
                                        </a>
                                    @else
                                        <button type="button" @if($child['action'] ?? false) @click="$dispatch('{{ $child['action'] }}')" @endif>
                                            @if($showIcons && ($child['icon'] ?? false)) <x-dynamic-component :component="$child['icon']" class="w-4 h-4" /> @endif
                                            <span>{{ $child['label'] ?? '' }}</span>
                                            @if($child['badge'] ?? false) <span class="badge badge-sm badge-{{ $child['badgeColor'] ?? 'primary' }}">{{ $child['badge'] }}</span> @endif
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @elseif($item['route'] ?? false)
                    <a href="{{ route($item['route']) }}" class="{{ ($item['active'] ?? false) ? 'menu-active' : '' }}">
                        @if($showIcons && ($item['icon'] ?? false)) <x-dynamic-component :component="$item['icon']" class="w-4 h-4" /> @endif
                        <span>{{ $item['label'] ?? '' }}</span>
                        @if($item['badge'] ?? false) <span class="badge badge-sm badge-{{ $item['badgeColor'] ?? 'primary' }}">{{ $item['badge'] }}</span> @endif
                    </a>
                @elseif($item['url'] ?? false)
                    <a href="{{ $item['url'] }}" class="{{ ($item['active'] ?? false) ? 'menu-active' : '' }}">
                        @if($showIcons && ($item['icon'] ?? false)) <x-dynamic-component :component="$item['icon']" class="w-4 h-4" /> @endif
                        <span>{{ $item['label'] ?? '' }}</span>
                        @if($item['badge'] ?? false) <span class="badge badge-sm badge-{{ $item['badgeColor'] ?? 'primary' }}">{{ $item['badge'] }}</span> @endif
                    </a>
                @else
                    <button type="button" @if($item['action'] ?? false) @click="$dispatch('{{ $item['action'] }}')" @endif>
                        @if($showIcons && ($item['icon'] ?? false)) <x-dynamic-component :component="$item['icon']" class="w-4 h-4" /> @endif
                        <span>{{ $item['label'] ?? '' }}</span>
                        @if($item['badge'] ?? false) <span class="badge badge-sm badge-{{ $item['badgeColor'] ?? 'primary' }}">{{ $item['badge'] }}</span> @endif
                    </button>
                @endif
            </li>
        @endif
    @endforeach
</ul>
