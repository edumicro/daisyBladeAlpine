import test from 'node:test'
import assert from 'node:assert/strict'
import {
    NODE_W, NODE_H, snap, borderPoint, boxAt, ranks, autoLayout, pairCurve, selfLoop, layoutEdges,
    labelWidth, drawingBounds, draftLine, clampPos, sizeOf, shapePath, MARGIN, groupBands, normalizeShape, SHAPES,
} from '../../resources/js/components/flowchart/geometry.js'
import { responder, provisionalEdge, finalizeEdge, samePos } from '../../resources/js/components/flowchart/protocol.js'
import { fitScale, centerOffset } from '../../resources/js/components/flowchart.js'
import { normalize, visible, variantColor, fmt, dblFlowchart } from '../../resources/js/components/flowchart.js'

const box = (x, y) => ({ x, y, w: NODE_W, h: NODE_H })
const overlap = (a, b) => {
    const [aw, ah, bw, bh] = [a.w ?? NODE_W, a.h ?? NODE_H, b.w ?? NODE_W, b.h ?? NODE_H]
    return a.x < b.x + bw && b.x < a.x + aw && a.y < b.y + bh && b.y < a.y + ah
}

test('snap ajusta a la rejilla y con 0 no toca nada', () => {
    assert.equal(snap(33, 20), 40)
    assert.equal(snap(29, 20), 20)
    assert.equal(snap(33, 0), 33)
    assert.equal(snap(-4, 20), 0)
})

test('borderPoint corta el borde, no el centro', () => {
    const b = box(0, 0) // centro (80, 28)
    assert.deepEqual(borderPoint(b, { x: 400, y: 28 }), { x: 160, y: 28 })       // a la derecha
    assert.deepEqual(borderPoint(b, { x: 80, y: -300 }), { x: 80, y: 0 })        // arriba
    const diag = borderPoint(b, { x: 80 + 1000, y: 28 + 1000 })                 // diagonal: sale por abajo
    assert.equal(diag.y, NODE_H)
    assert.ok(diag.x > 80 && diag.x < 160)
    assert.deepEqual(borderPoint(b, { x: 80, y: 28 }), { x: 80, y: 28 })         // objetivo = centro
})

test('boxAt devuelve la caja que contiene el punto, o null', () => {
    const boxes = { a: box(0, 0), b: box(200, 0), c: box(100, 0) } // c pisa a a y b: la última manda
    assert.equal(boxAt(boxes, { x: 10, y: 10 }), 'a')
    assert.equal(boxAt(boxes, { x: 300, y: 10 }), 'b')
    assert.equal(boxAt(boxes, { x: 150, y: 10 }), 'c')
    assert.equal(boxAt(boxes, { x: 500, y: 500 }), null)
})

test('ranks sigue las flechas y no se rompe con ciclos', () => {
    const r = ranks(['a', 'b', 'c'], [{ from: 'a', to: 'b' }, { from: 'b', to: 'c' }, { from: 'c', to: 'a' }, { from: 'b', to: 'b' }])
    assert.deepEqual(r, { a: 0, b: 1, c: 2 })
})

test('autoLayout: por capas, sin solapar y respetando las posiciones dadas', () => {
    const nodes = [{ id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd', x: 400, y: 40 }, { id: 'e' }]
    const edges = [{ from: 'a', to: 'b' }, { from: 'a', to: 'c' }, { from: 'b', to: 'e' }, { from: 'c', to: 'a' }]
    const pos = autoLayout(nodes, edges)
    assert.deepEqual(pos.d, { x: 400, y: 40 })
    assert.ok(pos.b.y > pos.a.y && pos.e.y > pos.b.y, 'de arriba abajo: cada capa debajo de la anterior')
    assert.equal(pos.b.y, pos.c.y, 'los hermanos comparten capa')
    const ids = Object.keys(pos)
    assert.equal(ids.length, 5)
    for (let i = 0; i < ids.length; i++) {
        for (let j = i + 1; j < ids.length; j++) assert.ok(!overlap(pos[ids[i]], pos[ids[j]]), `${ids[i]} solapa ${ids[j]}`)
    }
})

test('autoLayout horizontal: cada capa a la derecha de la anterior', () => {
    const nodes = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]
    const pos = autoLayout(nodes, [{ from: 'a', to: 'b' }, { from: 'b', to: 'c' }], 'horizontal')
    assert.ok(pos.b.x > pos.a.x && pos.c.x > pos.b.x)
    assert.equal(pos.a.y, pos.b.y)
})

test('autoLayout con formas distintas: sin solapes y cada caja centrada en su celda', () => {
    const nodes = [{ id: 'a', shape: 'pill' }, { id: 'b', shape: 'diamond' }, { id: 'c', shape: 'circle' }, { id: 'd', shape: 'rectangle' }]
    const pos = autoLayout(nodes, [{ from: 'a', to: 'b' }, { from: 'b', to: 'c' }, { from: 'b', to: 'd' }])
    const boxes = nodes.map(n => ({ ...pos[n.id], ...sizeOf(n.shape) }))
    for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) assert.ok(!overlap(boxes[i], boxes[j]))
    const cx = b => b.x + b.w / 2
    assert.equal(cx(boxes[0]), cx(boxes[1]), 'rango vertical: centradas en la misma columna')
})

