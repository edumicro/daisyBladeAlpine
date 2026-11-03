'use strict'

import {
    autoLayout, layoutEdges, boxAt, draftLine, drawingBounds, groupBands, shapePath, snap, clampPos, normalizeShape, sizeOf,
} from './flowchart/geometry.js'
import { responder, provisionalEdge, finalizeEdge, samePos } from './flowchart/protocol.js'

// ── Datos: funciones puras (se prueban con `node --test`) ──────────────────

const VARIANT_COLOR = {
    success: 'var(--color-success)',
    warning: 'var(--color-warning)',
    error:   'var(--color-error)',
    neutral: 'color-mix(in oklab, var(--color-base-content) 35%, transparent)',
}

/** Color CSS (variable del tema) de una variante genérica; sin variante, `fallback`. */
export const variantColor = (variant, fallback) => VARIANT_COLOR[variant] ?? fallback

/** Sustituye `:nombre` en un texto traducido. */
export const fmt = (tpl, vars = {}) => String(tpl).replace(/:(\w+)/g, (m, k) => (k in vars ? vars[k] : m))

const num = v => (typeof v === 'number' && Number.isFinite(v) ? v : (v !== null && v !== '' && v !== undefined && Number.isFinite(+v) ? +v : null))

/**
 * Limpia lo que manda el padre: ids como texto (`key`), sin nodos repetidos y sin flechas
 * que apunten a un nodo que no existe. El id original se conserva en `id` para los eventos.
 */
export function normalize(nodes = [], edges = []) {
    const seen = new Set()
    const cleanNodes = []
    for (const n of Array.isArray(nodes) ? nodes : []) {
        if (!n || n.id === undefined || n.id === null || seen.has(String(n.id))) continue
        seen.add(String(n.id))
        cleanNodes.push({
            id: n.id, key: String(n.id), label: String(n.label ?? n.id),
            x: num(n.x), y: num(n.y),
            shape: normalizeShape(n.shape), group: n.group ? String(n.group) : null,
            description: n.description ? String(n.description) : '', icon: n.icon ? String(n.icon) : null,
            ...sizeOf(n.shape),
            badge: n.badge ?? null, variant: n.variant ?? null, muted: !!n.muted, flat: n.flat ?? null,
        })
    }
    const ids = new Set(cleanNodes.map(n => n.key))
    const usedKeys = new Set()
    const cleanEdges = []
    for (const e of Array.isArray(edges) ? edges : []) {
        if (!e || !ids.has(String(e.from)) || !ids.has(String(e.to))) continue
        let key = String(e.id ?? `${e.from}>${e.to}#${cleanEdges.length}`)
        while (usedKeys.has(key)) key += '_'
        usedKeys.add(key)
        cleanEdges.push({
            id: e.id ?? key, key, from: String(e.from), to: String(e.to),
            label: e.label ? String(e.label) : '',
            variant: e.variant ?? null, muted: !!e.muted, flat: e.flat ?? null,
        })
    }
    return { nodes: cleanNodes, edges: cleanEdges }
}

/** Nodos y flechas que se pintan: los `muted` solo con `showMuted`, y sin flechas colgando. */
export function visible(nodes, edges, showMuted) {
    const vNodes = showMuted ? nodes : nodes.filter(n => !n.muted)
    const keys = new Set(vNodes.map(n => n.key))
    const vEdges = edges.filter(e => (showMuted || !e.muted) && keys.has(e.from) && keys.has(e.to))
    return { vNodes, vEdges }
}

const MIN_SCALE = 0.1
const MAX_SCALE = 2
const ZOOM_STEP = 1.25

/** Escala que cabe el dibujo en la vista (con un margen), sin pasar de 1 ni bajar del mínimo. */
export function fitScale(size, view, pad = 8) {
    const s = Math.min((view.w - pad * 2) / size.w, (view.h - pad * 2) / size.h, 1)
    return Math.max(MIN_SCALE, Number.isFinite(s) && s > 0 ? Math.round(s * 1000) / 1000 : 1)
}

/**
 * Cuánto hay que correr el dibujo para dejarlo centrado en la vista: la mitad del hueco que sobra en
 * cada eje (a esa escala), o 0 si en ese eje el dibujo ocupa todo el ancho o el alto (entonces se desplaza con el scroll).
 */
export function centerOffset(size, view, scale) {
    return {
        x: Math.max(0, Math.round((view.w - size.w * scale) / 2)),
        y: Math.max(0, Math.round((view.h - size.h * scale) / 2)),
    }
}

