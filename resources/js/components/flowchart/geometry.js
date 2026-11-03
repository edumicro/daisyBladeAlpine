'use strict'

// ── Geometría del diagrama: funciones puras (se prueban con `node --test`) ──
// Coordenadas en píxeles del lienzo, con el origen arriba a la izquierda.
// Una caja es { x, y, w, h } (x, y = esquina superior izquierda).

// Tamaño de la caja de cada forma: [ancho, alto].
const SHAPE_SIZE = {
    rectangle: [160, 56],
    rounded:   [160, 56],
    pill:      [160, 56],
    diamond:   [180, 90],   // el rombo pierde mucho hueco en las esquinas
    circle:    [84, 84],
}
export const SHAPES = Object.keys(SHAPE_SIZE)
export const DEFAULT_SHAPE = 'rounded'
export const NODE_W = SHAPE_SIZE[DEFAULT_SHAPE][0]
export const NODE_H = SHAPE_SIZE[DEFAULT_SHAPE][1]

export const MARGIN = 40
const ARROW = 10          // largo de la punta
const BAND_PAD = 22       // margen de la banda de un grupo alrededor de sus cajas
const BAND_TITLE = 24     // alto reservado arriba de la banda para el nombre del grupo

const round = n => Math.round(n * 10) / 10 + 0   // el + 0 evita el -0
const isNum = v => typeof v === 'number' && Number.isFinite(v)

/** Forma válida (`rounded` si no se conoce). */
export const normalizeShape = s => (SHAPES.includes(s) ? s : DEFAULT_SHAPE)

/** Ancho y alto de la caja de una forma. */
export function sizeOf(shape) {
    const [w, h] = SHAPE_SIZE[normalizeShape(shape)]
    return { w, h }
}

/** Ajusta un valor a la rejilla; con `grid` <= 0 lo deja como está. */
export const snap = (v, grid) => (grid > 0 ? Math.round(v / grid) * grid + 0 : v)

export const center = box => ({ x: box.x + box.w / 2, y: box.y + box.h / 2 })

/** Ancho aproximado de una etiqueta (sin medir el DOM, para poder calcularlo en puro). */
export const labelWidth = text => (text ? String(text).length * 6.6 + 14 : 0)

/**
 * Punto donde el rayo que sale del centro de la caja hacia `target` corta el borde de SU FORMA
 * (rectángulo y variantes, rombo o círculo). Si el objetivo coincide con el centro devuelve el centro.
 */
export function borderPoint(box, target) {
    const c = center(box)
    const dx = target.x - c.x
    const dy = target.y - c.y
    if (dx === 0 && dy === 0) return c
    let s
    if (box.shape === 'circle') {
        s = box.w / 2 / Math.hypot(dx, dy)
    } else if (box.shape === 'diamond') {
        s = 1 / (Math.abs(dx) / (box.w / 2) + Math.abs(dy) / (box.h / 2))
    } else {
        s = Math.min(
            dx === 0 ? Infinity : box.w / 2 / Math.abs(dx),
            dy === 0 ? Infinity : box.h / 2 / Math.abs(dy),
        )
    }
    return { x: round(c.x + dx * s), y: round(c.y + dy * s) }
}

/** Id de la caja que contiene el punto (la última de la lista gana: es la que se pinta encima). */
export function boxAt(boxes, point) {
    let found = null
    for (const [id, b] of Object.entries(boxes)) {
        if (point.x >= b.x && point.x <= b.x + b.w && point.y >= b.y && point.y <= b.y + b.h) found = id
    }
    return found
}

// ── Colocación automática ──────────────────────────────────────────────────