test('autoLayout con grupos: cada grupo es una calle y las calles no se pisan', () => {
    const nodes = [
        { id: 'a', group: 'Ventas' }, { id: 'b', group: 'Almacén' }, { id: 'c', group: 'Ventas' }, { id: 'd', group: 'Almacén' }, { id: 'e' },
    ]
    const edges = [{ from: 'a', to: 'b' }, { from: 'b', to: 'c' }, { from: 'c', to: 'd' }, { from: 'd', to: 'e' }]
    for (const dir of ['vertical', 'horizontal']) {
        const pos = autoLayout(nodes, edges, dir)
        const boxes = Object.fromEntries(Object.keys(pos).map(id => [id, { ...pos[id], ...sizeOf() }]))
        const bands = groupBands(nodes, boxes)
        assert.deepEqual(bands.map(b => b.name), ['Ventas', 'Almacén'])
        const [v, a] = bands
        const apart = v.x + v.w <= a.x || a.x + a.w <= v.x || v.y + v.h <= a.y || a.y + a.h <= v.y
        assert.ok(apart, `${dir}: las bandas no se solapan`)
        for (const id of ['b', 'd']) assert.ok(!overlap(boxes[id], v), `${dir}: ${id} no cae en la banda de Ventas`)
    }
})

test('autoLayout: sin flechas, rejilla sin solapes', () => {
    const nodes = Array.from({ length: 7 }, (_, i) => ({ id: `n${i}` }))
    const pos = autoLayout(nodes, [])
    const ids = Object.keys(pos)
    assert.equal(ids.length, 7)
    for (let i = 0; i < ids.length; i++) {
        for (let j = i + 1; j < ids.length; j++) assert.ok(!overlap(pos[ids[i]], pos[ids[j]]))
    }
    assert.equal(new Set(ids.map(id => pos[id].x)).size, 3, 'ceil(sqrt(7)) = 3 columnas')
})

test('autoLayout esquiva una caja fija que ocupa el sitio', () => {
    const pos = autoLayout([{ id: 'fija', x: 40, y: 40 }, { id: 'a' }], [])
    assert.ok(!overlap(pos.fija, pos.a))
})

test('borderPoint corta el borde de la forma: rombo y círculo', () => {
    const diamond = { x: 0, y: 0, ...sizeOf('diamond'), shape: 'diamond' } // 180 x 90, centro (90, 45)
    assert.deepEqual(borderPoint(diamond, { x: 500, y: 45 }), { x: 180, y: 45 })   // vértice derecho
    assert.deepEqual(borderPoint(diamond, { x: 90, y: -400 }), { x: 90, y: 0 })    // vértice superior
    const d = borderPoint(diamond, { x: 90 + 100, y: 45 + 100 })                   // diagonal: sobre la arista
    assert.ok(Math.abs((d.x - 90) / 90 + (d.y - 45) / 45 - 1) < 0.02, 'queda sobre la arista, no en el rectángulo')
    const circle = { x: 0, y: 0, ...sizeOf('circle'), shape: 'circle' }           // 84 x 84, centro (42, 42)
    const c = borderPoint(circle, { x: 42 + 300, y: 42 + 400 })
    assert.ok(Math.abs(Math.hypot(c.x - 42, c.y - 42) - 42) < 0.1, 'a un radio del centro')
    // El rectángulo y sus variantes siguen cortando el rectángulo.
    assert.deepEqual(borderPoint({ ...box(0, 0), shape: 'pill' }, { x: 400, y: 28 }), { x: 160, y: 28 })
})

test('sizeOf y normalizeShape: formas conocidas, y `rounded` por defecto', () => {
    assert.deepEqual(SHAPES, ['rectangle', 'rounded', 'pill', 'diamond', 'circle'])
    assert.equal(normalizeShape('hexagon'), 'rounded')
    assert.equal(normalizeShape(undefined), 'rounded')
    assert.deepEqual(sizeOf('circle'), { w: 84, h: 84 })
    assert.deepEqual(sizeOf('zzz'), { w: NODE_W, h: NODE_H })
})

test('shapePath: un contorno cerrado por forma', () => {
    for (const sh of SHAPES) {
        const { w, h } = sizeOf(sh)
        const d = shapePath(sh, w, h)
        assert.match(d, /^M/)
        assert.match(d, /Z$/, sh)
    }
    assert.equal(shapePath('rectangle', 160, 56), 'M1 1H159V55H1Z')
    assert.match(shapePath('diamond', 180, 90), /^M90 1L179 45L90 89L1 45Z$/)
})

test('groupBands rodea a los miembros de cada grupo y deja sitio al nombre', () => {
    const boxes = { a: box(100, 100), b: box(400, 300), c: box(900, 900) }
    const [band] = groupBands([{ id: 'a', group: 'X' }, { id: 'b', group: 'X' }, { id: 'c' }], boxes)
    assert.equal(band.name, 'X')
    assert.ok(band.x < 100 && band.y < 100 - 24, 'margen y título')
    assert.ok(band.x + band.w > 400 + NODE_W && band.y + band.h > 300 + NODE_H)
    assert.equal(groupBands([{ id: 'c' }], boxes).length, 0)
    assert.equal(groupBands([{ id: 'a', group: 'X' }], { a: box(0, 0) })[0].x, 0, 'nunca sale por la izquierda')
})

test('pairCurve: una sola flecha es recta y parte del borde de cada caja', () => {
    const a = box(0, 0)
    const b = box(400, 0)
    const g = pairCurve(a, b, 0, 1, false)
    assert.equal(g.c.y, 28)
    assert.deepEqual(g.p0, { x: 160, y: 28 })
    assert.deepEqual(g.p2, { x: 400, y: 28 })
    assert.match(g.d, /^M160 28Q280 28 400 28$/)
})

test('pairCurve: varias flechas del par tienen curvas y etiquetas distintas', () => {
    const a = box(0, 0)
    const b = box(400, 0)
    const g = [0, 1, 2].map(i => pairCurve(a, b, i, 3, false, labelWidth('genera')))
    const ys = g.map(x => x.label.y)
    assert.equal(new Set(ys).size, 3)
    assert.ok(Math.min(Math.abs(ys[0] - ys[1]), Math.abs(ys[1] - ys[2])) >= 24, 'etiquetas a >= 24 px una de otra')
})

