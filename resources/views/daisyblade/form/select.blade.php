@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'loadUrl'        => '',
    'dependsOn'      => '',
    'placeholder'    => '',
    'hint'           => '',
    'multiple'       => false,
    'searchable'     => false,
    'disabled'       => false,
    'required'       => false,
    'size'           => 'md',   // xs | sm | md | lg | xl
    'selectClass'    => '',
    'value'          => null,
])

@php
$sizeClass = match($size) {
    'xs' => 'select-xs',
    'sm' => 'select-sm',
    'lg' => 'select-lg',
    'xl' => 'select-xl',
    default => 'select-md',
};
// Valor(es) seleccionados como texto, para comparar con las claves de `options` sea cual sea su tipo.
$selected = $value === null ? [] : array_map('strval', (array) $value);
$isSelected = fn ($key) => in_array((string) $key, $selected, true);
$selectedLabel = '';
foreach ($options as $optVal => $optLabel) {
    foreach (is_array($optLabel) ? $optLabel : [$optVal => $optLabel] as $key => $text) {
        if ($selectedLabel === '' && $isSelected($key)) {
            $selectedLabel = (string) $text;
        }
    }
}
@endphp

{{-- With `load-url` the Alpine scope (dblSelectRemote) is this container, so the error message below can sit
     next to the <select>; the factory finds the <select> inside it. --}}
{{-- Everything the caller writes (class, id, data-*, aria-*...) goes to the root, as in any Laravel component.
     The one exception is the binding of the value (x-model, wire:model): it only means something on the control. --}}
<div {{ $attributes->whereDoesntStartWith(['x-model', 'wire:model'])->merge(['class' => 'w-full']) }}
     @if($loadUrl) x-data="dblSelectRemote(@js(['loadUrl' => $loadUrl, 'value' => $value, 'dependsOn' => $dependsOn, 'disabled' => $disabled, 'messages' => ['loadFailed' => __('Could not load the options')]]))" @endif>
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($name) for="{{ $name }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    @if($loadUrl)
        {{-- Remote select: Alpine's dblSelectRemote asks `load-url` for the options (again each time `depends-on` changes). --}}
        <select
            :aria-busy="loading"
            x-on:change="remember()"
            @if($dependsOn) x-on:change.window="onDependencyChange($event)" @endif
            :disabled="waiting || disabled"
            @if($name) id="{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }}
            class="{{ trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error ' : '')
                . $selectClass) }}"
        >
            @if(!$multiple && $placeholder) <option value="">{{ $placeholder }}</option> @endif
            {{-- What the server already knows (for example the saved value): shown until the JS brings the real options, or if it never does. --}}
            @foreach($options as $optVal => $optLabel)
                <option value="{{ $optVal }}" data-initial @selected($isSelected($optVal))>{{ $optLabel }}</option>
            @endforeach
            <template x-for="option in options" :key="option.value">
                <option :value="option.value" :selected="selected.includes(String(option.value))" x-text="option.label"></option>
            </template>
        </select>
        {{-- The options could not be loaded: a message and a retry button (the detail is in the console). --}}
        <p class="mt-1 flex items-center gap-2 text-xs text-error" role="alert" style="display: none" x-show="error">
            <span x-text="error"></span>
            <button type="button" class="link" x-on:click="retry()">{{ __('Retry') }}</button>
        </p>
    @elseif($searchable)
        {{-- Alpine searchable combobox — soporta flat {k:v} y grouped {group:{k:v}} --}}
        <div x-data="{
            search: @js($selectedLabel),
            open: false,
            options: @js($options),
            get isGrouped() {
                const vals = Object.values(this.options)
                return vals.length > 0 && typeof vals[0] === 'object' && vals[0] !== null
            },
            get filtered() {
                if (!this.search) return this.options
                const q = this.search.toLowerCase()
                if (!this.isGrouped) {
                    return Object.fromEntries(
                        Object.entries(this.options).filter(([k, v]) =>
                            String(v).toLowerCase().includes(q) || String(k).toLowerCase().includes(q)
                        )
                    )
                }
                const result = {}
                Object.entries(this.options).forEach(([group, items]) => {
                    const matched = Object.fromEntries(
                        Object.entries(items).filter(([k, v]) =>
                            String(v).toLowerCase().includes(q) || String(k).toLowerCase().includes(q)
                        )
                    )
                    if (Object.keys(matched).length > 0) result[group] = matched
                })
                return result
            },
            get flatList() {
                if (!this.isGrouped) {
                    return Object.entries(this.filtered).map(([k, v]) => ({ type: 'option', key: k, label: String(v) }))
                }
                const list = []
                Object.entries(this.filtered).forEach(([group, items], gi) => {
                    list.push({ type: 'group', label: group, first: gi === 0 })
                    Object.entries(items).forEach(([k, v]) => list.push({ type: 'option', key: k, label: String(v) }))
                })
                return list
            },
            get isEmpty() { return this.flatList.filter(i => i.type === 'option').length === 0 }
        }" class="relative" @click.outside="open = false">
            <div class="relative">
                <input
                    type="text"
                    x-model="search"
                    @focus="open = true"
                    placeholder="{{ $placeholder ?: __('Search...') }}"
                    class="input w-full {{ $sizeClass }} {{ $selectClass }}"
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40">
                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                </div>
            </div>

            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <template x-for="item in flatList" :key="item.type + ':' + (item.key ?? item.label)">
                    <div>
                        <div
                            x-show="item.type === 'group'"
                            :class="item.first ? 'pt-1' : 'border-t border-base-200 mt-1 pt-1'"
                            class="px-3 pb-0.5 text-xs font-semibold text-base-content/40 uppercase tracking-wide"
                            x-text="item.label"
                        ></div>
                        <button
                            x-show="item.type === 'option'"
                            type="button"
                            @click="search = item.label; open = false; $el.closest('[x-data]').querySelector('input[type=hidden]').value = item.key; $dispatch('select-change', { name: '{{ $name }}', value: item.key })"
                            class="w-full text-left px-3 py-2 hover:bg-primary/10 text-sm"
                            :class="{ 'pl-5': isGrouped }"
                            x-text="item.label"
                        ></button>
                    </div>
                </template>
                <template x-if="isEmpty">
                    <div class="px-3 py-4 text-sm text-base-content/50 text-center">{{ __('No results') }}</div>
                </template>
            </div>

            @if($name)
                <input type="hidden" name="{{ $name }}" value="{{ $selected[0] ?? '' }}" {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }} />
            @endif
        </div>
    @else
        {{-- Native select --}}
        <select
            @if($name) id="{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->whereStartsWith(['x-model', 'wire:model']) }}
            class="{{ trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error ' : '')
                . $selectClass) }}"
        >
            @if(!$multiple && $placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @foreach($options as $optVal => $optLabel)
                @if(is_array($optLabel))
                    <optgroup label="{{ $optVal }}">
                        @foreach($optLabel as $gVal => $gLabel)
                            <option value="{{ $gVal }}" @selected($isSelected($gVal))>{{ $gLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $optVal }}" @selected($isSelected($optVal))>{{ $optLabel }}</option>
                @endif
            @endforeach
        </select>
    @endif

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1">
                <span class="text-xs text-error font-semibold">{{ $message }}</span>
            </div>
        @enderror
    @endif

    @if($hint)
        <p class="mt-1 text-xs text-base-content/60">{{ $hint }}</p>
    @endif
</div>
