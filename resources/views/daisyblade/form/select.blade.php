@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
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

    @if($searchable)
        {{-- Alpine searchable combobox --}}
        <div x-data="{
            search: '',
            open: false,
            options: @js($options),
            get filtered() {
                if (!this.search) return this.options
                const q = this.search.toLowerCase()
                return Object.fromEntries(
                    Object.entries(this.options).filter(([k, v]) =>
                        String(v).toLowerCase().includes(q) || String(k).toLowerCase().includes(q)
                    )
                )
            }
        }" class="relative" @click.outside="open = false">
            <div class="relative">
                <input
                    type="text"
                    x-model="search"
                    @focus="open = true"
                    placeholder="{{ $placeholder ?: __('Search...') }}"
                    class="input input-bordered w-full {{ $sizeClass }}"
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40">
                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                </div>
            </div>

            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <template x-for="[key, label] in Object.entries(filtered)" :key="key">
                    <button
                        type="button"
                        @click="search = label; open = false; $el.closest('[x-data]').querySelector('input[type=hidden]').value = key; $dispatch('select-change', { name: '{{ $name }}', value: key })"
                        class="w-full text-left px-3 py-2 hover:bg-primary/10 text-sm"
                        x-text="label"
                    ></button>
                </template>
                <template x-if="Object.keys(filtered).length === 0">
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
            {{ $attributes->merge(['class' => trim('select select-bordered w-full ' . $sizeClass . ' '
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
