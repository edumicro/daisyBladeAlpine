@props([
    'items'          => [],
    'size'           => 'md',   // xs | sm | md | lg | xl
    'direction'      => 'vertical',
    'showIcons'      => true,
    'compact'        => false,
    'fullWidth'      => false,
])

@php
$sizeClass = match($size) {
    'xs' => 'menu-xs',
    'sm' => 'menu-sm',
    'lg' => 'menu-lg',
    'xl' => 'menu-xl',
    default => 'menu-md',
};
$dirClass  = $direction === 'horizontal' ? 'menu-horizontal' : 'menu-vertical';
// Class names written out in full, not glued together from a prefix and the value: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$badgeClass = fn ($color) => match($color) {
    'neutral' => 'badge-neutral',
    'primary' => 'badge-primary',
    'secondary' => 'badge-secondary',
    'accent' => 'badge-accent',
    'info' => 'badge-info',
    'success' => 'badge-success',
    'warning' => 'badge-warning',
    'error' => 'badge-error',
    'ghost' => 'badge-ghost',
    default => 'badge-primary',
};
@endphp

{{-- The root is the caller's element: it holds no Alpine attribute of ours. The buttons that `$dispatch` carry their own `x-data`,
     so the order works outside any other scope without a scope around the root. --}}
<ul
    {{ $attributes->merge(['class' => trim("menu {$sizeClass} {$dirClass} " . ($fullWidth ? 'w-full ' : '') . (!$compact ? 'bg-base-100 rounded-lg' : ''))]) }}
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
                                <span class="badge badge-sm {{ $badgeClass($item['badge_color'] ?? 'primary') }}">{{ $item['badge'] }}</span>
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
                                                    <span class="badge badge-sm {{ $badgeClass($child['badge_color'] ?? 'primary') }}">{{ $child['badge'] }}</span>
                                                @endif
                                            </summary>
                                            <ul class="ml-2">
                                                @foreach($child['children'] as $nested)
                                                    <li class="{{ ($nested['disabled'] ?? false) ? 'disabled' : '' }}">
                                                        @if($nested['route'] ?? false)
                                                            <a href="{{ route($nested['route']) }}" class="{{ ($nested['active'] ?? false) ? 'menu-active' : '' }}">
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($nested['badge_color'] ?? 'primary') }}">{{ $nested['badge'] }}</span> @endif
                                                            </a>
                                                        @elseif($nested['url'] ?? false)
                                                            <a href="{{ $nested['url'] }}" class="{{ ($nested['active'] ?? false) ? 'menu-active' : '' }}">
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($nested['badge_color'] ?? 'primary') }}">{{ $nested['badge'] }}</span> @endif
                                                            </a>
                                                        @else
                                                            <button type="button" @if($nested['action'] ?? false) x-data @click="$dispatch(@js($nested['action']))" @endif>
                                                                @if($showIcons && ($nested['icon'] ?? false)) <x-dynamic-component :component="$nested['icon']" class="w-4 h-4" /> @endif
                                                                <span>{{ $nested['label'] ?? '' }}</span>
                                                                @if($nested['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($nested['badge_color'] ?? 'primary') }}">{{ $nested['badge'] }}</span> @endif
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
                                            @if($child['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($child['badge_color'] ?? 'primary') }}">{{ $child['badge'] }}</span> @endif
                                        </a>
                                    @elseif($child['url'] ?? false)
                                        <a href="{{ $child['url'] }}" class="{{ ($child['active'] ?? false) ? 'menu-active' : '' }}">
                                            @if($showIcons && ($child['icon'] ?? false)) <x-dynamic-component :component="$child['icon']" class="w-4 h-4" /> @endif
                                            <span>{{ $child['label'] ?? '' }}</span>
                                            @if($child['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($child['badge_color'] ?? 'primary') }}">{{ $child['badge'] }}</span> @endif
                                        </a>
                                    @else
                                        <button type="button" @if($child['action'] ?? false) x-data @click="$dispatch(@js($child['action']))" @endif>
                                            @if($showIcons && ($child['icon'] ?? false)) <x-dynamic-component :component="$child['icon']" class="w-4 h-4" /> @endif
                                            <span>{{ $child['label'] ?? '' }}</span>
                                            @if($child['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($child['badge_color'] ?? 'primary') }}">{{ $child['badge'] }}</span> @endif
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
                        @if($item['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($item['badge_color'] ?? 'primary') }}">{{ $item['badge'] }}</span> @endif
                    </a>
                @elseif($item['url'] ?? false)
                    <a href="{{ $item['url'] }}" class="{{ ($item['active'] ?? false) ? 'menu-active' : '' }}">
                        @if($showIcons && ($item['icon'] ?? false)) <x-dynamic-component :component="$item['icon']" class="w-4 h-4" /> @endif
                        <span>{{ $item['label'] ?? '' }}</span>
                        @if($item['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($item['badge_color'] ?? 'primary') }}">{{ $item['badge'] }}</span> @endif
                    </a>
                @else
                    <button type="button" @if($item['action'] ?? false) x-data @click="$dispatch(@js($item['action']))" @endif>
                        @if($showIcons && ($item['icon'] ?? false)) <x-dynamic-component :component="$item['icon']" class="w-4 h-4" /> @endif
                        <span>{{ $item['label'] ?? '' }}</span>
                        @if($item['badge'] ?? false) <span class="badge badge-sm {{ $badgeClass($item['badge_color'] ?? 'primary') }}">{{ $item['badge'] }}</span> @endif
                    </button>
                @endif
            </li>
        @endif
    @endforeach
</ul>
