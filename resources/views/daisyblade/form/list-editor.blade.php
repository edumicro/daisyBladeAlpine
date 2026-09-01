@props([
    'name',
    'value'       => [],
    'label'       => null,
    'hint'        => null,
    'placeholder' => 'Escribe y pulsa Enter o coma…',
    'class'       => '',
])

<div x-data="dbListEditor({ items: {{ \Illuminate\Support\Js::from(array_values((array) $value)) }} })" class="{{ $class }}">

    @if($label)
    <div class="mb-1 text-sm font-medium">{{ $label }}</div>
    @endif

    {{-- Hidden input keeps the JSON array in sync --}}
    <input type="hidden" name="{{ $name }}" :value="toJson()" />

    {{-- Chip container + text input --}}
    <div
        class="input flex flex-wrap gap-1.5 items-center min-h-10 h-auto py-1.5 px-2 cursor-text"
        @click="$refs.draft.focus()"
    >
        <template x-for="(item, i) in items" :key="i">
            <span class="badge badge-neutral gap-1 shrink-0">
                <span x-text="item"></span>
                <button type="button" @click.stop="remove(i)"
                        class="ml-0.5 text-base-content/50 hover:text-error leading-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        </template>

        <input
            type="text"
            x-ref="draft"
            x-model="draft"
            @keydown="onKeydown($event)"
            @blur="add()"
            placeholder="{{ $placeholder }}"
            class="flex-1 min-w-[120px] bg-transparent outline-none text-sm"
        />
    </div>

    @if($hint)
    <div class="mt-1 text-xs text-base-content/40">{{ $hint }}</div>
    @endif

</div>