test('pairCurve: con el par en vertical las etiquetas se separan en horizontal lo bastante', () => {
    const a = box(0, 0)
    const b = box(0, 300)
    const w = labelWidth('un texto largo')
    const [g0, g1] = [0, 1].map(i => pairCurve(a, b, i, 2, false, w))
    assert.ok(Math.abs(g0.label.x - g1.label.x) >= w, 'no se pisan')
})

test('layoutEdges: ida y vuelta no se superponen', () => {
    const boxes = { a: box(0, 0), b: box(400, 0) }
    const g = layoutEdges(boxes, [
        { id: 'ida', from: 'a', to: 'b', label: 'envía' },
        { id: 'vuelta', from: 'b', to: 'a', label: 'devuelve' },
    ])
    assert.notEqual(g.ida.label.y, g.vuelta.label.y)
    assert.notEqual(g.ida.c.y, g.vuelta.c.y)
    // Cada una llega a su destino: la ida acaba en b, la vuelta en a.
    assert.ok(g.ida.p2.x > 300)
    assert.ok(g.vuelta.p2.x < 200)
})

test('layoutEdges: el sentido no cambia el reparto, solo el orden de entrada', () => {
    const boxes = { a: box(0, 0), b: box(400, 0) }
    const g = layoutEdges(boxes, [
        { id: 'x', from: 'a', to: 'b' }, { id: 'y', from: 'a', to: 'b' }, { id: 'z', from: 'b', to: 'a' },
    ])
    const cs = ['x', 'y', 'z'].map(id => g[id].c.y)
    assert.equal(new Set(cs).size, 3)
})

test('layoutEdges: bucles propios apilados y descarta flechas con nodos que no existen', () => {
    const boxes = { a: box(0, 0) }
    const g = layoutEdges(boxes, [
        { id: 'l1', from: 'a', to: 'a', label: 'reintenta' },
        { id: 'l2', from: 'a', to: 'a', label: 'espera' },
        { id: 'rota', from: 'a', to: 'zzz' },
    ])
    assert.equal(g.rota, undefined)
    assert.ok(g.l2.points[1].y > g.l1.points[1].y, 'el segundo bucle es más grande')
    assert.ok(g.l2.label.y - g.l1.label.y >= 16, 'etiquetas de bucles no se pisan')
})

test('selfLoop sale y vuelve por el borde de abajo de la caja', () => {
    const g = selfLoop(box(0, 0), 28)
    assert.equal(g.p0.y, NODE_H)
    assert.equal(g.p2.y, NODE_H)
    assert.ok(g.p0.x > g.p2.x)
    assert.equal(g.label.anchor, 'middle')
    assert.ok(g.label.y > NODE_H)
    assert.ok(g.head.startsWith(`M${g.p2.x} ${NODE_H}`))
})

test('drawingBounds abarca cajas, curvas y etiquetas', () => {
    const boxes = { a: box(0, 0), b: box(400, 100) }
    const edges = [{ id: 'l', from: 'b', to: 'b', label: 'una etiqueta bastante larga' }]
    const geos = layoutEdges(boxes, edges)
    const b = drawingBounds(boxes, geos, edges, 40)
    assert.ok(b.width >= 400 + NODE_W + 40)
    assert.ok(b.height >= geos.l.label.y + 40, 'el bucle y su etiqueta caben')
    assert.deepEqual(drawingBounds({}, {}, [], 40), { width: 40, height: 40 })
})

test('draftLine arranca en el borde de la caja y acaba en el puntero', () => {
    const g = draftLine(box(0, 0), { x: 400, y: 28 })
    assert.match(g.d, /^M160 28L400 28$/)
    assert.ok(g.head.startsWith('M400 28'))
})

test('clampPos no deja coordenadas negativas', () => {
    assert.equal(clampPos(-5), 0)
    assert.equal(clampPos(12), 12)
})

// ── Datos y componente ─────────────────────────────────────────────────────

test('normalize: ids como texto, sin duplicados, sin flechas colgando y varias entre el mismo par', () => {
    const { nodes, edges } = normalize(
        [{ id: 1, label: 'Pedido', x: '40', y: 'x' }, { id: 2 }, { id: 1, label: 'repe' }, null],
        [{ id: 'e1', from: 1, to: 2, label: 'a' }, { id: 'e2', from: 1, to: 2, label: 'b' }, { from: 2, to: 9 }, { id: 'e1', from: 2, to: 2 }],
    )
    assert.equal(nodes.length, 2)
    assert.equal(nodes[0].id, 1, 'el id original se conserva para los eventos')
    assert.equal(nodes[0].key, '1')
    assert.equal(nodes[0].x, 40)
    assert.equal(nodes[0].y, null)
    assert.equal(nodes[1].label, '2')
    assert.equal(edges.length, 3)
    assert.equal(new Set(edges.map(e => e.key)).size, 3, 'claves únicas aunque el id se repita')
})

test('normalize: forma, grupo, descripción e icono, con su tamaño', () => {
    const { nodes } = normalize([
        { id: 1, shape: 'diamond', group: 'Calle', description: 'texto', icon: 'heroicon-o-check' },
        { id: 2, shape: 'hexagono' }, { id: 3 },
    ])
    assert.deepEqual([nodes[0].shape, nodes[0].w, nodes[0].h], ['diamond', 180, 90])
    assert.deepEqual([nodes[0].group, nodes[0].description, nodes[0].icon], ['Calle', 'texto', 'heroicon-o-check'])
    assert.equal(nodes[1].shape, 'rounded')
    assert.deepEqual([nodes[2].group, nodes[2].description, nodes[2].icon], [null, '', null])
})

