import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectRemote } from '../../resources/js/daisyblade.js'
import { fakeSelect, rootBesides, shownOptions } from './helpers/fake-select.mjs'

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

// ── A remote select as the Blade mounts it: the <select> sits beside the element that carries the Alpine scope ──

const selectWith = (config, field = fakeSelect()) =>
    Object.assign(dblSelectRemote(config), { $root: rootBesides(field), $nextTick: async () => {}, $watch() {} })

// ── syncField: what the x-effect of the container does to the <select> ──

test('syncField disables the select while it waits for the control it depends on, and enables it afterwards', () => {
    const field = fakeSelect()
    const select = selectWith({ loadUrl: '/options' }, field)

    select.waiting = true
    select.syncField()
    assert.equal(field.disabled, true)

    select.waiting = false
    select.syncField()
    assert.equal(field.disabled, false)
})

test('syncField disables the select for the `disabled` prop', () => {
    const field = fakeSelect()

    selectWith({ loadUrl: '/options', disabled: true }, field).syncField()

    assert.equal(field.disabled, true)
})

test('syncField does not enable a select that the user disabled with their own :disabled', () => {
    const field = fakeSelect({ disabled: true })   // the user's `:disabled="locked"` locked it
    const select = selectWith({ loadUrl: '/options' }, field)

    select.syncField()   // for example, the options finished loading and `loading` changed

    assert.equal(field.disabled, true)
})

test('syncField mirrors the loading state in aria-busy', () => {
    const field = fakeSelect()
    const select = selectWith({ loadUrl: '/options' }, field)

    select.loading = true
    select.syncField()
    assert.equal(field.attributes['aria-busy'], 'true')

    select.loading = false
    select.syncField()
    assert.equal(field.attributes['aria-busy'], 'false')
})

// ── Alpine calls init() by itself; a second call while the first one loads must not ask the server again ──

test('a second init() while the first one is still loading asks the server once', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const select = selectWith({ loadUrl: '/options', name: 'city' })

    await Promise.all([select.init(), select.init()])

    assert.equal(calls.length, 1)
})

test('init() called again once the first load has finished loads again', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const select = selectWith({ loadUrl: '/options' })

    await select.init()
    await select.init()

    assert.equal(calls.length, 2)
})

// ── depends-on a radio group or a checkbox ──
//
// dependencyValue() used to read `.value` of the FIRST control with that name, checked or not: with `form.radio`
// that was always the first radio, and with `form.checkbox` (a hidden `0` and then the checkbox `1`) the hidden 0.
// It must read what the form would send.

/** A form with the given controls, in document order: `{ name, type, value, checked }`. */
function formWith(controls) {
    return { querySelectorAll: selector => controls.filter(control => selector === `[name="${control.name}"]`) }
}

/** A remote select that depends on `kind`, inside `form`. */
function dependentSelect(form, dependsOn = 'kind') {
    return selectWith({ loadUrl: '/options', dependsOn }, fakeSelect({ form }))
}

test('depends-on a radio group sends the CHECKED radio, not the first one', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const form = formWith([
        { name: 'kind', type: 'radio', value: 'person', checked: false },
        { name: 'kind', type: 'radio', value: 'company', checked: true },
    ])

    await dependentSelect(form).init()

    assert.equal(calls.at(-1).params.kind, 'company')
})

test('depends-on a radio group with none checked waits: nothing is asked and the select is empty', async () => {
    const calls = pageWithAxios([{ value: 'a', label: 'A' }])
    const form = formWith([
        { name: 'kind', type: 'radio', value: 'person', checked: false },
        { name: 'kind', type: 'radio', value: 'company', checked: false },
    ])
    const select = dependentSelect(form)

    await select.init()

    assert.equal(select.dependencyValue(), '')
    assert.equal(select.waiting, true)
    assert.deepEqual(select.options, [])
    assert.equal(calls.length, 0)
})

test('depends-on a form.checkbox (hidden 0 + checkbox 1) sends 1 when it is checked and 0 when it is not', () => {
    const controls = [
        { name: 'vat', type: 'hidden', value: '0' },
        { name: 'vat', type: 'checkbox', value: '1', checked: true },
    ]
    const select = dependentSelect(formWith(controls), 'vat')

    assert.equal(select.dependencyValue(), '1')

    controls[1].checked = false
    assert.equal(select.dependencyValue(), '0')
})

test('depends-on a checkbox without hidden 0 that is unchecked sends nothing, so the select waits', () => {
    const select = dependentSelect(formWith([{ name: 'vat', type: 'checkbox', value: '1', checked: false }]), 'vat')

    assert.equal(select.dependencyValue(), '')
})

test('depends-on a plain control (text, select) still reads its value', () => {
    const select = dependentSelect(formWith([{ name: 'country', type: 'select-one', value: 'ES' }]), 'country')

    assert.equal(select.dependencyValue(), 'ES')
})

test('depends-on a control that is not in the form is an empty value', () => {
    const select = dependentSelect(formWith([]), 'country')

    assert.equal(select.dependencyValue(), '')
})

test('a select that depends on nothing has no dependency value', () => {
    assert.equal(selectWith({ loadUrl: '/options' }).dependencyValue(), null)
})
