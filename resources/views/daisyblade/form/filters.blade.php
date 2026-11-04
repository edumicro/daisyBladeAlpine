{{--
    Filter panel (dropdown). The only filter component of the package.

    Every change of the user is announced at once, so a table (or anything else) can listen:
      `dbl-filters-applied` { filters }  — all the filters that are on now, as { key: value }
      `dbl-filters-cleared` { filters }  — the user cleared them all; `filters` is empty
    `display.data-table` listens to these two events, and to nothing else.

    @prop filters  — [ ['key'=>'status', 'type'=>'select', 'label'=>'Status', 'options'=>[...]] ]
                     type: text (default) | number | select | date | daterange | boolean (or toggle)
                     number: min, max · text and number: placeholder · boolean: text (beside the switch)
                     any type: hint (help text under the control)
                     daterange puts two keys in `filters`: `<key>_from` and `<key>_to`
    @prop icon     — icon of the button that opens the panel
    @prop position — DaisyUI dropdown position class
    @prop label    — text of the button (default: «Filters»)
--}}
@props([
    'filters'  => [],
    'icon'     => 'heroicon-o-funnel',
    'position' => 'dropdown-end',
    'label'    => '',
])

{{-- The Alpine object has no data from PHP, so it can stay inline. The keys of the filters do come from PHP:
     they are written with @js() into the handlers, never inside a JavaScript string with {{ }}. --}}
<div
    x-data="{
        filters: {},
        count() { return Object.values(this.filters).filter(v => v !== '' && v !== null && v !== false).length },
        update(key, val) {
            if (val === '' || val === null) { delete this.filters[key] } else { this.filters[key] = val }
            this.$dispatch('dbl-filters-applied', { filters: this.filters })
        },
        clear() {
            this.filters = {}
            // The controls only tell `filters` what changed, they are not bound to it: emptying `filters` would leave the old
            // text in the boxes. So they are emptied here (the option All of a select has an empty value).
            this.$root.querySelectorAll('select, input').forEach(control => {
                if (control.type === 'checkbox') control.checked = false
                else control.value = ''
            })
            this.$dispatch('dbl-filters-cleared', { filters: {} })
        },
    }"
    {{ $attributes->merge(['class' => "dropdown {$position}"]) }}
>
    <label tabindex="0" class="btn btn-sm btn-ghost gap-2">
        <x-dynamic-component :component="$icon" class="w-4 h-4" />
        @if($label) <span>{{ $label }}</span>
        @else <span>{{ __('Filters') }}</span>
        @endif
        <span x-show="count() > 0" x-cloak class="badge badge-primary badge-sm" x-text="count()"></span>
    </label>

    <div tabindex="0" class="dropdown-content z-50 card card-sm w-80 p-4 shadow-lg bg-base-100 border border-base-300 mt-2">
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-base-300">
                <h3 class="font-semibold text-sm">{{ __('Filter options') }}</h3>
                <button type="button" x-show="count() > 0" x-cloak @click="clear()" class="btn btn-ghost btn-xs">
                    {{ __('Clear all') }}
                </button>
            </div>

            @forelse($filters as $filter)
                @php
                    $fKey   = $filter['key'] ?? '';
                    $fType  = $filter['type'] ?? 'text';
                    $fLabel = $filter['label'] ?? ucfirst($fKey);
                @endphp

                <div class="w-full">
                    <label class="mb-1 block font-medium text-xs">{{ $fLabel }}</label>

                    @switch($fType)
                        @case('select')
                            <select @change="update(@js($fKey), $event.target.value)" class="select select-sm w-full">
                                <option value="">{{ __('All') }}</option>
                                @foreach($filter['options'] ?? [] as $ov => $ol)
                                    <option value="{{ $ov }}">{{ $ol }}</option>
                                @endforeach
                            </select>
                        @break

                        @case('boolean')
                        @case('toggle')
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-base-content/70">{{ $filter['text'] ?? __('Enabled') }}</span>
                                <input type="checkbox" @change="update(@js($fKey), $event.target.checked ? 1 : '')" class="toggle toggle-primary toggle-sm" />
                            </div>
                        @break

                        @case('date')
                            <input type="date" @change="update(@js($fKey), $event.target.value)" class="input input-sm w-full" />
                        @break

                        @case('daterange')
                            <div class="flex gap-2">
                                <input type="date" @change="update(@js($fKey.'_from'), $event.target.value)" placeholder="{{ __('From') }}" class="input input-sm flex-1" />
                                <input type="date" @change="update(@js($fKey.'_to'), $event.target.value)" placeholder="{{ __('To') }}" class="input input-sm flex-1" />
                            </div>
                        @break

                        @case('number')
                            <input type="number" @input.debounce.300="update(@js($fKey), $event.target.value)"
                                min="{{ $filter['min'] ?? '' }}" max="{{ $filter['max'] ?? '' }}"
                                placeholder="{{ $filter['placeholder'] ?? '' }}" class="input input-sm w-full" />
                        @break

                        @default
                            <input type="text" @input.debounce.300="update(@js($fKey), $event.target.value)"
                                placeholder="{{ $filter['placeholder'] ?? '' }}" class="input input-sm w-full" />
                    @endswitch

                    @if(isset($filter['hint']))
                        <p class="mt-1 text-xs text-base-content/60">{{ $filter['hint'] }}</p>
                    @endif
                </div>
            @empty
                <div class="text-center py-4 text-sm text-base-content/50">{{ __('No filters available') }}</div>
            @endforelse

            <div class="pt-2 border-t border-base-300 text-xs text-base-content/50 text-right" x-show="count() > 0" x-cloak>
                <span x-text="count()"></span>&nbsp;{{ __('active') }}
            </div>
        </div>
    </div>
</div>