test('el componente respeta direction y pinta bandas para los grupos', () => {
    const cfg = {
        direction: 'horizontal',
        nodes: [{ id: 'a', group: 'G' }, { id: 'b', group: 'G' }, { id: 'c' }],
        edges: [{ id: 1, from: 'a', to: 'b' }],
    }
    const { c } = make(cfg)
    assert.ok(c.pos.b.x > c.pos.a.x)
    assert.equal(c.bands.length, 1)
    assert.equal(make({ ...cfg, direction: 'raro' }).c.direction, 'vertical')
})

test('visible oculta los muted y las flechas que cuelgan de ellos', () => {
    const { nodes, edges } = normalize(
        [{ id: 'a' }, { id: 'b', muted: true }, { id: 'c' }],
        [{ id: 1, from: 'a', to: 'b' }, { id: 2, from: 'a', to: 'c', muted: true }, { id: 3, from: 'a', to: 'c' }],
    )
    const off = visible(nodes, edges, false)
    assert.deepEqual(off.vNodes.map(n => n.key), ['a', 'c'])
    assert.deepEqual(off.vEdges.map(e => e.key), ['3'])
    assert.equal(visible(nodes, edges, true).vEdges.length, 3)
})

test('variantColor usa el mapa genérico y cae al color por defecto', () => {
    assert.equal(variantColor('success', 'x'), 'var(--color-success)')
    assert.equal(variantColor('warning', 'x'), 'var(--color-warning)')
    assert.equal(variantColor('error', 'x'), 'var(--color-error)')
    assert.match(variantColor('neutral', 'x'), /base-content/)
    assert.equal(variantColor('otra', 'x'), 'x')
    assert.equal(variantColor(null, 'x'), 'x')
})

test('fmt sustituye :nombre y deja lo desconocido', () => {
    assert.equal(fmt('Flecha de :from a :to', { from: 'A', to: 'B' }), 'Flecha de A a B')
    assert.equal(fmt(':x y :y', { x: 1 }), '1 y :y')
})

// Componente sin Alpine: se le dan a mano `$root` (que recoge los eventos) y `$refs`.
// `listener(ev)` hace de padre: puede llamar a `ev.preventDefault()`, `accept` o `reject`.
const make = (cfg, listener = () => {}) => {
    const events = []
    const root = {
        dispatchEvent(ev) {
            events.push({ name: ev.type, detail: ev.detail })
            listener(ev)
            return !ev.defaultPrevented
        },
    }
    const c = Object.assign(dblFlowchart(cfg), {
        $root: root,
        $nextTick: fn => fn(),
        $refs: { canvas: { getBoundingClientRect: () => ({ left: 0, top: 0 }) } },
    })
    c.init()
    return { c, events }
}
// Los avisos salen por `window`: en node se recogen en un EventTarget.
globalThis.window = new EventTarget()
const notices = []
window.addEventListener('notify', ev => notices.push(ev.detail))
const plain = ({ accept, reject, ...rest }) => rest   // el detalle sin las funciones de respuesta
const pe = (x, y, over = {}) => ({ clientX: x, clientY: y, pointerType: 'mouse', button: 0, pointerId: 1, currentTarget: { setPointerCapture() {} }, stopPropagation() {}, preventDefault() {}, ...over })
const base = () => ({
    editable: true, grid: 20,
    nodes: [{ id: 'a', label: 'A', x: 40, y: 40, flat: { k: 1 } }, { id: 'b', label: 'B', x: 400, y: 40, flat: 'fb' }],
    edges: [{ id: 'e', from: 'a', to: 'b' }],
})

test('clic sin mover emite node-click con id y flat', () => {
    const { c, events } = make(base())
    const n = c.vNodes[0]
    c.nodeDown(pe(10, 10), n); c.nodeUp(pe(10, 10), n)
    assert.deepEqual(events, [{ name: 'node-click', detail: { id: 'a', flat: { k: 1 } } }])
    c.edgeClick(c.vEdges[0])
    assert.deepEqual(events[1], { name: 'edge-click', detail: { id: 'e', flat: null } })
})

test('arrastrar una caja emite node-move ajustado a la rejilla', () => {
    const { c, events } = make(base())
    const n = c.vNodes[0]
    c.nodeDown(pe(100, 100), n)
    c.nodeMove(pe(133, 122), n)
    assert.deepEqual(c.pos.a, { x: 73, y: 62 })
    c.nodeUp(pe(133, 122), n)
    assert.equal(events[0].name, 'node-move')
    assert.deepEqual(plain(events[0].detail), { id: 'a', x: 80, y: 60, flat: { k: 1 } })
    assert.deepEqual(c.pos.a, { x: 80, y: 60 }, 'la caja se queda en su sitio nuevo')
})

test('sin editable las cajas no se mueven', () => {
    const { c, events } = make({ ...base(), editable: false })
    const n = c.vNodes[0]
    c.nodeDown(pe(100, 100), n); c.nodeMove(pe(200, 200), n); c.nodeUp(pe(200, 200), n)
    assert.deepEqual(c.pos.a, { x: 40, y: 40 })
    assert.ok(!events.some(e => e.name === 'node-move'))
})

