import test from 'node:test'
import assert from 'node:assert/strict'
import { dblFlowchart, normalize } from '../../resources/js/components/flowchart.js'

// The canvas has no negative coordinates (clampPos): dragging and the keyboard already respect that. A position that
// the PARENT sends must too. Before, `{ x: -20, y: -5 }` was kept as it came, and the box was painted cut off by the
// border of the canvas with no scroll to reach it.

test('a node the parent places at a negative position is moved onto the canvas', () => {
    const chart = Object.assign(dblFlowchart({ nodes: [{ id: 1, label: 'A', x: -20, y: -5 }], edges: [] }), { $nextTick() {}, $refs: {} })

    chart.init()

    assert.ok(chart.pos['1'].x >= 0 && chart.pos['1'].y >= 0, `got ${JSON.stringify(chart.pos['1'])}`)
})

test('normalize keeps a missing position as null, but never a negative one', () => {
    const { nodes } = normalize([{ id: 'a' }, { id: 'b', x: -1, y: 10 }])

    assert.equal(nodes[0].x, null)
    assert.equal(nodes[1].x, 0)
    assert.equal(nodes[1].y, 10)
})

test('normalize keeps a valid position as it is, including 0, a number given as text and a decimal', () => {
    const { nodes } = normalize([{ id: 'a', x: 0, y: 0 }, { id: 'b', x: '40', y: 12.5 }])

    assert.deepEqual([nodes[0].x, nodes[0].y], [0, 0])
    assert.deepEqual([nodes[1].x, nodes[1].y], [40, 12.5])
})

test('normalize treats a position that is not a number as missing, not as 0', () => {
    const { nodes } = normalize([{ id: 'a', x: 'left', y: null }, { id: 'b', x: '', y: undefined }])

    assert.deepEqual([nodes[0].x, nodes[0].y, nodes[1].x, nodes[1].y], [null, null, null, null])
})

test('a negative coordinate sent later by set() is also moved onto the canvas', () => {
    const chart = Object.assign(dblFlowchart({ nodes: [], edges: [] }), { $nextTick() {}, $refs: {} })
    chart.init()

    chart.set([{ id: 'z', x: -300, y: 40 }], [])

    assert.deepEqual(chart.pos['z'], { x: 0, y: 40 })
})