// ── Componente Alpine ──────────────────────────────────────────────────────

const DRAG_THRESHOLD = 4   // px que hay que mover antes de que un clic sea un arrastre
const KEY_STEP = 10        // px por pulsación de flecha; con Shift, un paso de rejilla
const ARROWS = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown']

export const dbFlowchart = (config = {}) => ({
    editable: !!config.editable,
    name: config.name ?? '',
    grid: config.grid ?? 0,
    showMuted: !!config.showMuted,
    fitOnLoad: !!config.fit,
    zoomed: false,                 // la persona ya tocó el zoom: no se reencuadra sola
    scale: 1,                      // zoom: el lienzo se pinta con `transform: scale()`
    offset: { x: 0, y: 0 },        // px que se corre el dibujo para centrarlo tras encuadrar o hacer zoom (0 al cargar)
    direction: config.direction === 'horizontal' ? 'horizontal' : 'vertical',
    icons: config.icons ?? {},     // nombre de icono -> SVG ya pintado por el servidor (x-html)
    labels: config.labels ?? {},

    nodes: [], edges: [],          // todo lo que mandó el padre (normalizado)
    vNodes: [], vEdges: [],        // lo que se pinta
    pos: {},                       // key -> {x, y}
    geo: {},                       // key de flecha -> geometría
    bands: [],                     // bandas de fondo de los grupos
    size: { w: 0, h: 0 },
    seq: 0,                        // contador de flechas provisionales
    pressed: null,                 // arrastre de caja en curso
    linking: null,                 // arrastre de flecha en curso { from, x, y, over, moved, path, head }
    kbKey: null,                   // caja que se está moviendo con el teclado
    kbFrom: null,                  // su posición al empezar a moverla
    focusKey: null,                // caja resaltada por focus()
    activeEdge: null,              // flecha con foco o puntero encima
    activeNode: null,              // caja con el foco del teclado

    init() {
        this.load(config.nodes, config.edges)
        // Como `display.map`: con `fit`, al cargar se encuadra todo el dibujo (una sola vez).
        if (this.fitOnLoad) {
            this.$nextTick(() => {
                this.fit()
                // El ancho de la vista aún puede cambiar (estilos que llegan tarde, girar el móvil):
                // se reencuadra hasta que la persona toque el zoom.
                if (typeof ResizeObserver !== 'undefined' && this.$refs.viewport) {
                    this._ro = new ResizeObserver(() => { if (!this.zoomed) this.fit() })
                    this._ro.observe(this.$refs.viewport)
                }
            })
        }
    },
    destroy() { this._ro?.disconnect() },

    get hasMuted() { return this.nodes.some(n => n.muted) || this.edges.some(e => e.muted) },
    get isEmpty() { return !this.vNodes.length },

    // ── Estado ──
    load(nodes, edges) {
        const clean = normalize(nodes, edges)
        const old = this.pos
        // Los nodos sin posición propia conservan la que ya tenían en pantalla.
        const withPos = clean.nodes.map(n => (n.x === null || n.y === null) && old[n.key] ? { ...n, x: old[n.key].x, y: old[n.key].y } : n)
        this.nodes = clean.nodes
        this.edges = clean.edges
        this.pos = autoLayout(withPos.map(n => ({ ...n, id: n.key })), clean.edges, this.direction)
        this.recompute()
    },
    set(nodes, edges) { this.load(nodes, edges) },

    boxes() {
        const boxes = {}
        for (const n of this.vNodes) {
            const p = this.pos[n.key]
            if (p) boxes[n.key] = { x: p.x, y: p.y, w: n.w, h: n.h, shape: n.shape }
        }
        return boxes
    },

    recompute() {
        const { vNodes, vEdges } = visible(this.nodes, this.edges, this.showMuted)
        this.vNodes = vNodes
        this.vEdges = vEdges
        const boxes = this.boxes()
        // Las flechas del par se reparten por clave de flecha, no por id original.
        const geos = layoutEdges(boxes, vEdges.map(e => ({ id: e.key, from: e.from, to: e.to, label: e.label })))
        this.geo = geos
        this.bands = groupBands(vNodes.map(n => ({ id: n.key, group: n.group })), boxes)
        const b = drawingBounds(boxes, geos, vEdges.map(e => ({ id: e.key, label: e.label })), undefined, this.bands)
        this.size = { w: b.width, h: b.height }
    },

    toggleMuted() { this.showMuted = !this.showMuted; this.recompute() },

    // ── Presentación ──
    nodeStyle(n) {
        const p = this.pos[n.key] ?? { x: 0, y: 0 }
        const styles = [
            `left:${p.x}px`, `top:${p.y}px`, `width:${n.w}px`, `height:${n.h}px`,
            `z-index:${this.pressed?.key === n.key || this.kbKey === n.key ? 3 : 2}`,
        ]
        if (n.muted) styles.push('opacity:.6')
        if (this.editable) styles.push('touch-action:none', 'cursor:grab')
        // El resalte es un `drop-shadow` y no un `box-shadow`: sigue el contorno del rombo y del círculo.
        if (this.focusKey === n.key || this.linking?.over === n.key || this.activeNode === n.key) {
            styles.push('filter:drop-shadow(0 0 3px var(--color-primary))')
        }
        return styles.join(';')
    },
    /** Atributos del trazo de la forma de una caja. */
    nodeStroke(n) {
        return variantColor(n.variant, 'color-mix(in oklab, var(--color-base-content) 40%, transparent)')
    },
    shapeD(n) { return shapePath(n.shape, n.w, n.h) },
    /** Padding del texto según la forma: en el rombo y el círculo el hueco útil es menor. */
    nodePad(n) {
        return { diamond: '0 2.2rem', circle: '0 .6rem', pill: '0 1.2rem' }[n.shape] ?? '0 .75rem'
    },
    edgeColor(e) {
        return variantColor(e.variant, 'color-mix(in oklab, var(--color-base-content) 60%, transparent)')
    },
    g(e) { return this.geo[e.key] ?? { d: '', head: '', label: { x: 0, y: 0, anchor: 'middle' } } },
    nodeAria(n) { return fmt(this.labels.node ?? ':label', { label: n.label }) },
    edgeAria(e) {
        const from = this.nodes.find(n => n.key === e.from)?.label ?? e.from
        const to = this.nodes.find(n => n.key === e.to)?.label ?? e.to
        const base = fmt(this.labels.edge ?? ':from -> :to', { from, to })
        return e.label ? `${base}: ${e.label}` : base
    },

    // ── Emisión de eventos (el padre decide) ──
    /** Despacha en el componente (burbujea); `false` si un oyente llamó a `preventDefault()`. */
    emit(name, detail) {
        return this.$root.dispatchEvent(new CustomEvent(name, { detail, bubbles: true, composed: true, cancelable: true }))
    },
    /** Aviso con el sistema del paquete: el evento `notify` lo recoge `feedback.toast`. */
    notify(message) {
        if (message && typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('notify', { detail: { message: String(message), type: 'error' } }))
        }
    },
    nodeClick(n) { this.emit('node-click', { id: n.id, flat: n.flat }) },
    edgeClick(e) { if (!e.provisional) this.emit('edge-click', { id: e.id, flat: e.flat }) },

    // ── Ratón, táctil y lápiz: mover cajas ──
    // Con zoom, el lienzo se ve a escala `scale`: toda posición del puntero se pasa a píxeles del dibujo.
    canvasPoint(ev) {
        const r = this.$refs.canvas.getBoundingClientRect()
        return { x: (ev.clientX - r.left) / this.scale, y: (ev.clientY - r.top) / this.scale }
    },
    nodeDown(ev, n) {
        if (ev.pointerType === 'mouse' && ev.button !== 0) return
        const p = this.pos[n.key]
        this.pressed = { key: n.key, sx: ev.clientX, sy: ev.clientY, ox: p.x, oy: p.y, moved: false }
        if (this.editable) ev.currentTarget.setPointerCapture?.(ev.pointerId)
    },
    nodeMove(ev, n) {
        const d = this.pressed
        if (!d || d.key !== n.key || !this.editable) return
        const dx = (ev.clientX - d.sx) / this.scale
        const dy = (ev.clientY - d.sy) / this.scale
        if (!d.moved && Math.hypot(dx, dy) * this.scale < DRAG_THRESHOLD) return
        d.moved = true
        this.pos[n.key] = { x: clampPos(d.ox + dx), y: clampPos(d.oy + dy) }
        this.recompute()
    },
    nodeUp(ev, n) {
        const d = this.pressed
        this.pressed = null
        if (!d || d.key !== n.key) return
        if (!d.moved) return this.nodeClick(n)
        this.pos[n.key] = { x: clampPos(snap(this.pos[n.key].x, this.grid)), y: clampPos(snap(this.pos[n.key].y, this.grid)) }
        this.recompute()
        this.emitMove(n, { x: d.ox, y: d.oy })
    },
    nodeCancel(ev, n) {
        const d = this.pressed
        this.pressed = null
        if (d?.moved) { this.pos[n.key] = { x: d.ox, y: d.oy }; this.recompute() }
    },
    /**
     * `node-move`: la caja ya está en su sitio nuevo. El padre puede llamar a `reject(mensaje?)` para
     * devolverla a `from`; `accept()` existe por simetría con `edge-create` y no hace nada.
     */
    emitMove(n, from) {
        const p = { x: Math.round(this.pos[n.key].x), y: Math.round(this.pos[n.key].y) }
        const r = responder({
            onAccept: () => {},
            onReject: message => {
                // Si la caja ya se movió otra vez, no se toca: ese movimiento es de otra respuesta.
                if (this.pos[n.key] && samePos(this.pos[n.key], p)) {
                    this.pos[n.key] = { ...from }
                    this.recompute()
                }
                this.notify(message)
            },
        })
        this.emit('node-move', { id: n.id, ...p, flat: n.flat, accept: r.accept, reject: r.reject })
    },

    // ── Teclado ──
    nodeKeydown(ev, n) {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault()
            return this.nodeClick(n)
        }
        if (!this.editable || !ARROWS.includes(ev.key)) return
        ev.preventDefault()
        if (this.kbKey !== n.key) {
            this.kbKey = n.key
            this.kbFrom = { ...this.pos[n.key] }
        }
        const step = ev.shiftKey ? (this.grid > 0 ? this.grid : 20) : KEY_STEP
        const p = this.pos[n.key]
        const d = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[ev.key]
        this.pos[n.key] = { x: clampPos(p.x + d[0] * step), y: clampPos(p.y + d[1] * step) }
        this.recompute()
    },
    nodeKeyup(ev, n) {
        if (this.kbKey !== n.key || !ARROWS.includes(ev.key)) return
        this.kbKey = null
        this.emitMove(n, this.kbFrom)
    },

    // ── Flechas nuevas: arrastrar desde el asa de una caja ──
    linkDown(ev, n) {
        if (!this.editable || (ev.pointerType === 'mouse' && ev.button !== 0)) return
        ev.stopPropagation()
        ev.preventDefault()
        ev.currentTarget.setPointerCapture?.(ev.pointerId)
        const start = this.canvasPoint(ev)
        this.linking = { from: n.key, sx: start.x, sy: start.y, x: start.x, y: start.y, over: null, moved: false, d: '', head: '' }
    },
    linkMove(ev) {
        const l = this.linking
        if (!l) return
        const pt = this.canvasPoint(ev)
        l.x = pt.x
        l.y = pt.y
        if (!l.moved && Math.hypot(pt.x - l.sx, pt.y - l.sy) * this.scale >= DRAG_THRESHOLD) l.moved = true
        if (!l.moved) return
        l.over = boxAt(this.boxes(), pt)
        const line = draftLine(this.boxes()[l.from], pt)
        l.d = line.d
        l.head = line.head
    },
    linkUp(ev) {
        const l = this.linking
        this.linking = null
        if (!l?.moved || l.over === null) return
        const from = this.nodes.find(n => n.key === l.from)
        const to = this.nodes.find(n => n.key === l.over)
        if (!from || !to) return
        // La flecha se pinta ya, provisional, mientras el padre decide.
        const prov = provisionalEdge(from.key, to.key, ++this.seq)
        this.edges = [...this.edges, prov]
        this.recompute()
        const r = responder({
            onAccept: extra => this.settleEdge(prov, extra),
            onReject: message => { this.dropEdge(prov.key); this.notify(message) },
        })
        const proceed = this.emit('edge-create', {
            fromId: from.id, toId: to.id, fromFlat: from.flat, toFlat: to.flat, accept: r.accept, reject: r.reject,
        })
        // Nadie ha pedido quedarse la decisión (`preventDefault()`) ni ha respondido: se fija tal cual.
        if (proceed && !r.settled) r.accept()
    },
    /** Fija la flecha provisional con los datos que devolvió el padre (si el estado ya cambió, no hace nada). */
    settleEdge(prov, extra) {
        const i = this.edges.findIndex(e => e.key === prov.key)
        if (i === -1) return
        const used = this.edges.filter(e => e.key !== prov.key).map(e => e.key)
        this.edges = this.edges.map((e, j) => (j === i ? finalizeEdge(prov, extra, used) : e))
        this.recompute()
    },
    dropEdge(key) {
        this.edges = this.edges.filter(e => e.key !== key)
        this.recompute()
    },
    linkCancel() { this.linking = null },

    // ── Zoom y encuadre ──
    /** Escala de golpe, manteniendo en su sitio el punto del dibujo que está en el centro de la vista. */
    zoomTo(next) {
        this.zoomed = true
        const vp = this.$refs.viewport
        const scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, next))
        if (!vp) { this.scale = scale; return }
        // Punto del dibujo que está en el centro de la vista (descontando el desplazamiento de centrado).
        const cx = (vp.scrollLeft + vp.clientWidth / 2 - this.offset.x) / this.scale
        const cy = (vp.scrollTop + vp.clientHeight / 2 - this.offset.y) / this.scale
        this.scale = scale
        // Si a la nueva escala el dibujo cabe en la vista, queda centrado; si no, se desplaza con el scroll.
        this.offset = centerOffset(this.size, { w: vp.clientWidth, h: vp.clientHeight }, scale)
        this.$nextTick(() => {
            vp.scrollLeft = cx * scale + this.offset.x - vp.clientWidth / 2
            vp.scrollTop = cy * scale + this.offset.y - vp.clientHeight / 2
        })
    },
    zoomIn() { this.zoomTo(this.scale * ZOOM_STEP) },
    zoomOut() { this.zoomTo(this.scale / ZOOM_STEP) },
    /** Encuadra todo el dibujo en el lienzo (escala máxima 1: no agranda) y lo deja centrado en el eje que sobra. */
    fit(manual = false) {
        if (manual) this.zoomed = false
        const vp = this.$refs.viewport
        if (!vp || !this.size.w || !this.size.h) return
        const view = { w: vp.clientWidth, h: vp.clientHeight }
        this.scale = fitScale(this.size, view)
        this.offset = centerOffset(this.size, view, this.scale)
        this.$nextTick(() => { vp.scrollLeft = 0; vp.scrollTop = 0 })
    },

    // ── Desde fuera ──
    /** Quita una flecha (el padre ya decidió, p. ej. tras un `edge-click`). No emite nada. */
    removeEdge(id) { this.dropEdge(String(id)) },
    /** Quita un nodo y las flechas que llegan o salen de él. No emite nada. */
    removeNode(id) {
        const key = String(id)
        this.nodes = this.nodes.filter(n => n.key !== key)
        this.edges = this.edges.filter(e => e.from !== key && e.to !== key)
        delete this.pos[key]
        this.recompute()
    },
    /** Centra y resalta un nodo (si estaba oculto por `muted`, muestra los ocultos). */
    focus(id) {
        const key = String(id)
        const node = this.nodes.find(n => n.key === key)
        if (!node) return
        if (node.muted && !this.showMuted) { this.showMuted = true; this.recompute() }
        this.focusKey = key
        this.$nextTick(() => {
            const vp = this.$refs.viewport
            const p = this.pos[key]
            if (vp && p) {
                vp.scrollTo({
                    left: Math.max(0, (p.x + node.w / 2) * this.scale + this.offset.x - vp.clientWidth / 2),
                    top: Math.max(0, (p.y + node.h / 2) * this.scale + this.offset.y - vp.clientHeight / 2),
                    behavior: 'smooth',
                })
            }
            this.$root.querySelector?.(`[data-node="${CSS.escape(key)}"]`)?.focus({ preventScroll: true })
        })
        clearTimeout(this._focusTimer)
        this._focusTimer = setTimeout(() => { this.focusKey = null }, 2500)
    },
    /** Manejador del evento de ventana `dbl-flowchart`: { name, action, ... }. */
    onWindowEvent(d) {
        if (!d || !this.name || d.name !== this.name) return
        if (d.action === 'set') this.set(d.nodes, d.edges)
        else if (d.action === 'removeEdge') this.removeEdge(d.id)
        else if (d.action === 'removeNode') this.removeNode(d.id)
        else if (d.action === 'focus') this.focus(d.id)
    },
})

export function registerFlowchart(Alpine) {
    Alpine.data('dbFlowchart', config => dbFlowchart(config))
}
