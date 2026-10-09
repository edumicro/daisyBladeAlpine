import test from 'node:test'
import assert from 'node:assert/strict'
import { validColor, pointColor, formatNumber, featureId, findFeature, splitFeatures } from '../../resources/js/components/map/features.js'

test('validColor acepta hex de 3, 4, 6 y 8 dígitos', () => {
    for (const c of ['#fff', '#FFFF', '#2563eb', '#2563EB80']) assert.equal(validColor(c), c)
    assert.equal(validColor('  #abc '), '#abc')
})

test('validColor rechaza lo que no es hex', () => {
    for (const c of ['red', '#ggg', '2563eb', '#12345', 'url(x)', '#fff;x', '', null, undefined, 12]) assert.equal(validColor(c), null)
})

test('pointColor cae al color de la capa', () => {
    assert.equal(pointColor({ color: '#f00' }, '#00f'), '#f00')
    assert.equal(pointColor({ color: 'nope' }, '#00f'), '#00f')
    assert.equal(pointColor(undefined, '#00f'), '#00f')
})

test('formatNumber: enteros y textos de 1 a 3 caracteres', () => {
    assert.equal(formatNumber(7), '7')
    assert.equal(formatNumber(0), '0')
    assert.equal(formatNumber(999), '999')
    assert.equal(formatNumber('12'), '12')
    assert.equal(formatNumber(' A3 '), 'A3')
})

test('formatNumber rechaza vacío, largo, decimal y otros tipos', () => {
    for (const v of [1000, 1.5, NaN, '', '   ', 'abcd', null, undefined, {}, true]) assert.equal(formatNumber(v), null)
})

test('featureId usa id o properties.id', () => {
    assert.equal(featureId({ id: 5 }), '5')
    assert.equal(featureId({ properties: { id: 'x1' } }), 'x1')
    assert.equal(featureId({ id: 0 }), '0')
    assert.equal(featureId({ properties: {} }), null)
    assert.equal(featureId(null), null)
})

test('findFeature compara como texto y devuelve null si no existe', () => {
    const fc = { features: [{ id: 1, n: 'a' }, { properties: { id: '2' }, n: 'b' }] }
    assert.equal(findFeature(fc, '1').n, 'a')
    assert.equal(findFeature(fc, 2).n, 'b')
    assert.equal(findFeature(fc, 3), null)
    assert.equal(findFeature(undefined, 1), null)
    assert.equal(findFeature(fc, undefined), null)
})

test('splitFeatures separa los numerados y limpia el color inválido', () => {
    const pt = (properties) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [1, 2] }, properties })
    const { plain, numbered } = splitFeatures([
        pt({ number: 3, color: '#f00' }), pt({ color: 'bad', number: 'toolong' }), pt({ color: '#0f0' }),
        { type: 'Feature', geometry: { type: 'LineString', coordinates: [] }, properties: {} },
    ])
    assert.equal(numbered.length, 1)
    assert.deepEqual([numbered[0].label, numbered[0].color], ['3', '#f00'])
    assert.equal(plain.length, 2)
    assert.equal('color' in plain[0].properties, false)
    assert.equal('number' in plain[0].properties, false)
    assert.equal(plain[1].properties.color, '#0f0')
})
