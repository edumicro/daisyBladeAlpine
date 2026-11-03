import test from 'node:test'
import assert from 'node:assert/strict'
import { toTexts, dependencyParams, stillOffered } from '../../resources/js/daisyblade.js'

test('toTexts: nada, un valor o una lista pasan a lista de textos', () => {
    assert.deepEqual(toTexts(null), [])
    assert.deepEqual(toTexts(undefined), [])
    assert.deepEqual(toTexts('ES-TF'), ['ES-TF'])
    assert.deepEqual(toTexts(3), ['3'])
    assert.deepEqual(toTexts([1, 'b']), ['1', 'b'])
})

test('toTexts: el valor vacío no cuenta como elegido', () => {
    assert.deepEqual(toTexts(''), [])
    assert.deepEqual(toTexts(['', 'a']), ['a'])
})

test('dependencyParams: el valor va bajo el nombre del control', () => {
    assert.deepEqual(dependencyParams('country_code', 'ES'), { country_code: 'ES' })
})

test('dependencyParams: sin dependencia no hay parámetros', () => {
    assert.deepEqual(dependencyParams('', 'ES'), {})
    assert.deepEqual(dependencyParams(undefined, 'ES'), {})
})

test('stillOffered: se queda con lo elegido que las opciones nuevas aún ofrecen', () => {
    const options = [{ value: 'PT-30', label: 'Madeira' }, { value: 'PT-20', label: 'Azores' }]
    assert.deepEqual(stillOffered(['PT-30'], options), ['PT-30'])
    assert.deepEqual(stillOffered(['ES-TF'], options), [])
    assert.deepEqual(stillOffered(['ES-TF', 'PT-20'], options), ['PT-20'])
})

test('stillOffered: compara como texto, así que 1 y "1" son lo mismo', () => {
    assert.deepEqual(stillOffered(['1'], [{ value: 1, label: 'Uno' }]), ['1'])
})

test('stillOffered: sin opciones no queda nada', () => {
    assert.deepEqual(stillOffered(['a'], []), [])
})