/** Rango (columna) de cada nodo: camino más largo, ignorando las flechas que cierran un ciclo. */
export function ranks(ids, edges) {
    const out = Object.fromEntries(ids.map(id => [id, []]))
    for (const e of edges) {
        if (e.from !== e.to && out[e.from] && out[e.to]) out[e.from].push(e.to)
    }
    // DFS: las flechas hacia un nodo "en curso" son de retroceso y se descartan.
    const state = {}
    const forward = Object.fromEntries(ids.map(id => [id, []]))
    const visit = id => {
        state[id] = 1
        for (const to of out[id]) {
            if (state[to] === 1) continue
            forward[id].push(to)
            if (!state[to]) visit(to)
        }
        state[id] = 2
    }
    for (const id of ids) if (!state[id]) visit(id)

    const indeg = Object.fromEntries(ids.map(id => [id, 0]))
    for (const id of ids) for (const to of forward[id]) indeg[to]++
    const rank = Object.fromEntries(ids.map(id => [id, 0]))
    const queue = ids.filter(id => indeg[id] === 0)
    while (queue.length) {
        const id = queue.shift()
        for (const to of forward[id]) {
            rank[to] = Math.max(rank[to], rank[id] + 1)
            if (--indeg[to] === 0) queue.push(to)
        }
    }
    return rank
}

const overlaps = (a, b, gap) =>
    a.x < b.x + b.w + gap && b.x < a.x + a.w + gap && a.y < b.y + b.h + gap && b.y < a.y + a.h + gap

const mean = xs => xs.reduce((a, b) => a + b, 0) / xs.length

/**
 * Posición transversal (en "huecos" de una caja) de cada nodo en su capa. Estilo Sugiyama sencillo:
 * orden por baricentro (barridos hacia abajo y hacia arriba) para reducir cruces, y colocación que
 * centra cada padre sobre sus hijos. Con `lanes` (grupos), cada grupo ocupa su intervalo.
 * @param {string[][]} layers ids por capa, en orden de entrada
 * @param {Object<string, string[]>} parents, children vecinos por id (solo flechas hacia delante)
 * @param {Object<string, string|null>} groupOf
 * @param {{lo: Object<string, number>, hi: Object<string, number>}|null} lanes intervalos por grupo
 * @param {Object<string, number>} init posiciones de partida
 */
function relax(layers, parents, children, groupOf, lanes, init) {
    const pos = { ...init }
    const gap = (a, b) => 1 + (groupOf[a] !== groupOf[b] && (groupOf[a] || groupOf[b]) ? 0.35 : 0)
    const bary = (ids, fallback) => (ids.length ? mean(ids.map(id => pos[id])) : fallback)
    // Un padre queda en el punto medio entre su primer y su último hijo, no en la media.
    const midpoint = (ids, fallback) => (ids.length ? (Math.min(...ids.map(id => pos[id])) + Math.max(...ids.map(id => pos[id]))) / 2 : fallback)
    const laneMid = g => (lanes.lo[g] + lanes.hi[g]) / 2

    const place = (layer, via) => {
        const want = {}
        for (const id of layer) {
            want[id] = (via === children ? midpoint : bary)(via[id], pos[id])
            const g = groupOf[id]
            if (lanes && g) want[id] = Math.min(lanes.hi[g], Math.max(lanes.lo[g], want[id]))
        }
        // Orden: los grupos como bloques (por el centro de su calle) y los demás por baricentro.
        const key = id => (lanes && groupOf[id] ? laneMid(groupOf[id]) : want[id])
        const order = layer.map((id, i) => ({ id, i })).sort((a, b) => key(a.id) - key(b.id) || want[a.id] - want[b.id] || a.i - b.i)
        layer.splice(0, layer.length, ...order.map(o => o.id))
        let prev = null
        for (const id of layer) {
            pos[id] = prev === null ? want[id] : Math.max(want[id], pos[prev] + gap(prev, id))
            prev = id
        }
        // Con calles, cada bloque de un grupo se mete dentro de su intervalo (el empuje lo pudo sacar).
        if (lanes) {
            for (const g of new Set(layer.map(id => groupOf[id]).filter(Boolean))) {
                const ids = layer.filter(id => groupOf[id] === g)
                const over = pos[ids[ids.length - 1]] - lanes.hi[g]
                const under = lanes.lo[g] - pos[ids[0]]
                const shift = over > 0 ? -over : Math.max(0, under)
                for (const id of ids) pos[id] += shift
            }
        }
        // Sin calles, el empuje hacia la derecha se reparte para que la capa quede centrada en lo que pedía.
        if (!lanes) {
            const shift = mean(layer.map(id => want[id] - pos[id]))
            for (const id of layer) pos[id] += shift
        }
    }

    for (let iter = 0; iter < 4; iter++) {
        for (let r = 1; r < layers.length; r++) place(layers[r], parents)
        for (let r = layers.length - 2; r >= 0; r--) place(layers[r], children)
    }
    return pos
}

