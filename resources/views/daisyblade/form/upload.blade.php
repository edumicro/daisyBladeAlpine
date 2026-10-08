{{--
    Subida de ficheros (arrastrar y soltar + botón, progreso, existentes, reordenar).
    Contrato con el servidor y ejemplos: docs/components/upload.md

    @prop action     — URL POST (multipart, campo `file`)
    @prop existing   — [{id, name, url, thumb, size}]
    @prop deleteUrl  — plantilla con {id}, p. ej. /files/{id}
    @prop reorderUrl — opcional; recibe POST {ids: [...]}
    @prop maxSize    — MB por fichero · @prop maxFiles — nº máximo
    @prop capture    — environment | user: abre la cámara en el móvil
--}}
@props([
    'name'           => 'files',
    'label'          => '',
    'action'         => '',
    'multiple'       => false,
    'accept'         => '',
    'maxSize'        => 0,
    'maxFiles'       => 0,
    'capture'        => '',
    'existing'       => [],
    'deleteUrl'      => '',
    'reorderUrl'     => '',
    'required'       => false,
    'description'    => '',
    'class'          => '',
    'containerClass' => '',
])

@php
    // Comillas dobles a propósito: son claves de fichero de idioma, no de es.json.
    $t = fn (string $key) => __("daisyblade::upload.$key");
    $keys = ['drop', 'too_many', 'too_big', 'bad_type', 'uploaded', 'cancelled', 'failed', 'confirm_delete',
             'delete_failed', 'removed', 'moved', 'reorder_failed'];
    $config = [
        'action'     => $action,
        'multiple'   => (bool) $multiple,
        'accept'     => $accept,
        'maxSize'    => (float) $maxSize,
        'maxFiles'   => (int) $maxFiles,
        'existing'   => array_values($existing),
        'deleteUrl'  => $deleteUrl,
        'reorderUrl' => $reorderUrl,
        'messages'   => collect($keys)->mapWithKeys(fn ($k) => [$k => $t($k)])->all(),
    ];
    $id = $name.'-upload';
    $hasHint = $maxSize || $maxFiles;
@endphp

<div {{ $attributes->merge(['class' => 'w-full '.$containerClass]) }} x-data="dbUpload({{ \Illuminate\Support\Js::from($config) }})">
    @if($label)
        <label class="mb-1 block text-sm font-medium" for="{{ $id }}">{{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif</label>
    @endif

    @if($description)
        <p id="{{ $id }}-desc" class="text-sm text-base-content/70 mb-1">{{ $description }}</p>
    @endif

    {{-- Zona de soltar. El input real está oculto pero es el que enfoca el label. --}}
    <div class="flex flex-col items-center gap-3 rounded-box border-2 border-dashed p-6 text-center transition-colors {{ $class }}"
         x-bind:class="dragging ? 'border-primary bg-primary/10' : 'border-base-300'"
         x-on:dragover.prevent="dragging = true"
         x-on:dragleave.prevent="dragging = false"
         x-on:drop.prevent="onDrop($event)">
        <x-heroicon-o-arrow-up-tray class="size-8 text-base-content/40" aria-hidden="true" />
        <p class="text-sm text-base-content/70" x-text="dragging ? @js($t('drop')) : @js($t('hint_drag'))"></p>

        <input type="file" id="{{ $id }}" class="sr-only"
               @if($multiple) multiple @endif
               @if($accept) accept="{{ $accept }}" @endif
               @if($capture) capture="{{ $capture }}" @endif
               @if($required) x-bind:required="ids.length === 0" @endif
               @if($description) aria-describedby="{{ $id }}-desc" @endif
               x-on:change="onPick($event)" />
        <label for="{{ $id }}" class="btn btn-primary btn-sm">
            {{ $capture ? $t('take_photo') : $t('choose') }}
        </label>

        @if($hasHint)
            <p class="text-xs text-base-content/60">
                @if($maxSize) {{ str_replace(':max', $maxSize, $t('max_size')) }} @endif
                @if($maxSize && $maxFiles) · @endif
                @if($maxFiles) {{ str_replace(':max', $maxFiles, $t('max_files')) }} @endif
            </p>
        @endif
    </div>

    {{-- Estado para lectores de pantalla --}}
    <p class="sr-only" role="status" aria-live="polite" x-text="status"></p>

    <ul class="mt-3 space-y-2" aria-label="{{ $t('files') }}">
        <template x-for="(item, index) in items" x-bind:key="item.key">
            <li class="flex items-center gap-3 rounded-box border border-base-300 p-2"
                x-bind:class="item.status === 'error' && 'border-error'">
                {{-- Miniatura o icono --}}
                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded bg-base-200">
                    <template x-if="isImage(item) && (item.preview || item.thumb || item.url)">
                        <img x-bind:src="item.preview || item.thumb || item.url" alt="" class="size-full object-cover" />
                    </template>
                    <template x-if="isPdf(item)">
                        <x-heroicon-o-document-text class="size-6 text-error" aria-hidden="true" />
                    </template>
                    <template x-if="!isImage(item) && !isPdf(item)">
                        <x-heroicon-o-document class="size-6 text-base-content/50" aria-hidden="true" />
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <a x-show="item.url" x-bind:href="item.url" target="_blank" rel="noopener"
                       class="link link-hover block truncate text-sm font-medium" x-text="item.name"></a>
                    <span x-show="!item.url" class="block truncate text-sm font-medium" x-text="item.name"></span>
                    <span class="text-xs text-base-content/60" x-text="formatSize(item.size)"></span>
                    <progress x-show="item.status === 'uploading'" class="progress progress-primary block w-full"
                              max="100" x-bind:value="item.progress"
                              x-bind:aria-label="item.name"></progress>
                    <p x-show="item.error" class="text-xs font-semibold text-error" x-text="item.error"></p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" class="btn btn-ghost btn-xs" x-show="item.status === 'uploading'"
                            x-on:click="cancel(item)">{{ $t('cancel') }}</button>
                    <button type="button" class="btn btn-ghost btn-xs" x-show="item.status === 'error' && item.file"
                            x-on:click="retry(item)">{{ $t('retry') }}</button>

                    <template x-if="item.status === 'done' && items.length > 1">
                        <span class="flex">
                            <button type="button" class="btn btn-ghost btn-xs btn-square" x-bind:disabled="index === 0"
                                    aria-label="{{ $t('move_up') }}" x-on:click="move(item, -1)">
                                <x-heroicon-o-chevron-up class="size-4" aria-hidden="true" />
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs btn-square" x-bind:disabled="index === items.length - 1"
                                    aria-label="{{ $t('move_down') }}" x-on:click="move(item, 1)">
                                <x-heroicon-o-chevron-down class="size-4" aria-hidden="true" />
                            </button>
                        </span>
                    </template>
                    <button type="button" class="btn btn-ghost btn-xs btn-square text-error"
                            x-show="item.status !== 'uploading'"
                            aria-label="{{ $t('delete') }}" x-on:click="remove(item)">
                        <x-heroicon-o-trash class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </li>
        </template>
    </ul>

    {{-- Lo que recoge el formulario: los ids que devolvió el servidor --}}
    <template x-for="id in ids" x-bind:key="id">
        <input type="hidden" name="{{ $name }}[]" x-bind:value="id" />
    </template>

    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
        @enderror
    @endif
</div>
