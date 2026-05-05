@props([
    'items'  => [],   // [['label'=>'Tab 1', 'content'=>'HTML', 'icon'=>null, 'badge'=>null], ...]
    'active' => 0,
    'style'  => 'lifted',
    'class'  => '',
])

<div x-data="{ active: {{ (int)$active }} }" {{ $attributes->merge(['class' => $class]) }}>
    <div role="tablist" class="tabs tabs-{{ $style }}">
        @foreach($items as $i => $item)
            <button
                type="button"
                role="tab"
                class="tab gap-2"
                :class="{ 'tab-active': active === {{ $i }} }"
                :aria-selected="active === {{ $i }}"
                @click="active = {{ $i }}"
            >
                @if(!empty($item['icon']))
                    <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                @endif
                {{ $item['label'] ?? "Tab {$i}" }}
                @if(!empty($item['badge']))
                    <span class="badge badge-sm">{{ $item['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach($items as $i => $item)
        <div x-show="active === {{ $i }}" x-cloak class="pt-4">
            {!! $item['content'] ?? '' !!}
        </div>
    @endforeach
</div>
