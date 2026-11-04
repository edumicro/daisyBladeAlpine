@props([
    'id'       => null,
    'title'    => null,
    'size'     => 'md',   // xs | sm | md | lg | xl | full
    'closable' => true,
])

@php
$sizeClass = match($size) {
    'xs'   => 'max-w-xs',
    'sm'   => 'max-w-sm',
    'lg'   => 'max-w-2xl',
    'xl'   => 'max-w-4xl',
    'full' => 'max-w-full',
    default => 'max-w-lg',   // md
};
@endphp

{{-- Estructura de DaisyUI: el .modal-box solo se ve cuando su contenedor tiene .modal
     y .modal-open. Con una estructura propia (fixed + flex + x-show), DaisyUI 5 deja la
     caja a opacidad 0 y el contenido queda invisible aunque esté en la página.

     The root takes the attributes of the caller (`$attributes`), so it carries no Alpine attribute of ours but its
     `x-data`: if the caller wrote the same one (`:class`, `@keydown.escape.window`...) the browser would keep only the
     first of the two. What the component needs (the orders it listens to, the `modal-open` class) lives on the panel. --}}
<div
    @if($id) id="{{ $id }}" @endif
    x-data="dblModal()"
    {{ $attributes->merge(['class' => 'modal']) }}
>
    {{-- Panel --}}
    <div class="modal-box relative {{ $sizeClass }} w-11/12"
        {{-- `$root` is the .modal above: its `modal-open` class follows `open` (a class toggled, not a `:class`, so the caller's stay). --}}
        x-effect="$root.classList.toggle('modal-open', open)"
        @if($id)
            @dbl-modal-open.window="if ($event.detail.id === @js($id)) show()"
            @dbl-modal-close.window="if ($event.detail.id === @js($id)) hide()"
        @endif
        {{-- `closable="false"` means that neither Escape nor a click outside closes it: only the order `dbl-modal-close`. --}}
        @if($closable) @keydown.escape.window="hide()" @endif
    >
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
    <div class="modal-backdrop" @if($closable) @click="hide()" @endif></div>
</div>
