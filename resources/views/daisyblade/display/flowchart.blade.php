{{--
    Flowchart: diagrama de cajas y flechas sobre un lienzo, con arrastrar y soltar.
    Grafo libre (ciclos, flechas de un nodo a sí mismo, varias flechas entre el mismo par): sirve
    para un organigrama, un mapa de procesos, un flujo de aprobación o los estados de una tarea.
    Necesita el módulo JS `components/flowchart.js` registrado (ver docs/components/flowchart.md).

    @prop nodes      — [{id, label, ...}] (campos del nodo, abajo)
    @prop edges      — [{id, from, to, label?, variant?, muted?, flat?}]
    @prop direction  — vertical (de arriba abajo) | horizontal (de izquierda a derecha): colocación automática
    @prop editable   — mover cajas y crear flechas arrastrando desde el asa
    @prop name       — identificador opcional: las órdenes de ventana (`dbl-flowchart-set`, `-focus`, `-remove-edge`, `-remove-node`) llevan el `name` del diagrama al que van
    @prop height     — altura CSS del lienzo (px, rem, em, vh o %); el contenido hace scroll dentro
    @prop grid       — paso de ajuste al soltar una caja, en px (0 = sin ajuste)
    @prop showMuted  — pintar también los `muted` (en discontinua); con `false` hay un interruptor
    @prop fit        — true: al cargar, encuadra todo el dibujo en el lienzo (escala máxima 1). Hay botones +, − y encuadrar

    Campos de cada nodo:
      id, label    — el `id` es obligatorio (número o texto); sin `label` se muestra el `id`
      x, y         — posición; sin ellas el nodo se coloca solo
      shape        — rectangle | rounded | pill | diamond | circle (`rounded` por defecto)
      description  — segunda línea de texto
      icon         — nombre de un componente de icono (heroicon-o-...), como en el resto del paquete
      badge        — texto corto sobre la esquina de la caja
      group        — nombre de grupo: los nodos con el mismo nombre quedan en una banda de fondo
      variant      — success | warning | error | neutral (mismo mapa que display.node-graph)
      muted        — oculto salvo con `showMuted`
      flat         — dato opaco del padre; vuelve en cada evento

    Eventos (siempre):  dbl-flowchart-node-clicked {id, flat}, dbl-flowchart-edge-clicked {id, flat}
    Eventos (editable): dbl-flowchart-node-move-requested {id, x, y, flat, accept, reject}
                        dbl-flowchart-edge-requested {fromId, toId, fromFlat, toFlat, accept, reject}
    El componente no sabe qué hacer con ellos: los emite y el padre decide. Un oyente que quiere
    responder más tarde (un diálogo, una petición) llama a `$event.preventDefault()` y luego a
    `accept(datos?)` o `reject(mensaje?)`. Sin respuesta ni `preventDefault()`, se acepta tal cual.
    Escucha en window (cada orden lleva en el detail el `name` del diagrama al que va):
      `dbl-flowchart-set` {name, nodes, edges}, `dbl-flowchart-focus` {name, id},
      `dbl-flowchart-remove-edge` {name, id}, `dbl-flowchart-remove-node` {name, id};
    métodos Alpine `set(nodes, edges)`, `focus(id)`, `removeEdge(id)`, `removeNode(id)`, `zoomIn()`, `zoomOut()`, `fit()`.
--}}
@props([
    'nodes'          => [],
    'edges'          => [],
    'direction'      => 'vertical',
    'editable'       => false,
    'name'           => '',
    'height'         => '28rem',
    'grid'           => 0,
    'showMuted'      => false,
    'fit'            => false,
    'label'          => '',
    'canvasClass'    => '',
])

@php
    $height = preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', (string) $height) ? $height : '28rem';

    // Los iconos se pintan aquí y el JS solo los coloca. Se piden a blade-icons con svg(), que solo conoce iconos
    // (heroicon-o-..., o los de cualquier otro juego instalado): con <x-dynamic-component> el nombre podría ser el
    // de CUALQUIER componente Blade, y los nodos pueden venir de datos que edita una persona.
    $icons = [];
    foreach (array_unique(array_filter(array_column($nodes, 'icon'), 'is_string')) as $iconName) {
        try {
            $icons[$iconName] = trim(svg($iconName, 'size-4 shrink-0')->toHtml());
        } catch (\Throwable) {
            // No es un icono (o su juego no está instalado): se omite y el nodo se queda sin icono.
        }
    }

    $config = [
        'nodes'     => array_values($nodes),
        'edges'     => array_values($edges),
        'direction' => $direction === 'horizontal' ? 'horizontal' : 'vertical',
        'editable'  => (bool) $editable,
        'name'      => (string) $name,
        'grid'      => max(0, (int) $grid),
        'showMuted' => (bool) $showMuted,
        'fit'       => (bool) $fit,
        'icons'     => (object) $icons,
        'labels'    => [
            'node' => __('daisyblade::flowchart.node'),
            'edge' => __('daisyblade::flowchart.edge'),
        ],
    ];
    $uid = 'dbl-flowchart-' . \Illuminate\Support\Str::random(8);   // único por render: dos diagramas iguales no comparten ids
