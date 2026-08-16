{{--
    Tipo 2: Alpine filter panel (dropdown). Dispatches 'filters-updated' event.

    @prop filters  — [ ['key'=>'status', 'type'=>'select', 'label'=>'Status', 'options'=>[...]] ]
    @prop icon     — trigger icon
    @prop position — DaisyUI dropdown position class
--}}
@props([
    'filters'    => [],
    'icon'       => 'heroicon-o-funnel',
    'position'   => 'dropdown-end',
    'label'      => '',
    'class'      => '',
])

<div
    x-data="{
        active: {},
        count() { return Object.values(this.active).filter(v => v !== '' && v !== null && v !== false).length },
        update(key, val) {
            if (val === '' || val === null) { delete this.active[key] } else { this.active[key] = val }
            this.$dispatch('filters-updated', { filters: this.active })
        },
        clear() { this.active = {}; this.$dispatch('filters-cleared') },
    }"
    class="dropdown {{ $position }} {{ $class }}"
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

                <div class="form-control">
                    <label class="label py-1">
                        <span class="label-text text-xs font-medium">{{ $fLabel }}</span>
                    </label>

                    @switch($fType)
                        @case('select')
                            <select @change="update('{{ $fKey }}', $event.target.value)" class="select select-sm w-full">
                                <option value="">{{ __('All') }}</option>
                                @foreach($filter['options'] ?? [] as $ov => $ol)
                                    <option value="{{ $ov }}">{{ $ol }}</option>
                                @endforeach
                            </select>
                        @break

                        @case('boolean')
                        @case('toggle')
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-base-content/70">{{ $filter['description'] ?? __('Enabled') }}</span>
                                <input type="checkbox" @change="update('{{ $fKey }}', $event.target.checked ? 1 : '')" class="toggle toggle-primary toggle-sm" />
                            </div>
                        @break

                        @case('date')
                            <input type="date" @change="update('{{ $fKey }}', $event.target.value)" class="input input-sm w-full" />
                        @break

                        @case('daterange')
                            <div class="flex gap-2">
                                <input type="date" @change="update('{{ $fKey }}_from', $event.target.value)" placeholder="{{ __('From') }}" class="input input-sm flex-1" />
                                <input type="date" @change="update('{{ $fKey }}_to', $event.target.value)" placeholder="{{ __('To') }}" class="input input-sm flex-1" />
                            </div>
                        @break

                        @case('number')
                            <input type="number" @input.debounce.300="update('{{ $fKey }}', $event.target.value)"
                                min="{{ $filter['min'] ?? '' }}" max="{{ $filter['max'] ?? '' }}"
                                placeholder="{{ $filter['placeholder'] ?? '' }}" class="input input-sm w-full" />
                        @break

                        @default
                            <input type="text" @input.debounce.300="update('{{ $fKey }}', $event.target.value)"
                                placeholder="{{ $filter['placeholder'] ?? '' }}" class="input input-sm w-full" />
                    @endswitch

                    @if(isset($filter['help']))
                        <label class="label py-1"><span class="label-text-alt text-xs text-base-content/60">{{ $filter['help'] }}</span></label>
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
