@props([
    'name',
    'value'  => [],
    'label'  => null,
    'hint'   => null,
    'class'  => '',
])

@php
$initialRows = collect($value)->map(function ($v, $k) {
    $type = match (true) {
        is_bool($v)                    => 'bool',
        is_int($v) || is_float($v)     => 'number',
        is_array($v)                   => 'object',
        default                        => 'string',
    };
    return [
        'key'   => $k,
        'value' => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v,
        'type'  => $type,
        'dirty' => true,
    ];
})->values()->toArray();
@endphp

<div x-data="dbKvEditor({ rows: {{ \Illuminate\Support\Js::from($initialRows) }} })" class="{{ $class }}">

    @if($label)
    <div class="label pb-1">
        <span class="label-text font-medium">{{ $label }}</span>
    </div>
    @endif

    {{-- Hidden input keeps the serialised JSON in sync --}}
    <input type="hidden" name="{{ $name }}" :value="toJson()" />

    {{-- Column headers --}}
    <div class="flex gap-2 mb-1.5 px-0.5 text-xs text-base-content/40 select-none">
        <span class="flex-1">clave</span>
        <span class="flex-1">valor</span>
        <span class="w-16 shrink-0 text-center">tipo</span>
        <span class="w-8 shrink-0"></span>
    </div>

    {{-- Rows --}}
    <div class="space-y-2">
        <template x-for="(row, i) in rows" :key="row.id">
            <div class="flex items-start gap-2">

                {{-- Key --}}
                <input
                    type="text"
                    x-model="row.key"
                    placeholder="campo"
                    class="input input-sm flex-1 font-mono text-sm min-w-0"
                />

                {{-- Value (conditional by type) --}}
                <div class="flex-1 min-w-0">
                    <template x-if="row.type === 'bool'">
                        <select x-model="row.value"
                                class="select select-sm w-full font-mono text-sm">
                            <option value="true">true</option>
                            <option value="false">false</option>
                        </select>
                    </template>
                    <template x-if="row.type === 'object'">
                        <textarea
                            x-model="row.value"
                            rows="2"
                            class="textarea text-xs font-mono w-full leading-snug"
                            placeholder='{"key": "value"}'
                        ></textarea>
                    </template>
                    <template x-if="row.type !== 'bool' && row.type !== 'object'">
                        <input
                            type="text"
                            x-model="row.value"
                            @input="onValueInput(row)"
                            class="input input-sm w-full font-mono text-sm"
                        />
                    </template>
                </div>

                {{-- Type selector --}}
                <select
                    x-model="row.type"
                    @change="onTypeChange(row)"
                    class="select select-xs w-16 shrink-0 font-mono self-start mt-0.5"
                >
                    <option value="number">num</option>
                    <option value="string">str</option>
                    <option value="bool">bool</option>
                    <option value="object">{…}</option>
                </select>

                {{-- Remove row --}}
                <button
                    type="button"
                    @click="rows.splice(i, 1)"
                    class="btn btn-ghost btn-sm btn-square shrink-0 self-start text-base-content/30 hover:text-error"
                    title="Eliminar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <button
        type="button"
        @click="addRow()"
        class="btn btn-ghost btn-xs gap-1 mt-3 self-start"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Añadir campo
    </button>

    @if($hint)
    <div class="label mt-0">
        <span class="label-text-alt text-base-content/40">{{ $hint }}</span>
    </div>
    @endif

</div>