@endphp

<div {{ $attributes->merge(['class' => 'dbl-flowchart min-w-0 max-w-full']) }}
     x-data="dblFlowchart(@js($config))"
     x-on:dbl-flowchart-set.window="onSet($event.detail)"
     x-on:dbl-flowchart-focus.window="onFocus($event.detail)"
     x-on:dbl-flowchart-remove-edge.window="onRemoveEdge($event.detail)"
     x-on:dbl-flowchart-remove-node.window="onRemoveNode($event.detail)">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        @if($label)
            <h3 class="font-semibold text-base">{{ $label }}</h3>
        @endif
        <label class="ml-auto flex cursor-pointer items-center gap-2 text-sm" x-show="hasMuted" x-cloak>
            <input type="checkbox" class="toggle toggle-sm" x-bind:checked="showMuted" x-on:change="toggleMuted()">
            <span>{{ __('daisyblade::flowchart.show_muted') }}</span>
        </label>
    </div>

    @if($editable)
        <p id="{{ $uid }}-help" class="sr-only">{{ __('daisyblade::flowchart.help') }}</p>
    @endif

    <style>
        /* El asa de crear flechas solo se ve con el puntero encima o con la caja enfocada; en táctil, siempre. */
        .dbl-flowchart .dbl-flowchart-handle { opacity: 0; transition: opacity .12s }
        .dbl-flowchart [data-node]:hover .dbl-flowchart-handle,
        .dbl-flowchart [data-node]:focus .dbl-flowchart-handle,
        .dbl-flowchart .dbl-flowchart-handle:focus-visible { opacity: 1 }
        @media (hover: none) { .dbl-flowchart .dbl-flowchart-handle { opacity: 1 } }
    </style>

    <div class="relative">
        <div class="absolute right-2 top-2 z-10 join">
            <button type="button" class="btn btn-xs join-item" x-on:click="zoomIn()"
                    aria-label="{{ __('daisyblade::flowchart.zoom_in') }}" title="{{ __('daisyblade::flowchart.zoom_in') }}">
                <x-heroicon-o-plus class="size-4" />
            </button>
            <button type="button" class="btn btn-xs join-item" x-on:click="zoomOut()"
                    aria-label="{{ __('daisyblade::flowchart.zoom_out') }}" title="{{ __('daisyblade::flowchart.zoom_out') }}">
                <x-heroicon-o-minus class="size-4" />
            </button>
            <button type="button" class="btn btn-xs join-item" x-on:click="fit(true)"
                    aria-label="{{ __('daisyblade::flowchart.fit') }}" title="{{ __('daisyblade::flowchart.fit') }}">
                <x-heroicon-o-arrows-pointing-in class="size-4" />
            </button>
        </div>

    <div x-ref="viewport" class="overflow-auto rounded-box border border-base-300 bg-base-100 {{ $canvasClass }}"
         style="height: {{ $height }}; max-width: 100%">
        <div class="relative" x-bind:style="`width:${size.w * scale + offset.x}px;height:${size.h * scale + offset.y}px;min-width:100%;min-height:100%`">
        {{-- translate(offset) centers the drawing after fit/zoom when it is smaller than the view; it is 0 until then --}}
        <div x-ref="canvas" class="relative"
             x-bind:style="`width:${size.w}px;height:${size.h}px;transform:translate(${offset.x}px,${offset.y}px) scale(${scale});transform-origin:0 0`">

            {{-- Bandas de los grupos (al fondo) --}}
            <template x-for="b in bands" x-bind:key="b.name">
                <div class="absolute rounded-box border border-dashed border-base-300 bg-base-200/50" style="z-index:0; pointer-events:none"
                     x-bind:style="`left:${b.x}px;top:${b.y}px;width:${b.w}px;height:${b.h}px`">
                    <span class="absolute left-3 top-1 max-w-full truncate pr-3 text-xs font-semibold text-base-content/60" x-text="b.name"></span>
                </div>
            </template>

            {{-- Flechas: un <svg> por flecha (un <template> dentro de un svg no se puede iterar) --}}
            <template x-for="e in vEdges" x-bind:key="e.key">
                <svg class="absolute left-0 top-0 overflow-visible" style="pointer-events:none; z-index:1"
                     x-bind:width="size.w" x-bind:height="size.h">
                    <g tabindex="0" role="button" style="outline:none"
                       x-bind:aria-label="edgeAria(e)"
                       x-on:click="edgeClick(e)"
                       x-on:keydown.enter.prevent="edgeClick(e)"
                       x-on:keydown.space.prevent="edgeClick(e)"
                       x-on:focus="activeEdge = e.key" x-on:blur="activeEdge = null"
                       x-on:mouseenter="activeEdge = e.key" x-on:mouseleave="activeEdge = null">
                        <path fill="none" stroke="transparent" stroke-width="18" style="pointer-events:stroke; cursor:pointer"
                              x-bind:d="g(e).d"></path>
                        <path fill="none" stroke-linecap="round" style="pointer-events:none"
                              x-bind:d="g(e).d" x-bind:stroke="edgeColor(e)"
                              x-bind:stroke-width="activeEdge === e.key ? 3.5 : 2"
                              x-bind:stroke-dasharray="e.muted || e.provisional ? '6 4' : null"
                              x-bind:opacity="e.muted || e.provisional ? 0.6 : 1"></path>
                        <path style="pointer-events:none" x-bind:d="g(e).head" x-bind:fill="edgeColor(e)"
                              x-bind:opacity="e.muted || e.provisional ? 0.6 : 1"></path>
                        <text x-show="e.label" font-size="12" dominant-baseline="central"
                              style="pointer-events:all; cursor:pointer; paint-order:stroke; stroke:var(--color-base-100); stroke-width:5px; stroke-linejoin:round; fill:var(--color-base-content)"
                              x-bind:x="g(e).label.x" x-bind:y="g(e).label.y" x-bind:text-anchor="g(e).label.anchor"
                              x-text="e.label"></text>
                    </g>
                </svg>
            </template>

            {{-- Flecha provisional mientras se arrastra desde un asa --}}
            <svg class="absolute left-0 top-0 overflow-visible" style="pointer-events:none; z-index:4" aria-hidden="true"
                 x-show="linking && linking.moved" x-cloak x-bind:width="size.w" x-bind:height="size.h">
                <path fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-dasharray="5 4" x-bind:d="linking?.d"></path>
                <path fill="var(--color-primary)" x-bind:d="linking?.head"></path>
            </svg>

            {{-- Cajas: la forma es un <path> de fondo y el texto va encima en HTML --}}
            <template x-for="n in vNodes" x-bind:key="n.key">
                <div role="button" tabindex="0"
                     class="absolute flex select-none flex-col items-center justify-center text-center text-sm outline-none"
                     x-bind:data-node="n.key"
                     x-bind:style="nodeStyle(n)"
                     x-bind:aria-label="nodeAria(n)"
                     @if($editable) aria-describedby="{{ $uid }}-help" @endif
                     x-on:pointerdown="nodeDown($event, n)"
                     x-on:pointermove="nodeMove($event, n)"
                     x-on:pointerup="nodeUp($event, n)"
                     x-on:pointercancel="nodeCancel($event, n)"
                     x-on:keydown="nodeKeydown($event, n)"
                     x-on:keyup="nodeKeyup($event, n)"
                     x-on:focus="activeNode = n.key" x-on:blur="activeNode = null">
                    <svg class="absolute inset-0 overflow-visible" aria-hidden="true" x-bind:width="n.w" x-bind:height="n.h"
                         style="pointer-events:none; fill:var(--color-base-100)">
                        <path x-bind:d="shapeD(n)" stroke-width="2" x-bind:stroke="nodeStroke(n)"
                              x-bind:stroke-dasharray="n.muted ? '6 4' : null"></path>
                    </svg>
                    <div class="relative flex min-w-0 max-w-full flex-col items-center" x-bind:style="`padding:${nodePad(n)}`">
                        <span class="flex max-w-full items-center gap-1.5">
                            <span x-show="n.icon && icons[n.icon]" x-html="icons[n.icon] ?? ''"></span>
                            <span class="line-clamp-2 break-words font-medium leading-tight" x-text="n.label"></span>
                        </span>
                        <span class="line-clamp-1 max-w-full break-words text-xs leading-tight text-base-content/60"
                              x-show="n.description" x-text="n.description"></span>
                    </div>
                    <span class="badge badge-xs badge-neutral absolute -top-2 left-2" x-show="n.badge" x-text="n.badge"></span>
                    @if($editable)
                        <span class="dbl-flowchart-handle absolute -bottom-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] leading-none text-primary-content"
                              style="touch-action:none; cursor:crosshair; z-index:5"
                              x-bind:style="linking?.from === n.key ? 'opacity:1' : ''"
                              aria-hidden="true"
                              title="{{ __('daisyblade::flowchart.handle') }}"
                              x-on:pointerdown="linkDown($event, n)"
                              x-on:pointermove="linkMove($event)"
                              x-on:pointerup="linkUp($event)"
                              x-on:pointercancel="linkCancel()">&rarr;</span>
                    @endif
                </div>
            </template>

        </div>
            <p class="absolute inset-0 flex items-center justify-center text-sm text-base-content/50"
               x-show="isEmpty" x-cloak>{{ __('daisyblade::flowchart.empty') }}</p>
        </div>
    </div>
    </div>
</div>
