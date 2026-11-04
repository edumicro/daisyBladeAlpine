import test from 'node:test'
import assert from 'node:assert/strict'
import { modelBinding, modelOfElement, isEmptyValue } from '../../resources/js/modelBinding.js'
import { dblSelectSearch } from '../../resources/js/daisyblade.js'

// The rule of modelBinding.js, written once and tested once. Every control with a model uses it (the remote select, the searchable
// select and the character counter), so this file is the proof that they agree.

/** A model held in a variable, with the `{ get, set }` that Alpine leaves in `el._x_model`. */
function variableModel(startsAs) {
    const model = { value: startsAs, get: () => model.value, set: value => { model.value = value } }
    return model
}

test('isEmptyValue: null, undefined, "" and an empty list are empty; 0 and false are values', () => {
    for (const empty of [null, undefined, '', []]) assert.equal(isEmptyValue(empty), true)
    for (const full of [0, false, '0', 'a', ['a']]) assert.equal(isEmptyValue(full), false)
})

test('rule 1: a model that holds something wins over the value of the view', () => {
    const model = variableModel('1')
    assert.equal(modelBinding(model).reconcile('2'), '1')
    assert.equal(model.value, '1')
})

for (const empty of [null, undefined, '', []]) {
    test(`rule 1: an empty model (${JSON.stringify(empty) ?? 'undefined'}) shows the value of the view and RECEIVES it`, () => {
        const model = variableModel(empty)
        assert.equal(modelBinding(model).reconcile('2'), '2')
        assert.equal(model.value, '2')
    })
}

test('rule 1: an empty model and no value in the view stay empty, and nothing is written', () => {
    const model = variableModel('')
    assert.equal(modelBinding(model).reconcile(''), '')
    assert.equal(model.value, '')
})

test('rule 1: a list works the same way (a multiple select)', () => {
    const model = variableModel([])
    assert.deepEqual(modelBinding(model).reconcile(['1', '2']), ['1', '2'])
    assert.deepEqual(model.value, ['1', '2'])
})

test('without a model the value of the view is what the control holds', () => {
    assert.equal(modelBinding(null).reconcile('2'), '2')
    assert.equal(modelBinding(null).current('typed'), 'typed')
})

test('rule 3: current() reads the model every time, so a change from outside is always seen', () => {
    const model = variableModel('a')
    const binding = modelBinding(model)
    assert.equal(binding.current('x'), 'a')
    model.value = 'b'
    assert.equal(binding.current('x'), 'b')
})

test('rule 4: write() puts what the user chose in the model', () => {
    const model = variableModel('')
    modelBinding(model).write('7')
    assert.equal(model.value, '7')
    assert.doesNotThrow(() => modelBinding(null).write('7'))
})

test('modelOfElement: the `_x_model` that Alpine leaves, or null', () => {
    const access = variableModel('x')
    assert.equal(modelOfElement({ _x_model: access }), access)
    assert.equal(modelOfElement({}), null)
    assert.equal(modelOfElement(undefined), null)
})

test('the searchable select applies the same rule: its `chosen` is the model', () => {
    const search = Object.assign(dblSelectSearch({ chosen: '2', options: { 1: 'One', 2: 'Two' } }), { $refs: {}, $root: null })
    search.chosen = ''          // x-modelable copies an empty model a moment after the start
    search.sync()
    assert.equal(search.chosen, '2')
})
