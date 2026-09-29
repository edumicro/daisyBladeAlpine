@props([
    'id'       => null,
    'title'    => null,
    'size'     => 'md',   // sm | md | lg | xl | full
    'closable' => true,
])

@php
$sizeClass = match($size) {
    'sm'   => 'max-w-sm',
    'lg'   => 'max-w-2xl',
    'xl'   => 'max-w-4xl',
    'full' => 'max-w-full',
    default => 'max-w-lg',
};
@endphp

{{-- Estructura de DaisyUI: el .modal-box solo se ve cuando su contenedor tiene .modal
     y .modal-open. Con una estructura propia (fixed + flex + x-show), DaisyUI 5 deja la
     caja a opacidad 0 y el contenido queda invisible aunque esté en la página. --}}
<div
    @if($id) id="{{ $id }}" @endif
    x-data="dbModal()"
    @if($id)
        @open-modal.window="if ($event.detail.id === '{{ $id }}') show()"
        @close-modal.window="if ($event.detail.id === '{{ $id }}') hide()"
    @endif
    class="modal"
    :class="open && 'modal-open'"
    @keydown.escape.window="hide()"
>
    {{-- Panel --}}
    <div class="modal-box relative {{ $sizeClass }} w-11/12">
        @if($closable)
            <button
                type="button"
                class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                @click="hide()"
            >✕</button>
        @endif

        @if($title)
            <h3 class="font-bold text-lg mb-4">{{ $title }}</h3>
        @endif

        {{ $slot }}
    </div>

    {{-- Clic fuera para cerrar --}}
    <div class="modal-backdrop" @click="hide()"></div>
</div>
