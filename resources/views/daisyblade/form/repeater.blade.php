{{--
    Repeater: renders a dynamic list of sub-form rows.

    Props:
      name      — HTML array name (e.g. 'installments')
      label     — optional section label
      fields    — array of sub-field defs: [['key'=>'x','label'=>'X','type'=>'text','cols'=>4], ...]
      value     — initial rows array (for edit forms)
      mode      — 'form' (HTML POST, default) | 'alpine' (syncs into parent scope via alpineData)
      alpineData — parent Alpine data path used in alpine mode (default 'data')
      addLabel  — override the Add button text
      min       — minimum rows (0 = no minimum)
      max       — maximum rows (0 = unlimited)
--}}
@props([
    'name'       => 'items',
    'label'      => null,
    'fields'     => [],
    'value'      => null,
    'mode'       => 'form',
    'alpineData' => 'data',
    'addLabel'   => null,
    'min'        => 0,
    'max'        => 0,
])
@php
    $addLabel ??= __('Add row');
    $rows      = !empty($value) ? array_values($value) : [[]];
    $emptyRow  = array_fill_keys(array_column($fields, 'key'), '');
    $fieldDefs = array_values($fields);
@endphp

<div
    x-data="{
        rows: {{ Js::from($rows) }},
        add()      { this.rows.push({{ Js::from($emptyRow) }}) },
        remove(i)  { if ({{ (int)$min }} === 0 || this.rows.length > {{ (int)$min }}) this.rows.splice(i, 1) },
        canRemove  : {{ (int)$min }} === 0 || rows.length > {{ (int)$min }},
        canAdd     : {{ (int)$max }} === 0 || rows.length < {{ (int)$max }},
    }"
    @if($mode === 'alpine') x-effect="{{ $alpineData }}.{{ $name }} = rows" @endif
    class="space-y-2"
>
    @if($label)
        <label class="label py-0.5">
            <span class="label-text font-medium">{{ $label }}</span>
        </label>
    @endif

    <template x-for="(row, index) in rows" :key="index">
        <div class="relative border border-base-300 rounded-lg p-3 bg-base-100/50">

            {{-- Remove button: top-right corner --}}
            <button
                type="button"
                @click="remove(index)"
                x-show="{{ (int)$min }} === 0 || rows.length > {{ (int)$min }}"
                class="absolute top-1.5 right-1.5 btn btn-ghost btn-xs btn-square text-error opacity-50 hover:opacity-100"
                title="{{ __('Remove row') }}"
            >
                <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
            </button>

            <div class="grid grid-cols-12 gap-3 pr-6">
                @foreach($fieldDefs as $field)
                    @php
                        $fKey   = $field['key']   ?? '';
                        $fType  = $field['type']  ?? 'text';
                        $fLbl   = $field['label'] ?? ucfirst(str_replace('_', ' ', $fKey));
                        $fCols  = $field['cols']  ?? 4;
                        $fCols  = is_numeric($fCols) ? 'col-span-' . (int)$fCols : $fCols;
                        $fPh    = $field['placeholder'] ?? '';
                        $fOpts  = $field['options'] ?? [];
                        $inputType = match($fType) {
                            'number', 'money', 'percentage' => 'number',
                            'date'     => 'date',
                            'datetime' => 'datetime-local',
                            'email'    => 'email',
                            default    => 'text',
                        };
                    @endphp
                    <div class="{{ $fCols }}">
                        <div class="form-control w-full">
                            @if($fLbl)
                                <label class="label py-0.5">
                                    <span class="label-text text-xs text-base-content/70">{{ $fLbl }}</span>
                                </label>
                            @endif

                            @if(in_array($fType, ['toggle', 'boolean', 'checkbox']))
                                @if($mode === 'form')
                                    <input type="hidden"
                                        :name="`{{ $name }}[${index}][{{ $fKey }}]`"
                                        :value="row['{{ $fKey }}'] ? '1' : '0'"
                                    />
                                @endif
                                <label class="label cursor-pointer justify-start gap-2 py-2">
                                    <input
                                        type="checkbox"
                                        x-model="row['{{ $fKey }}']"
                                        class="{{ $fType === 'toggle' ? 'toggle toggle-primary toggle-sm' : 'checkbox checkbox-primary checkbox-sm' }}"
                                    />
                                </label>

                            @elseif($fType === 'select')
                                <select
                                    @if($mode === 'form') :name="`{{ $name }}[${index}][{{ $fKey }}]`" @endif
                                    x-model="row['{{ $fKey }}']"
                                    class="select select-bordered select-sm w-full"
                                >
                                    <option value="">{{ $fPh ?: '—' }}</option>
                                    @foreach($fOpts as $optVal => $optLabel)
                                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>

                            @elseif($fType === 'textarea')
                                <textarea
                                    @if($mode === 'form') :name="`{{ $name }}[${index}][{{ $fKey }}]`" @endif
                                    x-model="row['{{ $fKey }}']"
                                    rows="{{ $field['rows'] ?? 2 }}"
                                    placeholder="{{ $fPh }}"
                                    class="textarea textarea-bordered textarea-sm w-full"
                                ></textarea>

                            @else
                                <input
                                    type="{{ $inputType }}"
                                    @if($mode === 'form') :name="`{{ $name }}[${index}][{{ $fKey }}]`" @endif
                                    x-model="row['{{ $fKey }}']"
                                    placeholder="{{ $fPh }}"
                                    @isset($field['step']) step="{{ $field['step'] }}" @endisset
                                    @isset($field['field_min']) min="{{ $field['field_min'] }}" @endisset
                                    @isset($field['field_max']) max="{{ $field['field_max'] }}" @endisset
                                    class="input input-bordered input-sm w-full"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </template>

    <button
        type="button"
        @click="add()"
        x-show="{{ (int)$max }} === 0 || rows.length < {{ (int)$max }}"
        class="btn btn-ghost btn-sm gap-1.5 text-primary"
    >
        <x-heroicon-o-plus class="w-4 h-4" />
        {{ $addLabel }}
    </button>
</div>
