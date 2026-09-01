{{--
    Sidebar. Open, it is a 16rem column; closed, it slides off-canvas and gives the content the
    whole width back.

    It used to collapse to a 4rem rail instead, which clipped every label mid-word — `overflow-hidden`
    on a column too narrow for the text it still rendered. A rail only works if the items know how to
    become icon-only, and this component cannot know that: what goes inside it is a slot.

    Sliding out is also the right answer on a phone, where a 4rem rail costs a sixth of the screen
    for nothing. Same behaviour at every size, one thing to reason about.

    Props:
      collapsible    — false pins it open and hides both toggles
      floatingToggle — false drops the floating button. For hosts that put their own toggle in
                       the navbar: closing at the top and reopening at the bottom makes the
                       control jump across the screen, and two toggles in two corners is worse
                       than one where the user looks. The host then needs its own way back in —
                       dispatch `sidebar-toggle` on window.
      class          — extra classes for the <aside>

    `dbSidebar()` starts it open from 1024px up and remembers the last choice in localStorage.
--}}
@props([
    'collapsible'    => true,
    'floatingToggle' => true,
    'class'          => '',
])

{{-- `sidebar-toggle` en window: la barra y la navbar viven en slots distintos del layout, asi
     que no comparten ambito Alpine y un boton de la navbar no puede llamar a toggle() por su
     cuenta. Con el evento global, cualquier parte de la pagina puede recuperarla:

         <button x-data @click="$dispatch('sidebar-toggle')">

     Importa porque, cerrada, el unico acceso era el boton de la esquina inferior; si alguien no lo
     encuentra se queda sin navegacion y sin forma evidente de volver.

     Solo cuando es plegable: con `collapsible => false` la barra esta fija a proposito, y dejarle
     el escuchador permitiria cerrarla desde fuera, que es justo lo que esa opcion promete evitar. --}}
<div x-data="dbSidebar()" x-init="init()" @if($collapsible) @sidebar-toggle.window="toggle()" @endif>

    @if($collapsible && $floatingToggle)
        {{-- The way back in once it is closed.

             Bottom left, not top left: the top left corner is where page titles start, and a fixed
             button there covers them. The bottom corner is almost always empty — and on a phone it
             is the part of the screen a thumb actually reaches.

             Inline `display:none` so it does not flash before Alpine boots: a package cannot count
             on the host defining `[x-cloak]`. --}}
        <button
            type="button"
            x-show="!open"
            style="display: none"
            @click="toggle()"
            class="btn btn-circle fixed bottom-4 left-4 z-50 shadow-lg"
            title="{{ __('Expand') }}"
            aria-label="{{ __('Expand') }}"
        >
            <x-heroicon-o-bars-3 class="w-5 h-5" />
        </button>

        {{-- Tapping outside closes it. Only below lg, where the panel overlays the content. --}}
        <div
            x-show="open"
            style="display: none"
            @click="close()"
            class="fixed inset-0 bg-black/40 z-30 lg:hidden"
            aria-hidden="true"
        ></div>
    @endif

    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full lg:hidden'"
        class="w-64 shrink-0 fixed lg:static inset-y-0 left-0 z-40 min-h-screen bg-base-200 transition-transform duration-300 flex flex-col overflow-hidden {{ $class }}"
    >
        @if($collapsible)
            <div class="flex justify-end p-2">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square"
                    @click="toggle()"
                    title="{{ __('Collapse') }}"
                    aria-label="{{ __('Collapse') }}"
                >
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto overflow-x-hidden">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="p-2 border-t border-base-300">{{ $footer }}</div>
        @endisset
    </aside>
</div>
