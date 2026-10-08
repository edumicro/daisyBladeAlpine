@props([
    'name'           => '',
    'label'          => '',
    'value'          => '',
    'placeholder'    => '',
    'uploadUrl'      => '',
    'maxLength'      => 0,
    'required'       => false,
    'description'    => '',
    'minimal'        => false,
    'containerClass' => '',
])

@php
    // Textos traducidos: el JS no sabe de idiomas, los recibe ya resueltos.
    $keys = ['editor', 'paragraph', 'h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList',
             'blockquote', 'link', 'image', 'table', 'undo', 'redo', 'linkPrompt', 'linkInvalid',
             'uploadFailed'];
    $config = [
        'label'       => $label,
        'placeholder' => $placeholder,
        'uploadUrl'   => $uploadUrl,
        'maxLength'   => (int) $maxLength,
        'minimal'     => (bool) $minimal,
        'labels'      => collect($keys)->mapWithKeys(fn ($k) => [$k => __("daisyblade::rich-text.$k")])->all(),
    ];
@endphp

@once
    <style>
        /* Tailwind (preflight) borra el estilo de titulos, listas, citas y tablas: se devuelve aqui. */
        .db-rich-text-content { min-height: 10rem; padding: .75rem; outline: none; }
        .db-rich-text-content > * + * { margin-top: .5rem; }
        .db-rich-text-content h2 { font-size: 1.5rem; font-weight: 700; }
        .db-rich-text-content h3 { font-size: 1.25rem; font-weight: 600; }
        .db-rich-text-content ul { list-style: disc; padding-left: 1.5rem; }
        .db-rich-text-content ol { list-style: decimal; padding-left: 1.5rem; }
        .db-rich-text-content blockquote { border-left: 4px solid var(--color-base-300); padding-left: .75rem; opacity: .8; }
        .db-rich-text-content a { color: var(--color-primary); text-decoration: underline; }
        .db-rich-text-content img { max-width: 100%; height: auto; }
        .db-rich-text-content table { border-collapse: collapse; width: 100%; }
        .db-rich-text-content th, .db-rich-text-content td { border: 1px solid var(--color-base-300); padding: .25rem .5rem; }
        .db-rich-text-content th { background: var(--color-base-200); }
        .db-rich-text-content p.is-editor-empty:first-child::before {
            content: attr(data-placeholder); float: left; height: 0; pointer-events: none; opacity: .5;
        }
    </style>
@endonce

<div class="w-full {{ $containerClass }}" x-data="dbRichText(@js($config))">
    @if($label)
        <label class="mb-1 flex items-center justify-between gap-2 text-sm font-medium"
               @if($name) for="{{ $name }}-editor" @endif>
            <span>{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</span>
            @if($maxLength > 0)
                <span class="text-xs font-normal"
                      x-bind:class="overLimit ? 'text-error' : 'text-base-content/60'"
                      x-text="length + '/{{ (int) $maxLength }}'">0/{{ (int) $maxLength }}</span>
            @endif
        </label>
    @endif

    @if($description)
        <p class="text-sm text-base-content/70 mb-1">{{ $description }}</p>
    @endif

    {{-- Lo que viaja en el formulario: el HTML del editor, que el servidor DEBE sanear. --}}
    <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="{{ $value }}">

    <div class="rounded-box border border-base-300 bg-base-100 focus-within:border-primary">
        <div role="toolbar" aria-label="{{ __('daisyblade::rich-text.toolbar') }}"
             class="flex flex-wrap gap-1 border-b border-base-300 p-1">
            <template x-for="button in buttons" x-bind:key="button.id">
                <button type="button" class="btn btn-ghost btn-sm min-w-8 px-2"
                        x-bind:class="isActive(button) && 'btn-active'"
                        x-bind:aria-pressed="isActive(button)"
                        x-bind:aria-label="button.label"
                        x-bind:title="button.label"
                        x-on:click="button.run()"
                        x-text="button.text"></button>
            </template>
            <span x-show="uploading" x-cloak class="loading loading-spinner loading-sm self-center"
                  role="status" aria-label="{{ __('daisyblade::rich-text.uploading') }}"></span>
        </div>

        <div x-ref="editor" @if($name) id="{{ $name }}-editor" @endif></div>
    </div>

    <input type="file" accept="image/*" class="hidden" x-ref="file" x-on:change="upload($event)" tabindex="-1">

    <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-error font-semibold" role="alert"></p>

    @if($name && isset($errors))
        @error($name)
            <div class="mt-1"><span class="text-xs text-error font-semibold">{{ $message }}</span></div>
        @enderror
    @endif
</div>
