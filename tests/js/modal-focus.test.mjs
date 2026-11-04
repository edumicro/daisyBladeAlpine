import test from 'node:test'
import assert from 'node:assert/strict'
import { dblModal } from '../../resources/js/daisyblade.js'

test('opening the modal moves the focus into its panel', () => {
    let focused = false
    const modal = Object.assign(dblModal(), { $refs: { panel: { focus: () => { focused = true } } }, $nextTick: callback => callback() })

    modal.show()

    assert.equal(modal.open, true)
    assert.equal(focused, true)
})
