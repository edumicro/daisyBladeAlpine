import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectRemote } from '../../resources/js/daisyblade.js'
import { fakeSelect, rootBesides, shownOptions } from './helpers/fake-select.mjs'

// The guide sends `x-model` to the <select> of the remote mode. Alpine applies a model to a <select> when the model
// changes, not when <option>s appear: the first application finds no options, and when they arrive nothing re-applies
// it. Chrome, 3.0 before the fix: `<div x-data="{ form: { city: '2' } }"> <x-dbl::form.select load-url=… x-model="form.city">`
// ended with the select showing '' while form.city was '2' (an empty model, null or '', does not wipe the `value` of the view).
// Alpine leaves `el._x_model` ({ get, set }) on the element: the factory takes the model as what is chosen.

function pageWithAxios(answer) {
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { get: async () => ({ data: answer, status: 200 }) } }
}

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location })

const OPTIONS = [{ value: '1', label: 'One' }, { value: '2', label: 'Two' }, { value: '3', label: 'Three' }]

/** A <select> with x-model: `model.value` is what the bound variable holds, as Alpine's `_x_model` reads it. */
function selectWithModel(config, model) {
    const field = fakeSelect({ _x_model: { get: () => model.value, set: value => { model.value = value } } })
    const select = Object.assign(dblSelectRemote(config), { $root: rootBesides(field), $nextTick: async () => {}, $dispatch() {} })
    return { select, field }
}

const chosen = field => shownOptions(field).filter(option => option.selected).map(option => option.value)

test('a <select> with x-model: what the model holds is what ends up selected when the options arrive', async () => {
    pageWithAxios(OPTIONS)
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city' }, { value: '2' })

    await select.init()

    assert.deepEqual(select.selected, ['2'])
    assert.deepEqual(chosen(field), ['2'])
})

// An edit form: the view writes the saved `value="2"` and the x-model starts empty. The empty model must not wipe it.
for (const empty of [null, '']) {
    test(`a <select> with a value prop and an x-model that starts as ${JSON.stringify(empty)}: the value prop stays chosen`, async () => {
        pageWithAxios(OPTIONS)
        const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city', value: '2' }, { value: empty })

        await select.init()

        assert.deepEqual(select.selected, ['2'])
        assert.deepEqual(chosen(field), ['2'])
    })
}

test('a <select> with a value prop and an x-model that holds another value: the model wins', async () => {
    pageWithAxios(OPTIONS)
    const { select } = selectWithModel({ loadUrl: '/options', name: 'city', value: '2' }, { value: '1' })

    await select.init()

    assert.deepEqual(select.selected, ['1'])
})

test('a <select> with x-model whose model has a number: 2 and "2" are the same choice', async () => {
    pageWithAxios(OPTIONS)
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city' }, { value: 2 })

    await select.init()

    assert.deepEqual(chosen(field), ['2'])
})

test('a multiple <select> with x-model: every value of the model list is selected', async () => {
    pageWithAxios(OPTIONS)
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'cities', multiple: true }, { value: ['1', '3'] })

    await select.init()

    assert.deepEqual(chosen(field), ['1', '3'])
})

test('a <select> with x-model is read again at every load: a model changed from outside is not lost when the options change', async () => {
    pageWithAxios(OPTIONS)
    const model = { value: '1' }
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city' }, model)
    await select.init()

    model.value = '3'            // the parent changed the variable; Alpine moved the <select> there, but `selected` did not hear it
    await select.reload()

    assert.deepEqual(chosen(field), ['3'])
})

test('a <select> without x-model keeps working from the value prop', async () => {
    pageWithAxios(OPTIONS)
    const field = fakeSelect()   // no `_x_model`: nobody bound a model to it
    const select = Object.assign(dblSelectRemote({ loadUrl: '/options', name: 'city', value: '2' }), { $root: rootBesides(field), $nextTick: async () => {} })

    await select.init()

    assert.deepEqual(chosen(field), ['2'])
})

test('a model that is undefined does not clear the value prop', async () => {
    pageWithAxios(OPTIONS)
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city', value: '2' }, { value: undefined })

    await select.init()

    assert.deepEqual(chosen(field), ['2'])
})

// An edit form of a `multiple` select: the model starts as an empty LIST and the view wrote value=['1','2'].
test('a multiple <select> with an EMPTY LIST in the model keeps the value prop', async () => {
    pageWithAxios(OPTIONS)
    const { select, field } = selectWithModel({ loadUrl: '/options', name: 'city', multiple: true, value: ['1', '2'] }, { value: [] })

    await select.init()

    assert.deepEqual(select.selected, ['1', '2'])
    assert.deepEqual(chosen(field), ['1', '2'])
})

// The select shows "Two" (from the value prop) but the model stayed '': a form that sends the model sent an empty value.
test('an empty model receives the value prop that the select shows', async () => {
    pageWithAxios(OPTIONS)
    const model = { value: '' }
    const { select } = selectWithModel({ loadUrl: '/options', name: 'city', value: '2' }, model)

    await select.init()

    assert.equal(model.value, '2')
})

test('an empty model of a multiple select receives the list that the select shows', async () => {
    pageWithAxios(OPTIONS)
    const model = { value: [] }
    const { select } = selectWithModel({ loadUrl: '/options', name: 'city', multiple: true, value: ['1', '2'] }, model)

    await select.init()

    assert.deepEqual(model.value, ['1', '2'])
})
