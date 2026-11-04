@props([
    'items'  => [],   // [['label'=>'Tab 1', 'content'=>'HTML', 'icon'=>null, 'badge'=>null], ...]
    'active' => 0,
    'style'  => 'lifted',
])

@php
// Class names written out in full, not glued together from a prefix and the value: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$styleClass = match($style) {
    'lifted' => 'tabs-lifted',
    'bordered' => 'tabs-bordered',
    'boxed' => 'tabs-boxed',
    'lift' => 'tabs-lift',
    'border' => 'tabs-border',
    'box' => 'tabs-box',
    default => 'tabs-lifted',
};
@endphp

<div x-data="dblTabs(@js(['active' => (int) $active]))" {{ $attributes }}>
    <div role="tablist" class="tabs {{ $styleClass }}">
        @foreach($items as $i => $item)
            <button
                type="button"
                role="tab"
                class="tab gap-2"
                :class="{ 'tab-active': isActive({{ $i }}) }"
                :aria-selected="isActive({{ $i }})"
                @click="select({{ $i }})"
            >
                @if(!empty($item['icon']))
                    <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                @endif
                {{ $item['label'] ?? __('Tab :number', ['number' => $i]) }}
                @if(!empty($item['badge']))
                    <span class="badge badge-sm">{{ $item['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach($items as $i => $item)
        <div x-show="isActive({{ $i }})" x-cloak class="pt-4">
            {!! $item['content'] ?? '' !!}
        </div>
    @endforeach
</div>
