@props([
    'name'        => '',
    'label'       => '',
    'signer'      => '',
    'height'      => 220,
    'required'    => false,
    'hint'        => '',
    'wrapperClass' => '',
])

@php
    $id = 'sig-'.($name ?: \Illuminate\Support\Str::random(6));
    $config = [
        'name'     => $name,
        'required' => (bool) $required,
    ];
@endphp

<div x-data="dblSignature(@js($config))"
     class="w-full {{ $wrapperClass }}">

    @if($label)
        <div id="{{ $id }}-label" class="mb-1 text-lg font-semibold">
            {{ $label }}@if($required)<span class="text-error ml-1">*</span>@endif
        </div>
    @endif

    {{-- Descripción para lectores de pantalla y aviso visible de que hace falta dedo o puntero. --}}
    <p id="{{ $id }}-help" class="sr-only">{{ __('daisyblade::signature.sr_description') }}</p>
    <p class="mb-2 text-base text-base-content/70" aria-hidden="true">{{ __('daisyblade::signature.hint') }}</p>

    {{-- Siempre blanco con trazo oscuro, también en modo oscuro: el PDF es blanco. --}}
    {{-- The control of this component is the pad: it takes the attributes of the caller. The box around it, which is also the Alpine scope, takes `wrapper-class`. --}}
    <div {{ $attributes->merge(['class' => 'relative rounded-box border-2 bg-white']) }}
         x-bind:class="error ? 'border-error' : 'border-base-content/40'">
        <canvas x-ref="canvas"
                id="{{ $id }}"
                role="img"
                aria-label="{{ $label ?: __('daisyblade::signature.canvas_label') }}"
                aria-describedby="{{ $id }}-help"
                class="block w-full touch-none rounded-box bg-white"
                style="height: {{ (int) $height }}px"></canvas>

        {{-- Línea y guía "Firme aquí": no interceptan el dedo. --}}
        <div class="pointer-events-none absolute inset-x-6 bottom-8 border-b-2 border-neutral-700"></div>
        <span class="pointer-events-none absolute bottom-10 left-6 select-none text-xl font-medium text-neutral-500"
              x-show="empty">&#10005; {{ __('daisyblade::signature.sign_here') }}</span>
        @if($signer)
            <span class="pointer-events-none absolute inset-x-6 bottom-1 truncate text-lg text-neutral-800">{{ $signer }}</span>
        @endif
    </div>

    <p x-show="error" x-cloak role="alert" class="mt-2 text-lg font-semibold text-error">
        {{ __('daisyblade::signature.required') }}
    </p>

    <div class="mt-3 flex gap-3">
        <button type="button" class="btn btn-lg btn-outline flex-1" x-on:click="undo()" x-bind:disabled="empty">
            {{ __('daisyblade::signature.undo') }}
        </button>
        <button type="button" class="btn btn-lg btn-error btn-outline flex-1" x-on:click="clear()" x-bind:disabled="empty">
            {{ __('daisyblade::signature.clear') }}
        </button>
    </div>

    @if($hint)
        <p class="mt-2 text-base text-base-content/70">{{ $hint }}</p>
    @endif

    <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="">
</div>
