import test from 'node:test'
import assert from 'node:assert/strict'
import { dbSelectRemote } from '../../resources/js/daisyblade.js'

// Edge cases of the remote select (`<x-dbl::form.select options-url="...">`) that the pure helpers
// tested in select-remote.test.mjs do not reach.

// ── A remote select as the Blade mounts it: the Alpine scope is the container, the <select> is inside ──

/** The <select> element, with the bits of the DOM that dbSelectRemote reads and writes. */
function fakeField(overrides = {}) {
    const attributes = {}
    return {
        form: null, selectedOptions: [], value: '', disabled: false,
        attributes,
        setAttribute(name, value) { attributes[name] = value },
        querySelectorAll: () => [], dispatchEvent() {},
        ...overrides,
    }
}

function selectWith(config, field = fakeField()) {
    return Object.assign(dbSelectRemote(config), { $root: { querySelector: () => field }, $nextTick: async () => {} })
}

// ── syncField: what the x-effect of the container does to the <select> ──

test('syncField disables the select while it waits for the control it depends on, and enables it afterwards', () => {
    const field = fakeField()
    const select = selectWith({ url: '/options' }, field)

    select.waiting = true
    select.syncField()
    assert.equal(field.disabled, true)

    select.waiting = false
    select.syncField()
    assert.equal(field.disabled, false)
})

test('syncField disables the select for the `disabled` prop', () => {
    const field = fakeField()

    selectWith({ url: '/options', disabled: true }, field).syncField()

    assert.equal(field.disabled, true)
})

test('syncField does not enable a select that the user disabled with their own :disabled', () => {
    const field = fakeField({ disabled: true })   // the user's `:disabled="locked"` locked it
    const select = selectWith({ url: '/options' }, field)

    select.syncField()   // for example, the options finished loading and `loading` changed

    assert.equal(field.disabled, true)
})

test('syncField mirrors the loading state in aria-busy', () => {
    const field = fakeField()
    const select = selectWith({ url: '/options' }, field)

    select.loading = true
    select.syncField()
    assert.equal(field.attributes['aria-busy'], 'true')

    select.loading = false
    select.syncField()
    assert.equal(field.attributes['aria-busy'], 'false')
})