test('arrastrar desde el asa y soltar sobre otra caja emite edge-create; sobre sí misma, también', () => {
    const { c, events } = make(base())
    const a = c.vNodes[0]
    c.linkDown(pe(200, 68), a)
    c.linkMove(pe(450, 60))
    assert.equal(c.linking.over, 'b')
    c.linkUp(pe(450, 60))
    const first = events.pop()
    assert.equal(first.name, 'edge-create')
    assert.deepEqual(plain(first.detail), { fromId: 'a', toId: 'b', fromFlat: { k: 1 }, toFlat: 'fb' })
    assert.equal(typeof first.detail.accept, 'function')
    assert.equal(typeof first.detail.reject, 'function')
    c.linkDown(pe(200, 68), a); c.linkMove(pe(100, 60)); c.linkUp(pe(100, 60))
    assert.deepEqual(plain(events.pop().detail), { fromId: 'a', toId: 'a', fromFlat: { k: 1 }, toFlat: { k: 1 } })
})

test('soltar el asa en el vacío, o sin haberla movido, no emite nada', () => {
    const { c, events } = make(base())
    const a = c.vNodes[0]
    c.linkDown(pe(200, 68), a); c.linkMove(pe(300, 400)); c.linkUp(pe(300, 400))
    c.linkDown(pe(200, 68), a); c.linkUp(pe(200, 68))
    assert.equal(events.length, 0)
    assert.equal(c.linking, null)
})

test('teclado: flechas mueven la caja y al soltar la tecla se emite node-move; Shift = rejilla', () => {
    const { c, events } = make(base())
    const n = c.vNodes[0]
    const key = (k, shiftKey = false) => ({ key: k, shiftKey, preventDefault() {} })
    c.nodeKeydown(key('ArrowRight'), n); c.nodeKeydown(key('ArrowRight'), n)
    assert.deepEqual(c.pos.a, { x: 60, y: 40 })
    assert.equal(events.length, 0, 'no se emite mientras se mantiene')
    c.nodeKeyup(key('ArrowRight'), n)
    assert.deepEqual(plain(events.pop().detail), { id: 'a', x: 60, y: 40, flat: { k: 1 } })
    c.nodeKeydown(key('ArrowDown', true), n); c.nodeKeyup(key('ArrowDown'), n)
    assert.equal(c.pos.a.y, 60)
    events.pop().detail.reject()
    assert.deepEqual(c.pos.a, { x: 60, y: 40 }, 'rechazar devuelve la caja a donde estaba antes de esa pulsación')
    c.nodeKeydown(key('Enter'), n)
    assert.equal(events.pop().name, 'node-click')
})

test('set reemplaza el estado y conserva la posición de los nodos sin x/y', () => {
    const { c } = make({ nodes: [{ id: 'a' }, { id: 'b' }], edges: [] })
    const before = { ...c.pos.a }
    c.set([{ id: 'a' }, { id: 'c' }], [{ id: 'e', from: 'a', to: 'c' }])
    assert.deepEqual(c.pos.a, before)
    assert.equal(c.vNodes.length, 2)
    assert.equal(c.vEdges.length, 1)
})

test('los muted se ocultan hasta activar el interruptor, y focus los muestra', () => {
    const { c } = make({ nodes: [{ id: 'a' }, { id: 'b', muted: true }], edges: [{ id: 'e', from: 'a', to: 'b' }] })
    assert.equal(c.hasMuted, true)
    assert.equal(c.vNodes.length, 1)
    assert.equal(c.vEdges.length, 0)
    c.toggleMuted()
    assert.equal(c.vNodes.length, 2)
    c.toggleMuted()
    c.focus('b')
    assert.equal(c.showMuted, true)
    assert.equal(c.focusKey, 'b')
    clearTimeout(c._focusTimer)
})

test('el evento de ventana solo lo atiende el diagrama con ese name', () => {
    const { c } = make({ ...base(), name: 'flujo' })
    c.onWindowEvent({ name: 'otro', action: 'set', nodes: [], edges: [] })
    assert.equal(c.vNodes.length, 2)
    c.onWindowEvent({ name: 'flujo', action: 'set', nodes: [{ id: 'z' }], edges: [] })
    assert.equal(c.vNodes.length, 1)
    const anon = make(base()).c
    anon.onWindowEvent({ name: '', action: 'set', nodes: [], edges: [] })
    assert.equal(anon.vNodes.length, 2)
})

// ── Protocolo accept / reject ──────────────────────────────────────────────

const drag = (c, from = pe(200, 68), to = pe(450, 60)) => {
    c.linkDown(from, c.vNodes[0]); c.linkMove(to); c.linkUp(to)
}

test('responder: solo cuenta la primera respuesta', () => {
    const calls = []
    const r = responder({ onAccept: d => calls.push(['ok', d]), onReject: m => calls.push(['no', m]) })
    assert.equal(r.settled, false)
    r.accept({ label: 'x' }); r.reject('tarde'); r.accept()
    assert.deepEqual(calls, [['ok', { label: 'x' }]])
    assert.equal(r.settled, true)
})

test('finalizeEdge: mezcla lo que devuelve el padre, no deja cambiar los extremos y no repite claves', () => {
    const prov = provisionalEdge('a', 'b', 1)
    assert.equal(prov.provisional, true)
    const e = finalizeEdge(prov, { id: 7, label: 'si procede', variant: 'success', flat: { x: 1 }, from: 'zzz' }, ['8'])
    assert.deepEqual([e.id, e.key, e.from, e.to, e.label, e.variant, e.flat, e.provisional], [7, '7', 'a', 'b', 'si procede', 'success', { x: 1 }, false])
    assert.equal(finalizeEdge(prov, { id: 8 }, ['8']).key, '8_')
    const bare = finalizeEdge(prov)
    assert.equal(bare.label, '')
    assert.ok(bare.id.startsWith('a>b#'), 'sin id, uno generado')
    assert.equal(finalizeEdge(prov, 'raro').from, 'a', 'un accept con algo que no es objeto se ignora')
})

