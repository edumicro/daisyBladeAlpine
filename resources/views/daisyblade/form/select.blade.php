@props([
    'name'           => '',
    'label'          => '',
    'options'        => [],
    'loadUrl'        => '',
    'searchUrl'      => '',     // asks the server what to show while the user types (implies the searchable mode)
    'queryParam'     => 'q',    // the name of the typed text in that request
    'params'         => [],     // fixed params of every request (keys in snake_case)
    'limit'          => 20,
    'minChars'       => 2,
    'debounce'       => 250,    // milliseconds
    'text'           => '',     // the label of `value`, for a form that comes back with an error
    'allowFreeText'  => false,  // typed text with nothing chosen is fine: the server reads it in `{name}_text`
    'textName'       => '',     // name of the field that sends the typed text (default `{name}_text`)
    'optionLabel'    => 'text', // the field of a row that holds the label
    'processResults' => '',     // JS expression: `response` (the whole body) -> rows { id, text, ... }
    'onSelect'       => '',     // JS expression, when an option is chosen: `row`
    'onError'        => '',     // JS expression, when the search fails: `error`, `status`, `data`; false = the page paints it
    'listClass'      => '',
    'dependsOn'      => '',
    'placeholder'    => '',
    'hint'           => '',
    'multiple'       => false,
    'searchable'     => false,
    'disabled'       => false,
    'required'       => false,
    'size'           => 'md',   // xs | sm | md | lg | xl
    'wrapperClass'   => '',
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
// The id that links the label to the control: the caller's, if it wrote one; else the name.
$controlId = $attributes->get('id', $name ?: null);
// Two ways of getting the options from a URL that cannot go together: say which one is meant, never pick one in silence.
if ($loadUrl !== '' && $searchUrl !== '') {
    throw new InvalidArgumentException('<x-dbl::form.select> cannot have both `load-url` (loads the whole list) and `search-url` (asks the server while the user types): choose one.');
}
if ($searchUrl !== '' && $multiple) {
    throw new InvalidArgumentException('<x-dbl::form.select> does not support `multiple` with `search-url`.');
}
$searching = $searchUrl !== '';
$textFieldName = $textName !== '' ? $textName : ($name !== '' ? $name . '_text' : '');
if ($searching) {
    // What was already chosen: its label comes from `text`; without it the value itself is shown (never an empty box with a hidden value).
    $selectedLabel = $text !== '' ? (string) $text : ($selected[0] ?? '');
}
// Ids of the list and of its options: the text box points at them (aria-controls, aria-activedescendant).
$listId = 'dbl-select-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
$searchableConfig = [
    'name' => $name, 'search' => $selectedLabel, 'chosen' => $selected[0] ?? '', 'options' => $searching ? [] : $options,
    'id' => $listId,
    'messages' => [
        'noResults' => __('No results'),
        'searching' => __('Searching...'),
        'minChars' => __('Type at least :count characters', ['count' => $minChars]),
        'empty' => __('Nothing matches that'),
        'loadFailed' => __('Could not load the results'),
        'chooseFromList' => __('Choose an option from the list'),
    ],
    'searchUrl' => $searchUrl, 'queryParam' => $queryParam, 'params' => (object) $params, 'dependsOn' => $dependsOn,
    'limit' => (int) $limit, 'minChars' => (int) $minChars, 'debounce' => (int) $debounce, 'allowFreeText' => (bool) $allowFreeText,
    'optionLabel' => $optionLabel, 'processResults' => $processResults, 'onSelect' => $onSelect, 'onError' => $onError,
];
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, x-model,
     wire:model, x-on:*, data-*...) goes to the <select> (to the text box, in `searchable` mode), and the box around
     it takes its classes from `wrapper-class`.
     The three modes announce a change as `dbl-select-changed`. None of them has a scope AROUND the control (it would hide the
     `x-ref`, `$root` and data of the caller): the scope of each is an element beside it, further down (see there). --}}