/**
 * Posición de los nodos que no la traen. Los que sí traen x/y se respetan y se esquivan.
 * Por capas siguiendo las flechas: de arriba abajo (`vertical`) o de izquierda a derecha
 * (`horizontal`). Dentro de cada capa, ordenados para reducir cruces y con cada padre centrado
 * sobre sus hijos. Con grupos, cada grupo es una calle (una franja a lo largo de las capas) y los
 * nodos sin grupo van libres. Sin flechas ni grupos, en rejilla. Si no hay cajas fijas, el dibujo
 * se normaliza para empezar en el margen, sin hueco arriba ni a la izquierda.
 * @param {{id: string, x?: number|null, y?: number|null, shape?: string, group?: string|null}[]} nodes
 * @param {{from: string, to: string}[]} edges
 * @param {'vertical'|'horizontal'} direction
 * @returns {Object<string, {x: number, y: number}>} posición de todos los nodos
 */
export function autoLayout(nodes, edges, direction = 'vertical') {
    const horizontal = direction === 'horizontal'
    const result = {}
    const taken = []
    for (const n of nodes) {
        if (isNum(n.x) && isNum(n.y)) {
            result[n.id] = { x: n.x, y: n.y }
            taken.push({ x: n.x, y: n.y, ...sizeOf(n.shape) })
        }
    }
    const free = nodes.filter(n => !result[n.id])
    if (!free.length) return result

    // Todas las celdas miden lo mismo (la mayor caja); cada caja va centrada en la suya.
    const cellW = Math.max(...nodes.map(n => sizeOf(n.shape).w))
    const cellH = Math.max(...nodes.map(n => sizeOf(n.shape).h))
    const cell = horizontal ? { rank: cellW, cross: cellH } : { rank: cellH, cross: cellW }
    // Entre capas hace falta sitio para las etiquetas (a lo ancho) y los bucles (por debajo).
    const rankStep = cell.rank + (horizontal ? 120 : 80)
    const crossStep = cell.cross + (horizontal ? 70 : 60)

    const groups = [...new Set(free.map(n => n.group).filter(Boolean))]
    const freeIds = new Set(free.map(n => n.id))
    const linked = edges.some(e => e.from !== e.to && freeIds.has(e.from) && freeIds.has(e.to))

    if (!linked && !groups.length) {
        const cols = Math.max(1, Math.ceil(Math.sqrt(free.length)))
        free.forEach((n, i) => {
            const { w, h } = sizeOf(n.shape)
            const col = i % cols
            let row = Math.floor(i / cols)
            let box
            do {
                box = {
                    x: MARGIN + col * (cellW + 120) + (cellW - w) / 2,
                    y: MARGIN + row * (cellH + 70) + (cellH - h) / 2, w, h,
                }
                row++
            } while (taken.some(t => overlaps(box, t, 8)))
            taken.push(box)
            result[n.id] = { x: box.x, y: box.y }
        })
        return result
    }

    // Capas. Sin flechas (solo grupos): la capa es el puesto dentro del grupo.
    let rank
    if (linked) {
        rank = ranks(nodes.map(n => n.id), edges)
    } else {
        const seen = {}
        rank = Object.fromEntries(free.map(n => [n.id, (seen[n.group || ''] = (seen[n.group || ''] ?? -1) + 1)]))
    }
    const layers = []
    for (const n of free) (layers[rank[n.id]] ??= []).push(n.id)
    for (let r = 0; r < layers.length; r++) layers[r] ??= []
    const groupOf = Object.fromEntries(free.map(n => [n.id, n.group || null]))
    const parents = Object.fromEntries(free.map(n => [n.id, []]))
    const children = Object.fromEntries(free.map(n => [n.id, []]))
    for (const e of edges) {
        if (freeIds.has(e.from) && freeIds.has(e.to) && rank[e.from] < rank[e.to]) {
            if (!children[e.from].includes(e.to)) children[e.from].push(e.to)
            if (!parents[e.to].includes(e.from)) parents[e.to].push(e.from)
        }
    }

    // Primera pasada sin calles: da el orden natural de los grupos (por la posición media de sus miembros).
    const init = {}
    layers.forEach(layer => layer.forEach((id, i) => { init[id] = i }))
    let pos = relax(layers.map(l => [...l]), parents, children, groupOf, null, init)
    let lanes = null
    if (groups.length) {
        const mid = Object.fromEntries(groups.map(g => [g, mean(free.filter(n => n.group === g).map(n => pos[n.id]))]))
        const ordered = [...groups].sort((a, b) => mid[a] - mid[b])
        lanes = { lo: {}, hi: {} }
        let start = 0
        for (const g of ordered) {
            let width = 1
            for (const layer of layers) width = Math.max(width, layer.filter(id => groupOf[id] === g).length)
            lanes.lo[g] = start
            lanes.hi[g] = start + width - 1
            start += width + 0.35
        }
        const init2 = {}
        for (const layer of layers) {
            const used = {}
            for (const id of layer) {
                const g = groupOf[id]
                init2[id] = g ? lanes.lo[g] + (used[g] = (used[g] ?? -1) + 1) : pos[id]
            }
        }
        pos = relax(layers.map(l => [...l]), parents, children, groupOf, lanes, init2)
    }

    const minPos = Math.min(...free.map(n => pos[n.id]))
    const placed = {}
    for (const n of free) {
        const { w, h } = sizeOf(n.shape)
        const r = MARGIN + rank[n.id] * rankStep + (cell.rank - (horizontal ? w : h)) / 2
        const c = MARGIN + (pos[n.id] - minPos) * crossStep + (cell.cross - (horizontal ? h : w)) / 2
        placed[n.id] = horizontal ? { x: r, y: c, w, h } : { x: c, y: r, w, h }
    }

    if (!taken.length) {
        // Sin cajas fijas: el dibujo (cajas y bandas) empieza justo en el margen.
        const boxes = Object.values(placed)
        const bands = groupBands(free.map(n => ({ id: n.id, group: n.group })), placed, false)
        const minX = Math.min(...boxes.map(b => b.x), ...bands.map(b => b.x))
        const minY = Math.min(...boxes.map(b => b.y), ...bands.map(b => b.y))
        for (const b of boxes) { b.x += MARGIN - minX; b.y += MARGIN - minY }
    } else {
        // Con cajas fijas, las que no caben se desplazan a lo largo de la capa hasta quedar libres.
        for (const n of free) {
            const b = placed[n.id]
            for (let k = 0; k < 60 && taken.some(t => overlaps(b, t, 8)); k++) {
                if (horizontal) b.y += crossStep
                else b.x += crossStep
            }
            taken.push(b)
        }
    }
    for (const n of free) result[n.id] = { x: round(placed[n.id].x), y: round(placed[n.id].y) }
    return result
}

