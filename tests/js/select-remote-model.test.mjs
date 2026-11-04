import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dbSelectRemote } from '../../resources/js/daisyblade.js'

// 2.4.1. A user's `x-model` on the remote <select>: Alpine applies a model to a <select> when the model changes,
// not when <option>s appear. The first application found no options and nobody applied it again, so the select
// stayed empty while the model held '2' (and with `value="2"` and an empty model, the opposite).
// Alpine leaves `el._x_model` ({ get, set }) on the element: the component reads it when the options arrive.

function pageWithAxios(answer) {
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { get: async () => ({ data: answer, status: 200 }) } }
}

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location })

/** The <select> with the bits of the DOM the component touches; `model` is what Alpine's x-model holds (undefined = no x-model). */
function fieldWithModel(model) {
    const field = {
        form: null, selectedOptions: [], value: '', disabled: false,
        setAttribute() {}, querySelectorAll: () => [], dispatchEvent() {},
    }
    if (model) field._x_model = { get: () => model.value, set: value => { model.value = value } }
    return field
}

const selectWith = (config, field) => Object.assign(dbSelectRemote(config), { $root: { querySelector: () => field }, $nextTick: async () => {} })

const OPTIONS = [{ value: '1', label: 'One' }, { value: '2', label: 'Two' }]

test('with x-model, what the model holds is what is chosen when the options arrive', async () => {
    pageWithAxios(OPTIONS)
    const select = selectWith({ url: '/options' }, fieldWithModel({ value: '2' }))

    await select.init()

    assert.deepEqual(select.selected, ['2'])
})

test('with x-model and a value prop, an empty model wins, as on any Alpine input', async () => {
    pageWithAxios(OPTIONS)
    const select = selectWith({ url: '/options', value: '2' }, fieldWithModel({ value: '' }))

    await select.init()

    assert.deepEqual(select.selected, [])
})

test('with a list in the model (multiple), all of them are chosen', async () => {
    pageWithAxios(OPTIONS)
    const select = selectWith({ url: '/options' }, fieldWithModel({ value: ['1', '2'] }))

    await select.init()

    assert.deepEqual(select.selected, ['1', '2'])
})

test('without x-model, the value prop stays as it was', async () => {
    pageWithAxios(OPTIONS)
    const select = selectWith({ url: '/options', value: '2' }, fieldWithModel(null))

    await select.init()

    assert.deepEqual(select.selected, ['2'])
})
