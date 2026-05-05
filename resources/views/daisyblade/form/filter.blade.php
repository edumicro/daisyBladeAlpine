{{--
    Inline filter bar. Dispatches 'filter-apply' or redirects to applyUrl with params.

    @prop fields    — [ ['name'=>'status','type'=>'select','label'=>'Estado','options'=>[...]] ]
    @prop applyUrl  — redirect URL with query params (optional; dispatches event otherwise)
    @prop method    — GET (default)
--}}
@props([
    'fields'   => [],
    'applyUrl' => '',
    'method'   => 'GET',
    'label'    => '',
    'class'    => '',
])

<div
    x-data="{
        values: {},
        applyUrl: '{{ $applyUrl }}',
        apply() {
            const qs = new URLSearchParams(Object.fromEntries(Object.entries(this.values).filter(([,v])=>v!==''&&v!==null)))
            if (this.applyUrl) {
                window.location.href = this.applyUrl + (this.applyUrl.includes('?') ? '&' : '?') + qs.toString()
            } else {
                this.$dispatch('filter-apply', { values: this.values, queryString: qs.toString() })
            }
        },
        clear() {
            this.values = {}
            this.$dispatch('filter-clear')
        }
    }"
    class="{{ $class }}"
>
    @if($label)
        <h3 class="font-semibold mb-3">{{ $label }}</h3>
    @endif

    <div class="flex flex-wrap gap-3 items-end">
        @foreach($fields as $field)
            @php
                $fName  = $field['name'] ?? '';
                $fType  = $field['type'] ?? 'text';
                $fLabel = $field['label'] ?? ucfirst($fName);
            @endphp

            <div class="form-control min-w-36">
                <label class="label py-1">
                    <span class="label-text text-xs font-medium">{{ $fLabel }}</span>
                </label>

                @switch($fType)
                    @case('select')
                        <select x-model="values.{{ $fName }}" @change="apply()" class="select select-bordered select-sm">
                            <option value="">{{ __('All') }}</option>
                            @foreach($field['options'] ?? [] as $ov => $ol)
                                <option value="{{ $ov }}">{{ $ol }}</option>
                            @endforeach
                        </select>
                    @break

                    @case('boolean')
                    @case('toggle')
                        <label class="label cursor-pointer gap-2 justify-start">
                            <input type="checkbox" x-model="values.{{ $fName }}" @change="apply()" class="toggle toggle-primary toggle-sm" />
                            <span class="label-text text-xs">{{ $field['description'] ?? __('Yes') }}</span>
                        </label>
                    @break

                    @case('date')
                        <input type="date" x-model="values.{{ $fName }}" @change="apply()" class="input input-bordered input-sm" />
                    @break

                    @default
                        <input type="text" x-model="values.{{ $fName }}" @input.debounce.400="apply()"
                            placeholder="{{ $field['placeholder'] ?? '' }}" class="input input-bordered input-sm" />
                @endswitch
            </div>
        @endforeach

        <div class="flex gap-2 pb-0.5">
            <button type="button" @click="apply()" class="btn btn-primary btn-sm">{{ __('Apply') }}</button>
            <button type="button" @click="clear()" class="btn btn-ghost btn-sm">{{ __('Clear') }}</button>
        </div>
    </div>
</div>
