import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectRemote } from '../../resources/js/daisyblade.js'
import { fakeSelect, rootBesides, shownOptions } from './helpers/fake-select.mjs'

// The <select> of the remote select sits OUTSIDE the element that carries the x-data (so that what the caller writes on it
// is evaluated in the caller's scope, not in ours). A `<template x-for>` inside it could not see `options`, so
// dblSelectRemote writes the <option>s itself, and listens on the window because the `change` of its <select> does not
// pass through its own element.

// A page whose axios gives the next answer of the list each time; an answer that is an Error makes the call fail.
function pageWithAxios(...answers) {
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = {
        axios: {
            get: async () => {
                const answer = answers.shift()
                if (answer instanceof Error) throw answer
                return { data: answer, status: 200 }
            },
        },
    }
}

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location })

function remoteSelect(config, field = fakeSelect()) {
    const events = []
    const select = Object.assign(dblSelectRemote(config), {
        $root: rootBesides(field), $nextTick: async () => {}, $watch() {}, $dispatch: (name, detail) => events.push({ name, detail }),
    })
    return { select, field, events }
}

// ── Writing the options ──

test('the options that arrive are written in the <select>, and the chosen one is marked', async () => {
    pageWithAxios([{ value: '1', label: 'One' }, { value: 2, label: 'Two' }])
    const { select, field } = remoteSelect({ loadUrl: '/options', value: '2' })

    await select.init()

    assert.deepEqual(shownOptions(field), [
        { value: '1', label: 'One', selected: false },
        { value: 2, label: 'Two', selected: true },   // 2 and '2' are the same choice
    ])
})

test('a new answer replaces the options written before, and a failed one leaves none of them', async () => {
    pageWithAxios([{ value: 'a', label: 'A' }], [{ value: 'b', label: 'B' }], new Error('down'))
    const { select, field } = remoteSelect({ loadUrl: '/options' })

    await select.init()
    assert.deepEqual(shownOptions(field).map(option => option.value), ['a'])

    await select.reload()
    assert.deepEqual(shownOptions(field).map(option => option.value), ['b'])

    await select.reload()
    assert.deepEqual(shownOptions(field), [])
})

test('the label of an option is written as text, never as HTML', async () => {
    pageWithAxios([{ value: 'x', label: '<img src=x onerror=alert(1)>' }])
    const { select, field } = remoteSelect({ loadUrl: '/options' })

    await select.init()

    // textContent: the browser shows the characters; it would not build an <img> from them.
    assert.equal(field.options[0].textContent, '<img src=x onerror=alert(1)>')
    assert.equal(field.options[0].innerHTML, undefined)
})

// ── The window listener: onChange() ──

test('onChange: the change of its own <select> is remembered and announced', () => {
    const { select, field, events } = remoteSelect({ loadUrl: '/options', name: 'city' })
    field.selectedOptions = [{ value: 'ES-TF' }]

    select.onChange({ target: field })

    assert.deepEqual(select.selected, ['ES-TF'])
    assert.deepEqual(events, [{ name: 'dbl-select-changed', detail: { name: 'city', value: 'ES-TF' } }])
})

test('onChange: the change of the control it depends on reloads the options', async () => {
    pageWithAxios([], [{ value: 'a', label: 'A' }])
    const form = { querySelectorAll: () => [] }
    const { select, field } = remoteSelect({ loadUrl: '/options', dependsOn: 'country' }, fakeSelect({ form }))
    let reloads = 0
    select.reload = () => { reloads++ }

    select.onChange({ target: { name: 'country', form } })
    select.onChange({ target: { name: 'other', form } })               // another control: nothing
    select.onChange({ target: { name: 'country', form: {} } })         // same name in another form: nothing

    assert.equal(reloads, 1)
})

test('onChange: a select that depends on nothing ignores a control without a name', () => {
    const { select } = remoteSelect({ loadUrl: '/options' })
    let reloads = 0
    select.reload = () => { reloads++ }

    select.onChange({ target: { name: '', form: null } })

    assert.equal(reloads, 0)
})