// ── Flechas ────────────────────────────────────────────────────────────────

/** Punta de flecha (triángulo) con el vértice en `tip` mirando en la dirección `dir`. */
export function arrowHead(tip, dir) {
    const len = Math.hypot(dir.x, dir.y) || 1
    const ux = dir.x / len
    const uy = dir.y / len
    const bx = tip.x - ux * ARROW
    const by = tip.y - uy * ARROW
    const px = -uy * ARROW * 0.45
    const py = ux * ARROW * 0.45
    return `M${round(tip.x)} ${round(tip.y)}L${round(bx + px)} ${round(by + py)}L${round(bx - px)} ${round(by - py)}Z`
}

const pairKey = (a, b) => (a < b ? `${a}\u0000${b}` : `${b}\u0000${a}`)

/**
 * Curva (cuadrática) de la flecha número `index` de `count` entre dos cajas.
 * Todas las flechas del par comparten la misma normal (la de la caja "menor" a la "mayor"),
 * así A→B y B→A con distinto índice no se superponen. Con una sola, recta.
 * @param {{x,y,w,h}} a caja de origen
 * @param {{x,y,w,h}} b caja de destino
 * @param {boolean} flipped true si el origen es la caja "mayor" del par (la normal se invierte)
 * @param {number} maxLabel ancho de la etiqueta más ancha del par
 */
