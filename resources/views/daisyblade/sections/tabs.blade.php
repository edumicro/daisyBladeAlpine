{{--
    Tipo 2/3: tabbed form section.

    @prop tabs   — [ ['id'=>'general', 'label'=>'General', 'icon'=>'heroicon-o-cog',
                      'schema' => [...], 'values' => [...], 'description'=>''] ]
    @prop action — form action URL
    @prop method — HTTP method (default POST)
    @prop submitLabel — submit button label
    @prop default — default active tab id (first tab if empty)
--}}
@props([
    'tabs'         => [],
    'action'       => '',
    'method'       => 'POST',
    'submitLabel'  => '',
    'default'      => '',
    'class'        => '',
    'containerClass' => '',
])

@php
$defaultTab = $default ?: ($tabs[0]['id'] ?? '');
@endphp

<div
    x-data="dbTabs({ default: '{{ $defaultTab }}' })"
    {{ $attributes->merge(['class' => "space-y-0 {$containerClass}"]) }}
>
    {{-- Tab headers --}}
    <div role="tablist" class="tabs tabs-lifted w-full">
        @foreach($tabs as $tab)
            @php $id = $tab['id'] ?? ''; @endphp
            <button
                type="button"
                role="tab"
                @click="select('{{ $id }}')"
                :class="{ 'tab-active': isActive('{{ $id }}') }"
                class="tab gap-2"
                :aria-selected="isActive('{{ $id }}')"
            >
                @if(!empty($tab['icon']))
                    <x-dynamic-component :component="$tab['icon']" class="w-4 h-4" />
                @endif
                {{ $tab['label'] ?? $id }}
                @if(($tab['badge'] ?? 0) > 0)
                    <span class="badge badge-sm badge-primary">{{ $tab['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Form wrapping all panels --}}
    <form
        action="{{ $action ?: '#' }}"
        method="{{ in_array(strtoupper($method), ['GET','POST']) ? strtoupper($method) : 'POST' }}"
        class="bg-base-100 border border-base-300 border-t-0 rounded-b-box {{ $class }}"
    >
        @csrf
        @if(!in_array(strtoupper($method), ['GET','POST']))
            @method($method)
        @endif

        @foreach($tabs as $tab)
            @php
                $tabId  = $tab['id'] ?? '';
                $schema = $tab['schema'] ?? [];
                $vals   = $tab['values'] ?? [];

                $scopedFields = [];
                foreach ($schema as $fieldName => $fieldDef) {
                    $scopedFields["{$tabId}[{$fieldName}]"] = $fieldDef;
                }
                $scopedValues = [];
                foreach ($vals as $k => $v) {
                    $scopedValues["{$tabId}[{$k}]"] = $v;
                }
            @endphp

            <div
                x-show="isActive('{{ $tabId }}')"
                x-cloak
                class="p-6"
            >
                @if(!empty($tab['description']))
                    <p class="text-sm text-base-content/60 mb-4">{{ $tab['description'] }}</p>
                @endif

                <div class="grid grid-cols-12 gap-4">
                    @include('daisyblade::form.fields', [
                        'fields' => $scopedFields,
                        'mode'   => 'form',
                        'values' => $scopedValues,
                    ])
                </div>
            </div>
        @endforeach

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-base-300">
            <button type="submit" class="btn btn-primary">
                {{ $submitLabel ?: __('Save') }}
            </button>
        </div>
    </form>
</div>
