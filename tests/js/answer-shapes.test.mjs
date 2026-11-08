import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { answerShapes, isContractError } from '../../resources/js/http.js'

// One good and one bad answer for every component. A wrong form is a ContractError that names the endpoint in the console.

const realConsoleError = console.error
let warnings
beforeEach(() => { warnings = []; console.error = message => warnings.push(message) })
afterEach(() => { console.error = realConsoleError })

const isShapeError = error => isContractError(error) && error.reason === 'shape'

test('selectOptions: the vocabulary of a <select>: a map, rows { id, text } or { value, label }, bare or in `results`', () => {
    const options = [{ value: 1, label: 'One' }]
    assert.deepEqual(answerShapes.selectOptions(options, '/o'), options)
    assert.deepEqual(answerShapes.selectOptions({ results: options }, '/o'), options)
    assert.deepEqual(answerShapes.selectOptions([{ id: 1, text: 'One' }], '/o').map(({ value, label }) => [value, label]), [[1, 'One']])
    assert.deepEqual(answerShapes.selectOptions({ 12: 'Paracetamol', 7: 'Aspirin' }, '/o').map(({ value, label }) => [value, label]), [['7', 'Aspirin'], ['12', 'Paracetamol']])
    assert.throws(() => answerShapes.selectOptions({ results: [{ foo: 1 }] }, '/o'), isShapeError)
    assert.throws(() => answerShapes.selectOptions({ message: { x: 1 } }, '/o'), isShapeError)
    assert.match(warnings[0], /\/o/)
})

test('selectOptions: `data` is no longer a wrapper (the 3.x one is `results`)', () => {
    assert.throws(() => answerShapes.selectOptions({ data: [{ value: 1, label: 'One' }] }, '/o'), isShapeError)
})

test('tableRows: { data: [...], meta? }', () => {
    assert.deepEqual(answerShapes.tableRows({ data: [{ id: 1 }], meta: { total: 1 } }, '/r'), { rows: [{ id: 1 }], meta: { total: 1 }, pager: { previous: null, next: null } })
    assert.deepEqual(answerShapes.tableRows({ data: [] }, '/r'), { rows: [], meta: {}, pager: { previous: null, next: null } })
    assert.throws(() => answerShapes.tableRows([{ id: 1 }], '/r'), isShapeError)
    assert.throws(() => answerShapes.tableRows({ rows: [] }, '/r'), isShapeError)
})

test('resource: an object, bare or in `data`', () => {
    assert.deepEqual(answerShapes.resource({ name: 'Ana' }, '/u'), { name: 'Ana' })
    assert.deepEqual(answerShapes.resource({ data: { name: 'Ana' } }, '/u'), { name: 'Ana' })
    assert.throws(() => answerShapes.resource([1], '/u'), isShapeError)
})

test('calendarEvents: a list of events with title and a start text', () => {
    const events = [{ id: 1, title: 'A', start: '2026-05-10' }]
    assert.deepEqual(answerShapes.calendarEvents(events, '/c'), events)
    assert.deepEqual(answerShapes.calendarEvents({ data: events }, '/c'), events)
    assert.throws(() => answerShapes.calendarEvents([{ title: 'A' }], '/c'), isShapeError)
    assert.throws(() => answerShapes.calendarEvents({ events }, '/c'), isShapeError)
})

test('geoJsonLayer and geocoderResults', () => {
    const layer = { type: 'FeatureCollection', features: [] }
    assert.equal(answerShapes.geoJsonLayer(layer, '/g'), layer)
    assert.throws(() => answerShapes.geoJsonLayer({ data: [] }, '/g'), isShapeError)
    assert.deepEqual(answerShapes.geocoderResults({ data: [{ lat: 1, lng: 2, label: 'x' }] }, '/s'), [{ lat: 1, lng: 2, label: 'x' }])
    assert.throws(() => answerShapes.geocoderResults({ data: [{ label: 'x' }] }, '/s'), isShapeError)
})

test('uploadedFile needs an id (0 counts), uploadedImage needs a url', () => {
    assert.deepEqual(answerShapes.uploadedFile({ id: 0 }, '/u'), { id: 0 })
    assert.throws(() => answerShapes.uploadedFile({}, '/u'), isShapeError)
    assert.throws(() => answerShapes.uploadedFile({ id: null }, '/u'), isShapeError)
    assert.deepEqual(answerShapes.uploadedImage({ url: '/i.jpg' }, '/u'), { url: '/i.jpg' })
    assert.throws(() => answerShapes.uploadedImage({ url: '' }, '/u'), isShapeError)
})

test('formAnswer and importResult: a JSON object', () => {
    assert.deepEqual(answerShapes.formAnswer({ redirect: '/x' }, '/f'), { redirect: '/x' })
    assert.deepEqual(answerShapes.importResult({ imported: 3 }, '/i'), { imported: 3 })
    assert.throws(() => answerShapes.formAnswer([], '/f'), isShapeError)
    assert.throws(() => answerShapes.importResult([], '/i'), isShapeError)
})