export function pairCurve(a, b, index, count, flipped, maxLabel = 0) {
    const ca = center(a)
    const cb = center(b)
    // Normal canónica: del menor al mayor.
    let dx = flipped ? ca.x - cb.x : cb.x - ca.x
    let dy = flipped ? ca.y - cb.y : cb.y - ca.y
    const len = Math.hypot(dx, dy)
    if (len < 1) { dx = 1; dy = 0 } else { dx /= len; dy /= len }
    const nx = -dy
    const ny = dx
    const spacing = 24 + maxLabel * Math.abs(nx)
    const offset = (index - (count - 1) / 2) * spacing
    const c = { x: (ca.x + cb.x) / 2 + nx * offset * 2, y: (ca.y + cb.y) / 2 + ny * offset * 2 }
    const p0 = borderPoint(a, c)
    const p2 = borderPoint(b, c)
    return {
        p0, c, p2,
        d: `M${round(p0.x)} ${round(p0.y)}Q${round(c.x)} ${round(c.y)} ${round(p2.x)} ${round(p2.y)}`,
        head: arrowHead(p2, { x: p2.x - c.x, y: p2.y - c.y }),
        label: { x: round(0.25 * p0.x + 0.5 * c.x + 0.25 * p2.x), y: round(0.25 * p0.y + 0.5 * c.y + 0.25 * p2.y), anchor: 'middle' },
        points: [p0, c, p2],
    }
}

/**
 * Bucle de una flecha de un nodo a sí mismo: sale y vuelve por el borde de abajo, con la
 * etiqueta debajo. `radius` crece con cada bucle del mismo nodo para que no se pisen.
 */
export function selfLoop(box, radius) {
    const c = center(box)
    // Salida y llegada por abajo, un poco a cada lado del centro, sea cual sea la forma.
    const s = borderPoint(box, { x: c.x + box.w * 0.8, y: c.y + box.h * 2 })
    const e = borderPoint(box, { x: c.x - box.w * 0.8, y: c.y + box.h * 2 })
    const c1 = { x: s.x, y: s.y + radius }
    const c2 = { x: e.x, y: e.y + radius }
    const mid = { x: (s.x + e.x) / 2, y: s.y + radius * 0.75 }
    return {
        p0: s, p2: e,
        d: `M${round(s.x)} ${round(s.y)}C${round(c1.x)} ${round(c1.y)} ${round(c2.x)} ${round(c2.y)} ${round(e.x)} ${round(e.y)}`,
        head: arrowHead(e, { x: 0, y: -1 }),
        label: { x: round(mid.x), y: round(mid.y + 10), anchor: 'middle' },
        points: [s, c1, c2, e, mid],
    }
}

/**
 * Geometría de todas las flechas: reparte las del mismo par (en cualquier sentido) en curvas
 * separadas y apila los bucles de un mismo nodo.
 * @param {Object<string, {x,y,w,h}>} boxes por id de nodo
 * @param {{id: string, from: string, to: string, label?: string}[]} edges
 * @returns {Object<string, {d, head, label, points}>} por id de flecha
 */
export function layoutEdges(boxes, edges) {
    const groups = {}
    const loops = {}
    for (const e of edges) {
        if (!boxes[e.from] || !boxes[e.to]) continue
        const bucket = e.from === e.to ? loops : groups
        const key = e.from === e.to ? e.from : pairKey(e.from, e.to)
        ;(bucket[key] ??= []).push(e)
    }
    const out = {}
    for (const list of Object.values(groups)) {
        const maxLabel = Math.max(...list.map(e => labelWidth(e.label)))
        list.forEach((e, i) => {
            out[e.id] = pairCurve(boxes[e.from], boxes[e.to], i, list.length, e.from > e.to, maxLabel)
        })
    }
    for (const list of Object.values(loops)) {
        list.forEach((e, i) => { out[e.id] = selfLoop(boxes[e.from], 28 + i * 26) })
    }
    return out
}

