@props([
    'tabs'    => [],   // [['id'=>'t1','label'=>'Tab 1','icon'=>null,'badge'=>null], ...]
    'default' => null,
    'style'   => 'lifted',   // lifted | bordered | boxed
])

@php $defaultTab = $default ?? ($tabs[0]['id'] ?? ''); @endphp

<div x-data="dbTabs({ default: '{{ $defaultTab }}' })">

    <div role="tablist" class="tabs tabs-{{ $style }}">
        @foreach($tabs as $tab)
            <button
                type="button"
                role="tab"
                class="tab gap-2"
                :class="{ 'tab-active': isActive('{{ $tab['id'] }}') }"
                :aria-selected="isActive('{{ $tab['id'] }}')"
                @click="select('{{ $tab['id'] }}')"
            >
                @if(!empty($tab['icon']))
                    <x-dynamic-component :component="$tab['icon']" class="w-4 h-4" />
                @endif
                {{ __($tab['label'] ?? $tab['id']) }}
                @if(!empty($tab['badge']))
                    <span class="badge badge-sm">{{ $tab['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{ $slot }}

</div>