test('samePos compara redondeando', () => {
    assert.equal(samePos({ x: 1.2, y: 2 }, { x: 1, y: 2.4 }), true)
    assert.equal(samePos({ x: 1, y: 2 }, { x: 3, y: 2 }), false)
    assert.equal(samePos(null, { x: 1, y: 2 }), false)
})

test('edge-create sin oyentes que respondan: la flecha se fija tal cual', () => {
    const { c } = make(base())
    drag(c)
    assert.equal(c.edges.length, 2)
    const e = c.edges[1]
    assert.equal(e.provisional, false)
    assert.deepEqual([e.from, e.to], ['a', 'b'])
    assert.equal(c.vEdges.length, 2)
})

test('edge-create con preventDefault: queda provisional hasta que el padre acepte, con sus datos', () => {
    let answer
    const { c } = make(base(), ev => { if (ev.type === 'edge-create') { ev.preventDefault(); answer = ev.detail } })
    drag(c)
    assert.equal(c.edges[1].provisional, true, 'se pinta provisional')
    assert.equal(c.vEdges.length, 2, 'y es visible, con geometría')
    assert.ok(c.geo[c.edges[1].key])
    answer.accept({ id: 'nueva', label: 'si procede', variant: 'success', flat: { regla: 1 } })
    const e = c.edges[1]
    assert.deepEqual([e.provisional, e.id, e.label, e.variant, e.flat], [false, 'nueva', 'si procede', 'success', { regla: 1 }])
    assert.ok(c.geo[e.key])
    assert.equal(c.geo['__provisional_1'], undefined)
})

test('edge-create: reject quita la provisional y avisa con `notify`', () => {
    let answer
    const { c } = make(base(), ev => { if (ev.type === 'edge-create') { ev.preventDefault(); answer = ev.detail } })
    notices.length = 0
    drag(c)
    answer.reject('No se pueden unir esos dos pasos')
    assert.equal(c.edges.length, 1)
    assert.deepEqual(notices, [{ message: 'No se pueden unir esos dos pasos', type: 'error' }])
    answer.accept({ id: 'tarde' })
    assert.equal(c.edges.length, 1, 'una respuesta tardía no resucita nada')
})

test('edge-create: reject sin mensaje no avisa; accept o reject síncronos valen sin preventDefault', () => {
    notices.length = 0
    const rej = make(base(), ev => { if (ev.type === 'edge-create') ev.detail.reject() }).c
    drag(rej)
    assert.equal(rej.edges.length, 1)
    assert.equal(notices.length, 0)
    const acc = make(base(), ev => { if (ev.type === 'edge-create') ev.detail.accept({ label: 'sí' }) }).c
    drag(acc)
    assert.equal(acc.edges.length, 2)
    assert.equal(acc.edges[1].label, 'sí')
})

test('edge-create: si el estado se reemplaza con set() antes de responder, accept no hace nada', () => {
    let answer
    const { c } = make(base(), ev => { if (ev.type === 'edge-create') { ev.preventDefault(); answer = ev.detail } })
    drag(c)
    c.set(base().nodes, base().edges)
    answer.accept({ id: 'x' })
    assert.equal(c.edges.length, 1)
})

test('node-move: reject devuelve la caja y avisa; accept la deja', () => {
    notices.length = 0
    const rej = make(base(), ev => { if (ev.type === 'node-move') ev.detail.reject('No se puede mover') }).c
    const n = rej.vNodes[0]
    rej.nodeDown(pe(100, 100), n); rej.nodeMove(pe(160, 140), n); rej.nodeUp(pe(160, 140), n)
    assert.deepEqual(rej.pos.a, { x: 40, y: 40 })
    assert.deepEqual(notices, [{ message: 'No se puede mover', type: 'error' }])
    const acc = make(base(), ev => { if (ev.type === 'node-move') ev.detail.accept() }).c
    acc.nodeDown(pe(100, 100), acc.vNodes[0]); acc.nodeMove(pe(160, 140), acc.vNodes[0]); acc.nodeUp(pe(160, 140), acc.vNodes[0])
    assert.deepEqual(acc.pos.a, { x: 100, y: 80 })
})

test('node-move: un reject tardío no pisa un movimiento posterior', () => {
    let answer
    const { c } = make(base(), ev => { if (ev.type === 'node-move') { ev.preventDefault(); answer = ev.detail } })
    const n = c.vNodes[0]
    c.nodeDown(pe(100, 100), n); c.nodeMove(pe(160, 140), n); c.nodeUp(pe(160, 140), n)
    const first = answer
    c.nodeDown(pe(100, 100), n); c.nodeMove(pe(200, 200), n); c.nodeUp(pe(200, 200), n)
    first.reject()
    assert.deepEqual(c.pos.a, { x: 200, y: 180 }, 'se queda donde lo dejó el segundo movimiento')
})

test('removeEdge y removeNode quitan sin emitir nada; removeNode arrastra sus flechas', () => {
    const { c, events } = make(base())
    c.removeEdge('e')
    assert.equal(c.edges.length, 0)
    const g = make({ ...base(), edges: [{ id: 'e', from: 'a', to: 'b' }, { id: 'f', from: 'b', to: 'b' }] }).c
    g.removeNode('b')
    assert.deepEqual(g.vNodes.map(n => n.key), ['a'])
    assert.equal(g.edges.length, 0)
    assert.equal(g.pos.b, undefined)
    assert.equal(events.length, 0)
})