/** Línea provisional mientras se arrastra una flecha nueva: del borde de la caja al puntero. */
export function draftLine(box, pointer) {
    const p0 = borderPoint(box, pointer)
    return {
        d: `M${round(p0.x)} ${round(p0.y)}L${round(pointer.x)} ${round(pointer.y)}`,
        head: arrowHead(pointer, { x: pointer.x - p0.x, y: pointer.y - p0.y }),
    }
}

/** Mueve un valor a [0, ∞): el lienzo no tiene coordenadas negativas. */
export const clampPos = v => Math.max(0, v)

/**
 * Bandas de fondo de los grupos: la caja que rodea a los nodos de cada grupo, con su nombre arriba.
 * @param {{id: string, group?: string|null}[]} nodes solo los que se pintan
 * @param {Object<string, {x,y,w,h}>} boxes por id de nodo
 * @returns {{name: string, x: number, y: number, w: number, h: number}[]} en orden de aparición
 */
export function groupBands(nodes, boxes, clamp = true) {
    const bands = {}
    for (const n of nodes) {
        const b = boxes[n.id]
        if (!n.group || !b) continue
        const g = (bands[n.group] ??= { name: n.group, x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity })
        g.x1 = Math.min(g.x1, b.x)
        g.y1 = Math.min(g.y1, b.y)
        g.x2 = Math.max(g.x2, b.x + b.w)
        g.y2 = Math.max(g.y2, b.y + b.h)
    }
    return Object.values(bands).map(g => {
        const x = clamp ? Math.max(0, g.x1 - BAND_PAD) : g.x1 - BAND_PAD
        const y = clamp ? Math.max(0, g.y1 - BAND_PAD - BAND_TITLE) : g.y1 - BAND_PAD - BAND_TITLE
        return { name: g.name, x, y, w: g.x2 + BAND_PAD - x, h: g.y2 + BAND_PAD - y }
    })
}

/**
 * Límites del dibujo: caja que contiene todas las cajas, las flechas, las etiquetas y las bandas, más margen.
 * @returns {{width: number, height: number}}
 */
export function drawingBounds(boxes, geos, edges = [], pad = MARGIN, bands = []) {
    let maxX = 0
    let maxY = 0
    for (const b of [...Object.values(boxes), ...bands]) {
        maxX = Math.max(maxX, b.x + b.w)
        maxY = Math.max(maxY, b.y + b.h)
    }
    const labels = Object.fromEntries(edges.map(e => [e.id, e.label]))
    for (const [id, g] of Object.entries(geos)) {
        for (const p of g.points) {
            maxX = Math.max(maxX, p.x)
            maxY = Math.max(maxY, p.y)
        }
        const w = labelWidth(labels[id])
        maxX = Math.max(maxX, g.label.anchor === 'start' ? g.label.x + w : g.label.x + w / 2)
        maxY = Math.max(maxY, g.label.y + 10)
    }
    return { width: Math.ceil(maxX + pad), height: Math.ceil(maxY + pad) }
}

/** Contorno (atributo `d` de un <path>) de la forma de una caja de `w` x `h`, metido 1 px por el trazo. */
export function shapePath(shape, w, h) {
    const x1 = 1
    const y1 = 1
    const x2 = w - 1
    const y2 = h - 1
    const rounded = r => `M${x1 + r} ${y1}H${x2 - r}A${r} ${r} 0 0 1 ${x2} ${y1 + r}V${y2 - r}A${r} ${r} 0 0 1 ${x2 - r} ${y2}H${x1 + r}A${r} ${r} 0 0 1 ${x1} ${y2 - r}V${y1 + r}A${r} ${r} 0 0 1 ${x1 + r} ${y1}Z`
    switch (normalizeShape(shape)) {
        case 'rectangle': return `M${x1} ${y1}H${x2}V${y2}H${x1}Z`
        case 'pill': return rounded((h - 2) / 2)
        case 'circle': return `M${w / 2} ${y1}A${w / 2 - 1} ${h / 2 - 1} 0 1 1 ${w / 2} ${y2}A${w / 2 - 1} ${h / 2 - 1} 0 1 1 ${w / 2} ${y1}Z`
        case 'diamond': return `M${w / 2} ${y1}L${x2} ${h / 2}L${w / 2} ${y2}L${x1} ${h / 2}Z`
        default: return rounded(12)
    }
}
