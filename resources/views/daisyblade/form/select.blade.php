@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'optionsUrl'     => '',
    'placeholder'    => '',
    'multiple'       => false,
    'searchable'     => false,
    'disabled'       => false,
    'required'       => false,
    'size'           => 'md',
    'class'          => '',
    'containerClass' => '',
])

@php
$sizeClass = match($size) { 'xs'=>'select-xs','sm'=>'select-sm','lg'=>'select-lg', default=>'' };
@endphp

<div class="form-control w-full {{ $containerClass }}">
    @if($label)
        <label class="label" @if($name) for="{{ $name }}" @endif>
            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
        </label>
    @endif

    @if($optionsUrl)
        {{-- Remote select via Alpine dbSelectRemote factory --}}
        <select
            x-data="dbSelectRemote({ url: '{{ $optionsUrl }}', name: '{{ $name }}' })"
            x-init="init()"
            @if($name) id="{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim('select w-full ' . $sizeClass . ' ' . $class)]) }}
        >
            @if($placeholder) <option value="">{{ $placeholder }}</option> @endif
            <template x-for="opt in options" :key="opt.value">
                <option :value="opt.value" x-text="opt.label"></option>
            </template>
        </select>
    @elseif($searchable)
        {{-- Alpine searchable combobox — soporta flat {k:v} y grouped {group:{k:v}} --}}
        <div x-data="{
            search: '',
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
                    class="input w-full {{ $sizeClass }}"
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
                <input type="hidden" name="{{ $name }}" {{ $attributes->only(['value', 'x-model']) }} />
            @endif
        </div>
    @else
        {{-- Native select --}}
        <select
            @if($name) id="{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error ' : '')
                . $class)]) }}
        >
            @if(!$multiple && $placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @foreach($options as $optVal => $optLabel)
                @if(is_array($optLabel))
                    <optgroup label="{{ $optVal }}">
                        @foreach($optLabel as $gVal => $gLabel)
                            <option value="{{ $gVal }}">{{ $gLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $optVal }}">{{ $optLabel }}</option>
                @endif
            @endforeach
        </select>
    @endif

    @if($name && isset($errors))
        @error($name)
            <label class="label p-0 mt-1">
                <span class="label-text-alt text-error font-semibold">{{ $message }}</span>
            </label>
        @enderror
    @endif
</div>