test('el evento de ventana admite removeEdge y removeNode, y moveBack ya no existe', () => {
    const { c } = make({ ...base(), name: 'flujo' })
    assert.equal(c.moveBack, undefined)
    c.onWindowEvent({ name: 'flujo', action: 'removeEdge', id: 'e' })
    assert.equal(c.vEdges.length, 0)
    c.onWindowEvent({ name: 'flujo', action: 'removeNode', id: 'a' })
    assert.equal(c.vNodes.length, 1)
})

test('una flecha provisional no emite edge-click', () => {
    let answer
    const { c, events } = make(base(), ev => { if (ev.type === 'edge-create') { ev.preventDefault(); answer = ev.detail } })
    drag(c)
    events.length = 0
    c.edgeClick(c.edges[1])
    assert.equal(events.length, 0)
    answer.accept()
    c.edgeClick(c.edges[1])
    assert.equal(events.at(-1).name, 'edge-click')
})

// ── Organigrama: la colocación automática de un árbol con departamentos ────

const orgNodes = [
    { id: 'ceo', shape: 'rectangle' },
    { id: 'ops', group: 'Operaciones' }, { id: 'fin', group: 'Finanzas' }, { id: 'hr', group: 'Personas' },
    { id: 'log', group: 'Operaciones' }, { id: 'prod', group: 'Operaciones' }, { id: 'acc', group: 'Finanzas' }, { id: 'vac', group: 'Personas' },
]
const orgEdges = [['ceo', 'ops'], ['ceo', 'fin'], ['ceo', 'hr'], ['ops', 'log'], ['ops', 'prod'], ['fin', 'acc'], ['hr', 'vac']]
    .map(([from, to]) => ({ from, to }))
const centerOf = (pos, id, nodes) => { const b = { ...pos[id], ...sizeOf(nodes.find(n => n.id === id).shape) }; return { x: b.x + b.w / 2, y: b.y + b.h / 2 } }
const ccw = (a, b, c) => (c.y - a.y) * (b.x - a.x) > (b.y - a.y) * (c.x - a.x)
const cross = (p1, p2, p3, p4) => ccw(p1, p3, p4) !== ccw(p2, p3, p4) && ccw(p1, p2, p3) !== ccw(p1, p2, p4)

test('organigrama vertical: raíz centrada sobre sus hijos, sin cruces y empezando en el margen', () => {
    const pos = autoLayout(orgNodes, orgEdges)
    const c = id => centerOf(pos, id, orgNodes)
    const kids = ['ops', 'fin', 'hr'].map(c)
    assert.ok(Math.abs(c('ceo').x - (Math.min(...kids.map(k => k.x)) + Math.max(...kids.map(k => k.x))) / 2) < 1, 'raíz centrada sobre sus hijos directos')
    assert.ok(kids.every(k => k.y > c('ceo').y), 'los hijos, debajo')
    assert.ok(c('log').y > c('ops').y)
    for (let i = 0; i < orgEdges.length; i++) {
        for (let j = i + 1; j < orgEdges.length; j++) {
            const [a, b] = [orgEdges[i], orgEdges[j]]
            if (a.from === b.from || a.to === b.to || a.from === b.to || a.to === b.from) continue
            assert.ok(!cross(c(a.from), c(a.to), c(b.from), c(b.to)), `${a.from}>${a.to} cruza ${b.from}>${b.to}`)
        }
    }
    const boxes = Object.fromEntries(orgNodes.map(n => [n.id, { ...pos[n.id], ...sizeOf(n.shape) }]))
    const bands = groupBands(orgNodes, boxes, false)
    assert.equal(Math.min(...Object.values(boxes).map(b => b.x), ...bands.map(b => b.x)), MARGIN, 'sin hueco a la izquierda')
    assert.equal(Math.min(...Object.values(boxes).map(b => b.y), ...bands.map(b => b.y)), MARGIN, 'sin hueco arriba')
    // Cada departamento en su calle: las bandas no se solapan y la raíz (sin grupo) no cae dentro de ninguna.
    for (let i = 0; i < bands.length; i++) {
        for (let j = i + 1; j < bands.length; j++) {
            const [a, b] = [bands[i], bands[j]]
            assert.ok(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y, 'bandas sin solape')
        }
        assert.ok(!overlap(boxes.ceo, bands[i]), 'la raíz no cae dentro de una banda')
    }
})

test('organigrama horizontal: la raíz a la izquierda y centrada sobre sus hijos', () => {
    const pos = autoLayout(orgNodes.map(n => ({ ...n, group: undefined })), orgEdges, 'horizontal')
    const nodes = orgNodes
    const c = id => centerOf(pos, id, nodes)
    const kids = ['ops', 'fin', 'hr'].map(c)
    assert.ok(kids.every(k => k.x > c('ceo').x))
    assert.ok(Math.abs(c('ceo').y - (Math.min(...kids.map(k => k.y)) + Math.max(...kids.map(k => k.y))) / 2) < 1)
    assert.equal(Math.min(...Object.values(pos).map(p => p.x)), MARGIN)
    assert.equal(Math.min(...Object.values(pos).map(p => p.y)), MARGIN)
})

test('el orden por baricentro deshace un cruce evitable', () => {
    // a1 y a2 en la capa 0; sus hijos llegan en el orden contrario: b2 (de a1) antes que b1 (de a2).
    const nodes = [{ id: 'a1' }, { id: 'a2' }, { id: 'b2' }, { id: 'b1' }]
    const edges = [{ from: 'a1', to: 'b2' }, { from: 'a2', to: 'b1' }]
    const pos = autoLayout(nodes, edges)
    assert.ok((pos.a1.x < pos.a2.x) === (pos.b2.x < pos.b1.x), 'misma orientación arriba y abajo: no se cruzan')
})

// ── Zoom y encuadre ────────────────────────────────────────────────────────

