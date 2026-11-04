{{--
    Tipo 3: lazy-load tabbed sections via Alpine + Axios.

    @prop tabs   — [ ['label'=>'General', 'load_url'=>'/resource/1/general', 'icon'=>null] ]
    @prop active — initial active tab index (0-based)

    Server must return HTML string for each tab panel.
--}}
@props([
    'tabs'   => [],
    'active' => 0,
    'style'  => 'lifted',
    'panelClass' => '',
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
$config = [
    'active'   => (int) $active,
    'tabs'     => array_map(fn ($t) => ['label' => $t['label'] ?? '', 'loadUrl' => $t['load_url'] ?? ''], array_values($tabs)),
    'messages' => ['loadFailed' => __('Could not load the content')],
];
@endphp

<div
    x-data="dblTabsLazy(@js($config))"
    {{ $attributes->merge(['class' => 'space-y-0']) }}
>
    {{-- Tab headers --}}
    <div role="tablist" class="tabs {{ $styleClass }} w-full">
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
            class="bg-base-100 border border-base-300 border-t-0 rounded-b-box p-6 {{ $panelClass }}"
        >
            <div x-show="isLoading({{ $i }})" class="flex justify-center py-8">
                <div class="loading loading-spinner loading-md"></div>
            </div>
            {{-- The load failed: a message and a retry button instead of an empty panel. Inline display:none because
                 the package cannot count on the host defining [x-cloak]. --}}
            <div x-show="hasError({{ $i }}) && !isLoading({{ $i }})" style="display: none" role="alert" class="alert alert-error">
                <x-heroicon-o-x-circle class="w-5 h-5" />
                <span x-text="errors[{{ $i }}]"></span>
                <button type="button" @click="retry({{ $i }})" class="btn btn-ghost btn-xs">{{ __('Retry') }}</button>
            </div>
            <div x-show="!isLoading({{ $i }}) && !hasError({{ $i }})" x-html="contents[{{ $i }}] ?? ''"></div>
        </div>
    @endforeach
</div>