<div class="w-full {{ $wrapperClass }}">
    @if($label)
        <label class="mb-1 block text-sm font-medium" @if($controlId) for="{{ $controlId }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    @if($loadUrl)
        {{-- Remote select: dblSelectRemote asks `load-url` for the options (again each time `depends-on` changes) and writes them here.
             The <select> is OUTSIDE the element that carries the x-data of dblSelectRemote, on purpose: every Alpine expression the
             caller writes on it (`::disabled="loading"`, `x-model`...) is evaluated in the scope of the elements around it, and
             inside our scope the `loading`, `error`, `options`, `selected`... of this component would hide the caller's own.
             For the same reason it carries no Alpine attribute of ours: the caller's `x-on:change` or `:disabled` would
             be a second copy of the same attribute, and the browser keeps only the first. What Alpine needs from the
             component is written on the element below. --}}
        <select {{ $attributes->merge([
            'name' => $name ? $name . ($multiple ? '[]' : '') : null,
            'multiple' => $multiple ?: null,
            'disabled' => $disabled ?: null,
            'required' => $required ?: null,
            'id' => $controlId,
            'class' => trim('select w-full ' . $sizeClass . ' ' . ($name && isset($errors) && $errors->has($name) ? 'select-error' : '')),
        ]) }}>
            @if(!$multiple && $placeholder) <option value="">{{ $placeholder }}</option> @endif
            {{-- What the server already knows (for example the saved value): shown until the JS brings the real options, or if it never does. --}}
            @foreach($options as $optVal => $optLabel)
                <option value="{{ $optVal }}" data-initial @selected($isSelected($optVal))>{{ $optLabel }}</option>
            @endforeach
        </select>
        {{-- The element with the scope. It finds the <select> in its parent, and holds the message that shows when the options
             could not be loaded (with a retry button; the detail is in the console).
               x-on:change.window.capture  the `change` of the <select> and of the control we depend on (elsewhere in
                                           the form) reach the window; capture: before any listener of the user, which may stop it.
               x-effect                    keeps `disabled` and `aria-busy` of the <select> in step with the component state. --}}
        <div x-data="dblSelectRemote(@js(['loadUrl' => $loadUrl, 'name' => $name, 'multiple' => (bool) $multiple, 'value' => $value, 'dependsOn' => $dependsOn, 'disabled' => $disabled, 'params' => (object) $params, 'messages' => ['loadFailed' => __('Could not load the options')]]))"
             x-on:change.window.capture="onChange($event)"
             x-effect="syncField()">
            <p class="mt-1 flex items-center gap-2 text-xs text-error" role="alert" style="display: none" x-show="error">
                <span x-text="error"></span>
                <button type="button" class="link" x-on:click="retry()">{{ __('Retry') }}</button>
            </p>
        </div>
    @elseif($searchable || $searching)
        {{-- Searchable combobox. Flat {k:v} and grouped {group:{k:v}} options. --}}
        @php
        // The `x-model` of the caller (with its modifiers) is NOT for the text box: like on a native <select>, it holds the KEY
        // that is chosen. It goes on a hidden element inside the scope of the component, paired with `x-modelable`. Everything else
        // the caller writes (id, x-ref, placeholder, autocomplete, @input, @focus...) goes to the text box.
        $modelAttributes = $attributes->whereStartsWith('x-model');
        $boxAttributes = $attributes->whereDoesntStartWith('x-model');
        @endphp
        <div class="relative">
            {{-- The text box is the control of this mode, and it is OUTSIDE the scope of the component, as in the remote select: a scope
                 around it would hide the `x-ref`, the `$root` and the data of the caller. dblSelectSearch finds it in this box and listens
                 to it (typing, focus, Escape). Its text is written by the component (the x-effect below), not with :value nor x-model.
                 The model of the caller holds the chosen KEY (`''` while nothing is chosen, as a native <select>); the box shows its label. --}}
            <div class="relative">
                {{-- A combobox: ARIA roles on the box; the component keeps aria-expanded and aria-activedescendant up to date.
                     With `search-url` there are TWO fields, both in the HTML from the start: the hidden input sends the key under `name`
                     and this box sends what was typed under `{name}_text`. The server never has to guess which of the two it got. --}}
                <input {{ $boxAttributes->merge([
                    'type' => 'text',
                    'value' => $selectedLabel,
                    'placeholder' => $placeholder ?: __('Search...'),
                    'name' => $searching ? ($textFieldName ?: null) : null,
                    'disabled' => $disabled ?: null,
                    'required' => $required ?: null,
                    'id' => $controlId,
                    'role' => 'combobox',
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'list',
                    'aria-haspopup' => 'listbox',
                    'aria-expanded' => 'false',
                    'aria-controls' => $listId,
                    'class' => trim("input w-full {$sizeClass} " . ($name && isset($errors) && $errors->has($name) ? 'input-error' : '')),
                ]) }} />
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40">
                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                </div>
            </div>

            <div x-data="dblSelectSearch(@js($searchableConfig))"
                 x-effect="sync()" x-on:click.window="closeWhenOutside($event)"
                 @if($searching) x-on:change.window.capture="onChange($event)" @endif>
                <span {{ $modelAttributes->merge(['x-modelable' => 'chosen', 'hidden' => true]) }}></span>

                {{-- mousedown.prevent: pressing on the list must not take the focus from the text box (it would close the list before the click). --}}
                <div id="{{ $listId }}" role="listbox" x-show="open" x-cloak x-on:mousedown.prevent
                     class="absolute z-50 mt-1 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto {{ $listClass }}">
                    <template x-for="(item, index) in flatList" :key="index + ':' + item.type + ':' + (item.key ?? item.label)">
                        <div>
                            <div
                                x-show="item.type === 'group'"
                                role="presentation"
                                :class="item.first ? 'pt-1' : 'border-t border-base-200 mt-1 pt-1'"
                                class="px-3 pb-0.5 text-xs font-semibold text-base-content/40 uppercase tracking-wide"
                                x-text="item.label"
                            ></div>
                            <button
                                x-show="item.type === 'option'"
                                type="button"
                                tabindex="-1"
                                role="option"
                                :id="optionId(index)"
                                :aria-selected="String(index === active)"
                                @click="choose(item)"
                                @mousemove="active = index"
                                class="w-full text-left px-3 py-2 text-sm"
                                :class="[{ 'pl-5': isGrouped }, index === active ? 'bg-primary/10' : '']"
                                x-text="item.label"
                            ></button>
                        </div>
                    </template>
                    @if($searching)
                        {{-- The states, said in words: never an empty box. --}}
                        <div class="px-3 py-4 text-sm text-base-content/50 text-center" role="status" aria-live="polite" x-show="statusText" x-text="statusText"></div>
                        <div class="px-3 pb-3 text-center" x-show="hasRetry">
                            <button type="button" class="link text-sm" x-on:click="retry()">{{ __('Retry') }}</button>
                        </div>
                    @else
                        <template x-if="isEmpty">
                            <div class="px-3 py-4 text-sm text-base-content/50 text-center">{{ __('No results') }}</div>
                        </template>
                    @endif
                </div>

                @if($name)
                    <input type="hidden" x-ref="value" name="{{ $name }}" value="{{ $selected[0] ?? '' }}" @if($disabled) disabled @endif />
                @endif
            </div>
        </div>
    @else
        {{-- Native select --}}
        <select {{ $attributes->merge([
            'name' => $name ? $name . ($multiple ? '[]' : '') : null,
            'multiple' => $multiple ?: null,
            'disabled' => $disabled ?: null,
            'required' => $required ?: null,
            'id' => $controlId,
            'class' => trim('select w-full ' . $sizeClass . ' ' . ($name && isset($errors) && $errors->has($name) ? 'select-error' : '')),
        ]) }}>
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
        {{-- Announces the change of the <select> above (it finds it in its parent). Beside it, not around it. --}}
        <span hidden x-data="dblSelectAnnouncer(@js(['name' => $name]))" x-on:change.window="announce($event)"></span>
    @endif

    <x-dbl::form.field-error :name="$name" />

    @if($hint)
        <p class="mt-1 text-xs text-base-content/60">{{ $hint }}</p>
    @endif
</div>
