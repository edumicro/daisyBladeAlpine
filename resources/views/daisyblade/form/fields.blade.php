{{--
    Schema-driven field renderer. Two modes:
      mode='form'   — standard HTML POST: name, value, @error
      mode='alpine' — Alpine x-model + JSON errors from Axios

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
    'alphaErrors'  => 'errors',
])

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
        $cols        = is_numeric($colsRaw) ? 'col-span-' . (int)$colsRaw : $colsRaw;
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
        $attrs = Edumicro\DaisyBlade\Support\Attrs::render($attrs);
        $aD = $alpineData;
        $aE = $alphaErrors;
    @endphp

    <div class="{{ $cols }}">
        @switch($ft)

            @case('textarea')
                <div class="form-control w-full">
                    @if($label)
                        <label class="label" @if($mode==='form') for="{{ $key }}" @endif>
                            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
                        </label>
                    @endif
                    <textarea
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}"
                        @else
                            x-model="{{ $aD }}.{{ $key }}"
                            :class="{ 'textarea-error': ({{ $aE }}['{{ $key }}']||[]).length }"
                        @endif
                        placeholder="{{ $placeholder }}"
                        {{ $attrs }}
                        rows="{{ $field['rows'] ?? 4 }}"
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        class="textarea w-full @if($mode==='form' && $errors->has($key)) textarea-error @endif"
                    >@if($mode==='form'){{ $value }}@endif</textarea>
                    @if($mode==='form')
                        @error($key) <label class="label p-0 mt-1"><span class="label-text-alt text-error">{{ $message }}</span></label> @enderror
                    @else
                        <label class="label p-0 mt-1" x-show="({{ $aE }}['{{ $key }}']||[]).length" x-cloak>
                            <span class="label-text-alt text-error" x-text="({{ $aE }}['{{ $key }}']||[])[0]"></span>
                        </label>
                    @endif
                </div>
            @break

            @case('checkbox')
            @case('boolean')
                <div class="form-control w-full">
                    @if($mode==='form') <input type="hidden" name="{{ $key }}" value="0" /> @endif
                    <label class="label cursor-pointer justify-start gap-3 py-1">
                        <input
                            type="checkbox"
                            @if($mode==='form')
                                id="{{ $key }}" name="{{ $key }}" value="1" @checked((bool)$value)
                            @else
                                x-model="{{ $aD }}.{{ $key }}"
                            @endif
                            @if($disabled) disabled @endif
                            class="checkbox checkbox-primary"
                        />
                        <span class="label-text font-medium">{{ $label }}</span>
                    </label>
                    @if($mode==='form') @error($key) <label class="label p-0 mt-1"><span class="label-text-alt text-error">{{ $message }}</span></label> @enderror @endif
                </div>
            @break

            @case('toggle')
                <div class="form-control w-full">
                    @if($mode==='form') <input type="hidden" name="{{ $key }}" value="0" /> @endif
                    <label class="label cursor-pointer justify-between">
                        <span class="label-text font-medium">{{ $label }}</span>
                        <input
                            type="checkbox"
                            @if($mode==='form')
                                name="{{ $key }}" value="1" @checked((bool)$value)
                            @else
                                x-model="{{ $aD }}.{{ $key }}"
                            @endif
                            @if($disabled) disabled @endif
                            class="toggle toggle-primary"
                        />
                    </label>
                </div>
            @break

            @case('radio')
                <div class="form-control w-full">
                    @if($label)
                        <label class="label"><span class="label-text font-medium">{{ $label }}</span></label>
                    @endif
                    <div class="space-y-1">
                        @foreach($options as $optVal => $optLabel)
                            <label class="label cursor-pointer justify-start gap-3 py-1">
                                <input
                                    type="radio"
                                    value="{{ $optVal }}"
                                    @if($mode==='form')
                                        name="{{ $key }}" @checked((string)$value === (string)$optVal)
                                    @else
                                        x-model="{{ $aD }}.{{ $key }}"
                                    @endif
                                    class="radio radio-primary"
                                />
                                <span class="label-text">{{ $optLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if($mode==='form') @error($key) <label class="label p-0 mt-1"><span class="label-text-alt text-error">{{ $message }}</span></label> @enderror @endif
                </div>
            @break

            @case('select')
            @case('multiselect')
            @case('tags')
            @case('relation')
                <div class="form-control w-full">
                    @if($label)
                        <label class="label" @if($mode==='form') for="{{ $key }}" @endif>
                            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
                        </label>
                    @endif
                    <select
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}{{ $multiple ? '[]' : '' }}"
                        @else
                            x-model="{{ $aD }}.{{ $key }}"
                            :class="{ 'select-error': ({{ $aE }}['{{ $key }}']||[]).length }"
                        @endif
                        @if($multiple) multiple @endif
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        {{ $attrs }}
                        class="select w-full @if($mode==='form' && $errors->has($key)) select-error @endif"
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
                    @if($mode==='form')
                        @error($key) <label class="label p-0 mt-1"><span class="label-text-alt text-error">{{ $message }}</span></label> @enderror
                    @else
                        <label class="label p-0 mt-1" x-show="({{ $aE }}['{{ $key }}']||[]).length" x-cloak>
                            <span class="label-text-alt text-error" x-text="({{ $aE }}['{{ $key }}']||[])[0]"></span>
                        </label>
                    @endif
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
                <div class="form-control w-full">
                    @if($label)
                        <label class="label" @if($mode==='form') for="{{ $key }}" @endif>
                            <span class="label-text font-medium">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
                        </label>
                    @endif
                    <input
                        type="{{ $inputType }}"
                        placeholder="{{ $placeholder }}"
                        @if($mode==='form')
                            id="{{ $key }}" name="{{ $key }}"
                            @if(!in_array($inputType, ['password','file'])) value="{{ $value }}" @endif
                        @else
                            x-model="{{ $aD }}.{{ $key }}"
                            :class="{ 'input-error': ({{ $aE }}['{{ $key }}']||[]).length }"
                        @endif
                        @if($disabled) disabled @endif
                        @if($required) required @endif
                        {{ $attrs }}
                        class="input w-full @if($mode==='form' && $errors->has($key)) input-error @endif"
                    />
                    @if($mode==='form')
                        @error($key) <label class="label p-0 mt-1"><span class="label-text-alt text-error">{{ $message }}</span></label> @enderror
                    @else
                        <label class="label p-0 mt-1" x-show="({{ $aE }}['{{ $key }}']||[]).length" x-cloak>
                            <span class="label-text-alt text-error" x-text="({{ $aE }}['{{ $key }}']||[])[0]"></span>
                        </label>
                    @endif
                </div>

        @endswitch
    </div>
@endforeach
