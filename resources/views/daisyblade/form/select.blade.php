@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'optionsUrl'     => '',
    'searchUrl'      => '',
    'queryParam'     => 'q',
    'params'         => [],
    'limit'          => 20,
    'minChars'       => 2,
    'debounce'       => 250,
    'text'           => '',
    'allowFreeText'  => false,
    'onSelect'       => '',
    'processResults' => '',
    'optionLabel'    => '',
    'onError'        => '',
    'hint'           => '',
    'dependsOn'      => '',
    'placeholder'    => '',
    'multiple'       => false,
    'searchable'     => false,
    'disabled'       => false,
    'required'       => false,
    'size'           => 'md',
    'class'          => '',
    'containerClass' => '',
    'value'          => null,
])

@php
// Dos orígenes de datos a la vez no tienen sentido: nunca se elige uno en silencio.
if ($searchUrl && $optionsUrl) {
    throw new \InvalidArgumentException('x-dbl::form.select: `search-url` and `options-url` cannot be used together. Use `options-url` to load the whole list once, or `search-url` to ask the server while the person types.');
}
if ($searchUrl && $multiple) {
    throw new \InvalidArgumentException('x-dbl::form.select: `search-url` does not support `multiple`.');
}
$sizeClass = match($size) { 'xs'=>'select-xs','sm'=>'select-sm','lg'=>'select-lg', default=>'' };
$inputSizeClass = match($size) { 'xs'=>'input-xs','sm'=>'input-sm','lg'=>'input-lg', default=>'' };
$hasError = $name && isset($errors) && $errors->has($name);
$comboId = ($name ?: 'combobox').'-'.\Illuminate\Support\Str::random(5);
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

{{-- With `options-url` the Alpine scope (dbSelectRemote) is this container, so the error message below can sit
     next to the <select>; the factory finds the <select> inside it.
     Everything Alpine needs from the component is written HERE, not on the <select>: the <select> receives
     `$attributes`, and an attribute of ours repeated there (`x-on:change`, `:disabled`...) would hide the one the
     user passes, because the browser keeps only the first of two equal attributes. So the <select> carries what
     it carried in 2.3 plus whatever the user adds.
       x-on:change         the `change` of the <select> bubbles up to this container (capture: before the user's own
                           listener, which may stop it).
       x-on:change.window  the control we depend on is elsewhere in the form, so its `change` only reaches `window`.
       x-effect            keeps `disabled` and `aria-busy` of the <select> in step with the component state. --}}
