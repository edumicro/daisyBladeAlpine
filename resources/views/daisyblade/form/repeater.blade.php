{{--
    Repeater: renders a dynamic list of sub-form rows.

    Props:
      name      — HTML array name (e.g. 'installments')
      label     — optional section label
      fields    — array of sub-field defs: [['key'=>'x','label'=>'X','type'=>'text','cols'=>4], ...]
                  'type'  => text | decimal | number | money | percentage | date | datetime |
                             email | select | textarea | toggle | boolean | checkbox
                  'attrs' => raw HTML attributes for the input, e.g. ['autocomplete'=>'off']
      value     — initial rows array (for edit forms)
      mode      — 'form' (HTML POST, default) | 'alpine' (syncs into parent scope via alpineData)
      alpineData — parent Alpine data path used in alpine mode (default 'data')
      addLabel  — override the Add button text
      min       — minimum rows (0 = no minimum)
      max       — maximum rows (0 = unlimited)
      events    — Alpine bindings per target, merged over this component's defaults:
                  ['root'|'row'|'add'|'remove' => ['keydown' => ['enter' => 'add()']]]
                  Dotted keys work too: ['root' => ['keydown.enter.prevent' => 'add()']].
                  In scope: rows, add(), remove(index), notify() — plus index inside a row.

    Dispatches `dbl-repeater-change` ({ name, rows }) whenever the rows change, so a parent can
    react without reaching into this scope:  <div @dbl-repeater-change.debounce.500ms="save()">
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
    'events'     => [],
])
@php
    use Edumicro\DaisyBlade\Support\Attrs;
    use Edumicro\DaisyBlade\Support\Bindings;

    $addLabel  ??= __('Add row');
    $fieldDefs = array_values($fields);

    // Every new row starts from the field defaults, not from blanks: a select whose default only
    // applies to the first row is a default the user stops seeing the moment they add a second.
    $emptyRow = [];
    foreach ($fieldDefs as $fieldDef) {
        $emptyRow[$fieldDef['key'] ?? ''] = $fieldDef['default'] ?? '';
    }

    $rows = !empty($value) ? array_values($value) : [$emptyRow];

    $bind = fn (string $target, array $defaults = []) => Bindings::render($defaults, $events[$target] ?? []);
@endphp

<div
    x-data="{
        rows: {{ Js::from($rows) }},
        add()      { this.rows.push({{ Js::from($emptyRow) }}); this.notify() },
        remove(i)  { if ({{ (int)$min }} === 0 || this.rows.length > {{ (int)$min }}) { this.rows.splice(i, 1); this.notify() } },
        notify()   { this.$dispatch('dbl-repeater-change', { name: {{ Js::from($name) }}, rows: this.rows }) },
    }"
    @if($mode === 'alpine') x-effect="{{ $alpineData }}.{{ $name }} = rows" @endif
    {{ $bind('root', ['input' => 'notify()']) }}
    class="space-y-2"
>
    @if($label)
        <label class="mb-1 block text-sm font-medium">{{ $label }}</label>
    @endif

    <template x-for="(row, index) in rows" :key="index">
        <div {{ $bind('row') }} class="relative border border-base-300 rounded-lg p-3 bg-base-100/50">

            {{-- Remove button: top-right corner --}}
            <button
                type="button"
                {{ $bind('remove', ['click' => 'remove(index)']) }}
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
                        // Ver form/fields: clases enteras, no construidas en ejecución.
                        $fCols  = is_numeric($fCols) ? match ((int) $fCols) {
                            1 => 'col-span-1',   2 => 'col-span-2',   3 => 'col-span-3',
                            4 => 'col-span-4',   5 => 'col-span-5',   6 => 'col-span-6',
                            7 => 'col-span-7',   8 => 'col-span-8',   9 => 'col-span-9',
                            10 => 'col-span-10', 11 => 'col-span-11', default => 'col-span-12',
                        } : $fCols;
                        $fPh    = $field['placeholder'] ?? '';
                        $fOpts  = $field['options'] ?? [];
                        $fAttrs = $field['attrs'] ?? [];
                        $inputType = match($fType) {
                            'number', 'money', 'percentage' => 'number',
                            'date'     => 'date',
                            'datetime' => 'datetime-local',
                            'email'    => 'email',
                            default    => 'text',
                        };
                        // A localised decimal cannot be an <input type="number">: where the locale
                        // separator is not a dot, the browser sanitises the value to '' and the
                        // digits the user typed never reach the server. Text plus inputmode keeps
                        // the numeric keypad on mobile and lets the backend parse its own locale.
                        if ($fType === 'decimal') {
                            $fAttrs = array_merge(['inputmode' => 'decimal'], $fAttrs);
                        }
                    @endphp
                    {{-- Igual que en form/fields: un `hidden` no ocupa celda ni lleva etiqueta.
                         En el repeater importa todavía más, porque la clave que se colaba era el
                         `id` de la fila: editarlo a mano hace que al guardar se pise OTRA fila. --}}
                    @if($fType === 'hidden')
                        @if($mode === 'form')
                            <input type="hidden"
                                :name="`{{ $name }}[${index}][{{ $fKey }}]`"
                                :value="row['{{ $fKey }}']"
                            />
                        @endif
                        @continue
                    @endif

                    <div class="{{ $fCols }}">
                        <div class="w-full">
                            @if($fLbl)
                                <label class="mb-1 block text-xs text-base-content/70">{{ $fLbl }}</label>
                            @endif

                            @if(in_array($fType, ['toggle', 'boolean', 'checkbox']))
                                @if($mode === 'form')
                                    <input type="hidden"
                                        :name="`{{ $name }}[${index}][{{ $fKey }}]`"
                                        :value="row['{{ $fKey }}'] ? '1' : '0'"
                                    />
                                @endif
                                <label class="flex w-full cursor-pointer items-center justify-start gap-2 py-2">
                                    <input
                                        type="checkbox"
                                        x-model="row['{{ $fKey }}']"
                                        {{ Attrs::render($fAttrs) }}
                                        class="{{ $fType === 'toggle' ? 'toggle toggle-primary toggle-sm' : 'checkbox checkbox-primary checkbox-sm' }}"
                                    />
                                </label>

                            @elseif($fType === 'select')
                                <select
                                    @if($mode === 'form') :name="`{{ $name }}[${index}][{{ $fKey }}]`" @endif
                                    x-model="row['{{ $fKey }}']"
                                    {{ Attrs::render($fAttrs) }}
                                    class="select select-sm w-full"
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
                                    {{ Attrs::render($fAttrs) }}
                                    class="textarea textarea-sm w-full"
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
                                    {{ Attrs::render($fAttrs) }}
                                    class="input input-sm w-full"
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
        {{ $bind('add', ['click' => 'add()']) }}
        x-show="{{ (int)$max }} === 0 || rows.length < {{ (int)$max }}"
        class="btn btn-ghost btn-sm gap-1.5 text-primary"
    >
        <x-heroicon-o-plus class="w-4 h-4" />
        {{ $addLabel }}
    </button>
</div>
