{{--
    Schema-driven field renderer. Two modes:
      mode='form'   — standard HTML POST: name, value, @error
      mode='alpine' — Alpine x-model + JSON errors from the server

    $fields:       [ 'fieldKey' => ['type'=>'text', 'label'=>'...', 'cols'=>'col-span-6', ...] ]
    $values:       [ 'fieldKey' => 'value' ]  (form mode prefill)
    $alpineData:   Alpine object path for field values (default 'data')
    $alpineErrors: Alpine object path for errors    (default 'errors')
--}}
@props([
    'fields'       => [],
    'mode'         => 'form',
    'values'       => [],
    'alpineData'   => 'data',
    'alpineErrors' => 'errors',
])

{{-- A fragment: many cells and no root of its own, because it goes inside the grid of its parent. `display: contents`
     makes this wrapper disappear from the layout while still being the element that takes `$attributes`.
     (`$attributes` does not exist when a view `@include`s this file instead of using the tag.) --}}
<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'contents']) }}>
@foreach($fields as $key => $field)
    @php
        $ft          = $field['type'] ?? 'text';
        $colsRaw     = $field['cols'] ?? null;
        if (is_null($colsRaw)) {
            $typeDefault = match($ft) {
                'toggle', 'boolean', 'checkbox' => 3,
                'number', 'money', 'percentage', 'decimal', 'date', 'datetime' => 4,
                'textarea' => 12,
                default => 6,
            };
            $colsRaw = function_exists('theme_config') ? theme_config('form.cols.' . $ft, $typeDefault) : $typeDefault;
        }
        // Clases enteras y no 'col-span-'.$n: Tailwind solo genera lo que encuentra literal al
        // escanear. Una clase construida en ejecución no llega al CSS, y entonces el ancho de
        // columna no se aplica y todos los campos caen en la misma fila.
        $cols        = is_numeric($colsRaw) ? match ((int) $colsRaw) {
            1 => 'col-span-1',   2 => 'col-span-2',   3 => 'col-span-3',
            4 => 'col-span-4',   5 => 'col-span-5',   6 => 'col-span-6',
            7 => 'col-span-7',   8 => 'col-span-8',   9 => 'col-span-9',
            10 => 'col-span-10', 11 => 'col-span-11', default => 'col-span-12',
        } : $colsRaw;
        $label       = $field['label'] ?? ucwords(str_replace(['_', '.'], ' ', $key));
        $placeholder = $field['placeholder'] ?? '';
        $required    = !empty($field['required']);
        $disabled    = !empty($field['disabled']);
        $options     = $field['options'] ?? [];
        $multiple    = in_array($ft, ['multiselect','tags']) || !empty($field['multiple']);
        $value       = old($key, $values[$key] ?? ($field['default'] ?? ''));
        $inputType   = match($ft) {
            'email'    => 'email',
            'password' => 'password',
            'number', 'money', 'percentage' => 'number',
            'date'     => 'date',
            'datetime' => 'datetime-local',
            'color'    => 'color',
            'file'     => 'file',
            default    => 'text',
        };
        $attrs = $field['attrs'] ?? [];
        // See form/repeater: a localised decimal cannot be <input type="number">, because the
        // browser blanks the value when the separator is not the one it expects.
        if ($ft === 'decimal') {
            $attrs = array_merge(['inputmode' => 'decimal'], $attrs);
        }
        // Not rendered yet: each control joins its own classes to the `class` of the schema (see Attrs::render).
        $aD = $alpineData;
        $aE = $alpineErrors;
        // `$errors` is shared by Laravel's web middleware; a view rendered without it (a console command, a mail, a test) gets an empty bag.
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    @endphp

    {{-- `hidden` sale ANTES del <div> de la celda: un campo oculto no ocupa columna, no lleva
         etiqueta y no se puede tocar. Sin esto caía al `default => 'text'` de arriba y una clave
         ajena —el id de la fila, el del alumno— se pintaba como una caja de texto editable
         estrecha, encajada entre dos etiquetas. Además de feo, es peligroso: quien la edite
         reapunta el registro a otro padre.

         En modo Alpine no se pinta NADA a propósito: el valor ya viaja en el estado del
         formulario (`values`), que es lo que se envía. Un input suelto solo podría desincronizarse
         de él. En modo form sí hace falta, porque ahí lo que se manda es el HTML. --}}
    @if($ft === 'hidden')
        @if($mode === 'form')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
        @endif
        @continue
    @endif

    <div class="{{ $cols }}">
        @switch($ft)

            @case('textarea')
                <div class="w-full">
                    @if($label)
                        <label class="mb-1 block text-sm font-medium" @if($mode==='form') for="{{ $key }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
                    @endif
                    <textarea
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}"
                        @else
                            x-model="{{ $aD }}[@js($key)]"
                            :class="{ 'textarea-error': ({{ $aE }}[@js($key)]||[]).length }"
                        @endif
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        {{ \Edumicro\DaisyBlade\Support\Attrs::render($attrs, 'textarea w-full '.($mode==='form' && $errors->has($key) ? 'textarea-error' : ''), ['placeholder' => $placeholder, 'rows' => $field['rows'] ?? 4]) }}
                    >@if($mode==='form'){{ $value }}@endif</textarea>
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>
            @break

            @case('checkbox')
            @case('boolean')
                <div class="w-full">
                    @if($mode==='form') <input type="hidden" name="{{ $key }}" value="0" /> @endif
                    <label class="flex w-full cursor-pointer items-center justify-start gap-3 py-1">
                        <input
                            type="checkbox"
                            @if($mode==='form')
                                id="{{ $key }}" name="{{ $key }}" value="1" @checked((bool)$value)
                            @else
                                x-model="{{ $aD }}[@js($key)]"
                            @endif
                            @if($disabled) disabled @endif
                            class="checkbox checkbox-primary"
                        />
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </label>
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>
            @break

            @case('toggle')
                <div class="w-full">
                    @if($mode==='form') <input type="hidden" name="{{ $key }}" value="0" /> @endif
                    {{-- `gap-3` + `min-w-0` en el texto + `shrink-0` en el interruptor. Sin esto,
                         en una columna estrecha una etiqueta larga («Pide nº Seg. Social» en un
                         col-span-3) empuja contra el interruptor y acaban montados: `justify-between`
                         reparte el espacio sobrante, pero cuando no sobra ninguno no separa nada. --}}
                    <label class="flex w-full cursor-pointer items-center justify-between gap-3">
                        <span class="text-sm font-medium min-w-0">{{ $label }}</span>
                        <input
                            type="checkbox"
                            @if($mode==='form')
                                name="{{ $key }}" value="1" @checked((bool)$value)
                            @else
                                x-model="{{ $aD }}[@js($key)]"
                            @endif
                            @if($disabled) disabled @endif
                            class="toggle toggle-primary shrink-0"
                        />
                    </label>
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>
            @break

            @case('radio')
                <div class="w-full">
                    @if($label)
                        <label class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    @endif
                    <div class="space-y-1">
                        @foreach($options as $optVal => $optLabel)
                            <label class="flex w-full cursor-pointer items-center justify-start gap-3 py-1">
                                <input
                                    type="radio"
                                    value="{{ $optVal }}"
                                    @if($mode==='form')
                                        name="{{ $key }}" @checked((string)$value === (string)$optVal)
                                    @else
                                        x-model="{{ $aD }}[@js($key)]"
                                    @endif
                                    class="radio radio-primary"
                                />
                                <span class="text-sm">{{ $optLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>
            @break

            @case('select')
            @case('multiselect')
            @case('tags')
            @case('relation')
                <div class="w-full">
                    @if($label)
                        <label class="mb-1 block text-sm font-medium" @if($mode==='form') for="{{ $key }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
                    @endif
                    <select
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}{{ $multiple ? '[]' : '' }}"
                        @else
                            x-model="{{ $aD }}[@js($key)]"
                            :class="{ 'select-error': ({{ $aE }}[@js($key)]||[]).length }"
                        @endif
                        @if($multiple) multiple @endif
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        {{ \Edumicro\DaisyBlade\Support\Attrs::render($attrs, 'select w-full '.($mode==='form' && $errors->has($key) ? 'select-error' : '')) }}
                    >
                        @if(!$multiple)
                            <option value="">{{ $placeholder ?: __('Select...') }}</option>
                        @endif
                        @foreach($options as $optVal => $optLabel)
                            @if(is_array($optLabel))
                                <optgroup label="{{ $optVal }}">
                                    @foreach($optLabel as $gv => $gl)
                                        <option value="{{ $gv }}" @if($mode==='form') @selected($multiple ? in_array((string)$gv, (array)$value) : (string)$value===(string)$gv) @endif>{{ $gl }}</option>
                                    @endforeach
                                </optgroup>
                            @else
                                <option value="{{ $optVal }}" @if($mode==='form') @selected($multiple ? in_array((string)$optVal,(array)$value) : (string)$value===(string)$optVal) @endif>{{ $optLabel }}</option>
                            @endif
                        @endforeach
                    </select>
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>
            @break

            @case('repeater')
                <x-dbl::form.repeater
                    :name="$key"
                    :label="$label"
                    :fields="$field['fields'] ?? []"
                    :value="is_array($value) ? $value : []"
                    :mode="$mode"
                    :alpineData="$aD"
                    :addLabel="$field['add_label'] ?? null"
                    :min="$field['min'] ?? 0"
                    :max="$field['max'] ?? 0"
                    :events="$field['events'] ?? []"
                />
            @break

            @default
                <div class="w-full">
                    @if($label)
                        <label class="mb-1 block text-sm font-medium" @if($mode==='form') for="{{ $key }}" @endif>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
                    @endif
                    <input
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}"
                            @if(!in_array($inputType, ['password','file'])) value="{{ $value }}" @endif
                        @else
                            x-model="{{ $aD }}[@js($key)]"
                            :class="{ 'input-error': ({{ $aE }}[@js($key)]||[]).length }"
                        @endif
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        {{ \Edumicro\DaisyBlade\Support\Attrs::render($attrs, 'input w-full '.($mode==='form' && $errors->has($key) ? 'input-error' : ''), ['type' => $inputType, 'placeholder' => $placeholder]) }}
                    />
                    <x-dbl::form.field-error :name="$key" :mode="$mode" :alpine-errors="$aE" />
                </div>

        @endswitch
    </div>
@endforeach
</div>
