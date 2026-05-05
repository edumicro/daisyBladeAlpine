{{--
    Tipo 3: lazy-load tabbed sections via Alpine + Axios.

    @prop tabs   — [ ['label'=>'General', 'load-url'=>'/resource/1/general', 'icon'=>null] ]
    @prop active — initial active tab index (0-based)

    Server must return HTML string for each tab panel.
--}}
@props([
    'tabs'   => [],
    'active' => 0,
    'style'  => 'lifted',
    'class'  => '',
    'containerClass' => '',
])

@php
$tabsJson = json_encode(array_map(fn($t) => ['label' => $t['label'] ?? '', 'url' => $t['load-url'] ?? ''], $tabs), JSON_UNESCAPED_SLASHES);
@endphp

<div
    x-data="dbTabsLazy({ active: {{ (int)$active }}, tabs: {{ $tabsJson }} })"
    x-init="init()"
    {{ $attributes->merge(['class' => "space-y-0 {$containerClass}"]) }}
>
    {{-- Tab headers --}}
    <div role="tablist" class="tabs tabs-{{ $style }} w-full">
        @foreach($tabs as $i => $tab)
            <button
                type="button"
                role="tab"
                class="tab gap-2"
                :class="{ 'tab-active': isActive({{ $i }}) }"
                :aria-selected="isActive({{ $i }})"
                @click="select({{ $i }})"
            >
                @if(!empty($tab['icon']))
                    <x-dynamic-component :component="$tab['icon']" class="w-4 h-4" />
                @endif
                {{ $tab['label'] ?? '' }}
                @if(!empty($tab['badge']))
                    <span class="badge badge-sm">{{ $tab['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Tab panels --}}
    @foreach($tabs as $i => $tab)
        <div
            x-show="isActive({{ $i }})"
            x-cloak
            class="bg-base-100 border border-base-300 border-t-0 rounded-b-box p-6 {{ $class }}"
        >
            <div x-show="isLoading({{ $i }})" class="flex justify-center py-8">
                <div class="loading loading-spinner loading-md"></div>
            </div>
            <div x-show="!isLoading({{ $i }})" x-html="contents[{{ $i }}] ?? ''"></div>
        </div>
    @endforeach
</div>