test('fitScale: cabe el dibujo entero en la vista y nunca agranda', () => {
    assert.equal(fitScale({ w: 1000, h: 500 }, { w: 400, h: 400 }), 0.384)   // manda el ancho: (400-16)/1000
    assert.equal(fitScale({ w: 200, h: 1000 }, { w: 400, h: 400 }), 0.384)   // manda el alto
    assert.equal(fitScale({ w: 100, h: 100 }, { w: 400, h: 400 }), 1, 'escala máxima 1')
    assert.equal(fitScale({ w: 100000, h: 100000 }, { w: 400, h: 400 }), 0.1, 'mínimo')
})

test('con fit, al cargar se encuadra; los botones cambian la escala', () => {
    const vp = { clientWidth: 390, clientHeight: 400, scrollLeft: 5, scrollTop: 5 }
    const { c } = make({ ...base(), fit: true }, () => {})
    c.$refs.viewport = vp
    c.fit()
    assert.ok(c.scale < 1 && c.scale > 0.1)
    assert.equal(c.size.w * c.scale <= 390, true)
    const s = c.scale
    c.zoomIn()
    assert.ok(c.scale > s)
    c.zoomOut(); c.zoomOut()
    assert.ok(c.scale < s)
    c.zoomTo(100)
    assert.equal(c.scale, 2)
})

test('con zoom, el puntero y el arrastre se miden en píxeles del dibujo', () => {
    const { c, events } = make(base())
    c.scale = 0.5
    c.$refs.canvas.getBoundingClientRect = () => ({ left: 100, top: 50 })
    assert.deepEqual(c.canvasPoint(pe(300, 150)), { x: 400, y: 200 })
    // Arrastrar 30 px en pantalla a escala 0,5 mueve 60 px del dibujo (80 en la rejilla de 20).
    const n = c.vNodes[0]
    c.nodeDown(pe(100, 100), n); c.nodeMove(pe(130, 130), n); c.nodeUp(pe(130, 130), n)
    assert.deepEqual(plain(events.pop().detail), { id: 'a', x: 100, y: 100, flat: { k: 1 } })
    // Soltar el asa sobre la caja b: (450, 60) del dibujo = (100 + 450*0.5, 50 + 60*0.5) en pantalla.
    c.linkDown(pe(100 + 200 * 0.5, 50 + 68 * 0.5), c.vNodes[0])
    c.linkMove(pe(100 + 450 * 0.5, 50 + 60 * 0.5))
    assert.equal(c.linking.over, 'b')
})

// ── Encuadrar deja el dibujo centrado ──────────────────────────────────────

test('centerOffset: la mitad del hueco que sobra en cada eje, y 0 si el dibujo llena la vista', () => {
    assert.deepEqual(centerOffset({ w: 200, h: 1000 }, { w: 400, h: 400 }, 0.384), { x: 162, y: 8 })   // manda el alto: sobra a los lados
    assert.deepEqual(centerOffset({ w: 100, h: 100 }, { w: 400, h: 300 }, 1), { x: 150, y: 100 })      // pequeño: centrado en los dos ejes
    assert.deepEqual(centerOffset({ w: 1000, h: 500 }, { w: 400, h: 400 }, 1), { x: 0, y: 0 })         // más grande que la vista: hace scroll
})

test('al encuadrar, el dibujo queda centrado en el eje que sobra; antes de encuadrar no hay desplazamiento', () => {
    const { c } = make({ ...base(), fit: true }, () => {})
    assert.deepEqual(c.offset, { x: 0, y: 0 })

    // Una vista ancha y baja (la mitad del alto del dibujo): el alto manda la escala y sobra sitio a los lados.
    c.$refs.viewport = { clientWidth: 1200, clientHeight: Math.round(c.size.h / 2), scrollLeft: 7, scrollTop: 7 }
    c.fit()

    const drawnWidth = c.size.w * c.scale
    assert.ok(c.scale < 1)
    assert.equal(c.offset.x, Math.round((1200 - drawnWidth) / 2))
    assert.ok(c.offset.x > 0)
    // Centrado de verdad: el hueco de la izquierda y el de la derecha son iguales (±1 px de redondeo).
    assert.ok(Math.abs(c.offset.x - (1200 - drawnWidth - c.offset.x)) <= 1)
    assert.equal(c.$refs.viewport.scrollLeft, 0)
})

test('el zoom conserva el centro de la vista y recoloca el dibujo si cabe', () => {
    // El dibujo mide 600 x 136. En una vista de 500 x 800 se encuadra a 0,807 y sobra sitio arriba y abajo.
    const { c } = make({ ...base(), fit: true }, () => {})
    c.$refs.viewport = { clientWidth: 500, clientHeight: 800, scrollLeft: 0, scrollTop: 0 }
    c.fit()
    assert.deepEqual(c.size, { w: 600, h: 136 })
    assert.ok(c.offset.x > 0 && c.offset.y > 300)

    // Acercar a 2: a lo ancho ya no cabe (sin desplazamiento y con scroll), a lo alto sí (centrado).
    c.zoomTo(2)
    assert.deepEqual(c.offset, { x: 0, y: Math.round((800 - 136 * 2) / 2) })
    // El centro del dibujo (x = 300) sigue en el centro de la vista (250 px): 300 * 2 - 250 = 350.
    assert.ok(Math.abs(c.$refs.viewport.scrollLeft - 350) <= 1)

    // Alejar hasta que cabe por completo: centrado en los dos ejes.
    c.zoomTo(0.5)
    assert.deepEqual(c.offset, centerOffset(c.size, { w: 500, h: 800 }, 0.5))
    assert.ok(c.offset.x > 0)
})