<div class="w-full {{ $containerClass }}"
     @if($optionsUrl)
         x-data="dbSelectRemote(@js(['url' => $optionsUrl, 'value' => $value, 'dependsOn' => $dependsOn, 'disabled' => $disabled, 'messages' => ['loadFailed' => __('Could not load the options')]]))"
         x-on:change.capture="remember()"
         @if($dependsOn) x-on:change.window="onDependencyChange($event)" @endif
         x-effect="syncField()"
     @endif>
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($name) for="{{ $name }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    @if($optionsUrl)
        {{-- Remote select: Alpine's dbSelectRemote asks `options-url` for the options (again each time `depends-on` changes). --}}
        <select
            @if($name) id="{{ $name }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['class' => trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error ' : '')
                . $class)]) }}
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
    @elseif($searchable || $searchUrl)
        {{-- Combobox (dbCombobox): the same input + hidden + list for the browser-side filter (`searchable`) and for the
             server search (`search-url`). Its listeners go on this container and not on the input, so the attributes
             the user passes to the input never hide ours. With `search-url` and without JS the input keeps the `name`
             (what is typed is sent); with JS the hidden takes it and carries the id. --}}
        <div x-data="dbCombobox(@js([
                'id' => $comboId,
                'name' => $name,
                'options' => $searchUrl ? [] : $options,
                'searchUrl' => $searchUrl,
                'queryParam' => $queryParam,
                'params' => $params,
                'limit' => (int) $limit,
                'minChars' => (int) $minChars,
                'debounce' => (int) $debounce,
                'value' => $searchUrl ? ($value ?? '') : ($selected[0] ?? ''),
                'text' => $searchUrl ? $text : $selectedLabel,
                'allowFreeText' => (bool) $allowFreeText,
                'dependsOn' => $dependsOn,
                'disabled' => (bool) $disabled,
                'onSelect' => $onSelect,
                'processResults' => $processResults,
                'optionLabel' => $optionLabel,
                'onError' => $onError,
                'messages' => [
                    'minChars' => __('Type :min or more characters', ['min' => (int) $minChars]),
                    'searching' => __('Searching…'),
                    'noResults' => $searchUrl ? __('Nothing matches that') : __('No results'),
                    'searchFailed' => __('Could not search'),
                ],
            ]))"
            class="relative"
            x-on:click.outside="close()"
            x-on:focusin="onFocus($event)"
            x-on:focusout="onFocusOut($event)"
            x-on:input="onInput($event)"
            x-on:keydown="onKeydown($event)"
            @if($dependsOn && $searchUrl) x-on:change.window="onDependencyChange($event)" @endif
            x-effect="syncField()">
            <div class="relative">
                <input
                    type="text"
                    x-ref="field"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="{{ $comboId }}-list"
                    autocomplete="off"
                    @if($name) id="{{ $name }}" @endif
                    @if($searchUrl && $name) name="{{ $name }}" @endif
                    value="{{ $searchUrl ? ($text !== '' ? $text : ($allowFreeText ? ($value ?? '') : '')) : $selectedLabel }}"
                    placeholder="{{ $placeholder ?: __('Search...') }}"
                    @if($disabled) disabled @endif
                    @if($required) required @endif
                    {{ $attributes->except(['x-model'])->merge(['class' => trim('input w-full ' . $inputSizeClass . ' '
                        . ($hasError ? 'input-error ' : '')
                        . $class)]) }}
                />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40">
                    <span class="loading loading-spinner loading-xs" style="display: none" x-show="loading"></span>
                    <x-heroicon-o-chevron-down class="w-4 h-4" x-show="!loading" />
                </div>
            </div>

            {{-- mousedown.prevent: clicking inside the list (an option, the scrollbar) must not take the focus from the input. --}}
            <div x-show="listOpen" x-cloak style="display: none" x-on:mousedown.prevent
                 class="absolute z-50 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div id="{{ $comboId }}-list" role="listbox">
                    <template x-for="item in items" :key="item.key">
                        <div role="presentation">
                            <div x-show="item.header" role="presentation" x-text="item.text"
                                 class="px-3 pt-2 pb-0.5 text-xs font-semibold text-base-content/40 uppercase tracking-wide"></div>
                            <div x-show="!item.header" role="option"
                                 :id="optionId(item.index)"
                                 :aria-selected="String(item.index === active)"
                                 x-on:click="choose(item.row)"
                                 x-on:mousemove="hover(item.index)"
                                 :class="{ 'bg-primary/10': item.index === active, 'pl-5': item.row && item.row.group }"
                                 class="cursor-pointer px-3 py-2 text-sm"
                                 x-text="item.row ? item.row.text : ''"></div>
                        </div>
                    </template>
                </div>
                {{-- The state in words, never an empty box. If the search failed: retry (the detail is in the console). --}}
                <div role="status" aria-live="polite" x-show="statusText" style="display: none"
                     class="flex items-center gap-2 px-3 py-3 text-sm text-base-content/60"
                     :class="{ 'text-error': error }">
                    <span x-text="statusText"></span>
                    <button type="button" class="link" x-show="error" style="display: none" x-on:click="retry()">{{ __('Retry') }}</button>
                </div>
            </div>

            <input type="hidden" x-ref="hidden"
                   @if(! $searchUrl && $name) name="{{ $name }}" @endif
                   value="{{ $searchUrl ? ($value ?? ($allowFreeText ? $text : '')) : ($selected[0] ?? '') }}"
                   @if($disabled) disabled @endif
                   {{ $attributes->only(['x-model']) }} />
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
        <div class="mt-1">
            <span class="text-xs text-base-content/60">{{ $hint }}</span>
        </div>
    @endif
</div>
