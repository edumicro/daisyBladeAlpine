import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dbSelectRemote } from '../../resources/js/daisyblade.js'

// Edge cases of the remote select (`<x-dbl::form.select options-url="...">`) that the pure helpers
// tested in select-remote.test.mjs do not reach.

// ── A page with an axios that always gives the same answer ──────────────────

function pageWithAxios(answer) {
    const calls = []
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { get: async (url, config) => { calls.push({ url, ...config }); return { data: answer, status: 200 } } } }
    return calls
}

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location })

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

// ── A view published with 2.3 next to the 2.4 JavaScript ──
//
// In 2.3 the x-data was on the <select> itself and the view had `x-init="init()"`. The 2.4 factory looks for the
// <select> INSIDE the Alpine scope: for the old view that found nothing and fetch() threw
// "Cannot read properties of null (reading 'querySelectorAll')". Alpine also calls init() by itself, so the old
// `x-init="init()"` made it run twice and asked the server twice.

/** The <select> that carries the x-data itself, as in a view published with 2.3. */
function selectCarryingTheScope(config) {
    const selectElement = fakeField({ matches: selector => selector === 'select', querySelector: () => null })
    return Object.assign(dbSelectRemote(config), { $root: selectElement, $nextTick: async () => {} })
}

test('a <select> that carries the x-data itself (view published with 2.3) loads without throwing', async () => {
    pageWithAxios([{ value: 'a', label: 'A' }])
    const select = selectCarryingTheScope({ url: '/options', name: 'city' })

    await assert.doesNotReject(() => select.init())

    assert.deepEqual(select.options, [{ value: 'a', label: 'A' }])
})

test('the double init() of a view published with 2.3 asks the server once', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const select = selectCarryingTheScope({ url: '/options', name: 'city' })

    // Alpine calls init() by itself, and the old view's x-init="init()" calls it again right away.
    await Promise.all([select.init(), select.init()])

    assert.equal(calls.length, 1)
})

test('init() called again once the first load has finished loads again', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const select = selectWith({ url: '/options' })

    await select.init()
    await select.init()

    assert.equal(calls.length, 2)
})
