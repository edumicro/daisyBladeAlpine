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
@endphp

{{-- The main element of a form control is the control itself: everything the caller writes (class, x-model,
     wire:model, x-on:*, data-*...) goes to the <select> (to the text box, in `searchable` mode), and the box around
     it takes its classes from `wrapper-class`.
     The native select announces its change as `dbl-select-changed`, like the other two (this box is the root it is
     dispatched from). The remote select has its Alpine scope in an element of its own, further down (see there). --}}
<div class="w-full {{ $wrapperClass }}"
     @if(! $loadUrl && ! $searchable)
         x-data
         x-on:change="$dispatch('dbl-select-changed', { name: @js($name), value: $event.target.multiple ? [...$event.target.selectedOptions].map(option => option.value) : $event.target.value })"
     @endif>
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
        <select
            @if($name) name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['id' => $controlId, 'class' => trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error' : ''))]) }}
        >
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
        <div x-data="dblSelectRemote(@js(['loadUrl' => $loadUrl, 'name' => $name, 'multiple' => (bool) $multiple, 'value' => $value, 'dependsOn' => $dependsOn, 'disabled' => $disabled, 'messages' => ['loadFailed' => __('Could not load the options')]]))"
             x-on:change.window.capture="onChange($event)"
             x-effect="syncField()">
            <p class="mt-1 flex items-center gap-2 text-xs text-error" role="alert" style="display: none" x-show="error">
                <span x-text="error"></span>
                <button type="button" class="link" x-on:click="retry()">{{ __('Retry') }}</button>
            </p>
        </div>
    @elseif($searchable)
        {{-- Alpine searchable combobox — soporta flat {k:v} y grouped {group:{k:v}} --}}
        @php
        // The `x-model` of the caller (with its modifiers) is NOT for the text box: like on a native <select>, it holds the KEY
        // that is chosen. It goes on the box around everything, which is also the Alpine scope, paired with `x-modelable`
        // (that is why the tag below does not hold `$attributes`: it only gets the model). Everything else the caller writes
        // (placeholder, autocomplete, @input, @focus...) goes to the text box.
        $modelAttributes = $attributes->whereStartsWith('x-model');
        $boxAttributes = $attributes->whereDoesntStartWith('x-model');
        @endphp
        <div x-data="dblSelectSearch(@js(['name' => $name, 'search' => $selectedLabel, 'chosen' => $selected[0] ?? '', 'options' => $options]))"
             x-modelable="chosen" {{ $modelAttributes }} class="relative" @click.outside="open = false">
            {{-- The text box is the control of this mode: it takes the attributes of the caller. So it carries none of ours that
                 the caller could also write, since the browser keeps only the first of two equal attributes and the caller's
                 would vanish: its own `@input` and `@focus` would be exactly that. They go on the box around it instead:
                 `input` bubbles up to here, and so does `focusin` (`focus` does not). The text is written from the box
                 around it (the x-effect), not with :value nor x-model. The model of the caller holds the chosen KEY
                 (`''` while nothing is chosen, as a native <select>), and the text box shows its label. --}}
            {{-- Escape closes the list. The `input` stops here (the x-model of the box above would take the typed text as the key)
                 and typed() / choose() send the form a new one that carries the chosen key. --}}
            <div class="relative" x-on:input.stop="typed($event.target.value)" x-on:focusin="open = true"
                 x-on:keydown.escape="open = false"
                 x-effect="sync()">
                <input
                    type="text"
                    value="{{ $selectedLabel }}"
                    placeholder="{{ $placeholder ?: __('Search...') }}"
                    @if($disabled) disabled @endif
                    @if($required) required @endif
                    {{ $boxAttributes->merge(['id' => $controlId, 'class' => "input w-full {$sizeClass}"]) }}
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
                            @click="choose(item)"
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
                <input type="hidden" x-ref="value" name="{{ $name }}" value="{{ $selected[0] ?? '' }}" @if($disabled) disabled @endif />
            @endif
        </div>
    @else
        {{-- Native select --}}
        <select
            @if($name) name="{{ $name }}{{ $multiple ? '[]' : '' }}" @endif
            @if($multiple) multiple @endif
            @if($disabled) disabled @endif
            @if($required) required @endif
            {{ $attributes->merge(['id' => $controlId, 'class' => trim('select w-full ' . $sizeClass . ' '
                . ($name && isset($errors) && $errors->has($name) ? 'select-error' : ''))]) }}
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
