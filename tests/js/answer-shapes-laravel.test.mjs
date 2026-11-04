import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { answerShapes, isContractError } from '../../resources/js/http.js'

// The forms of Laravel and no others. (tests/Feature/LaravelAnswerShapesTest.php does the same with the real classes of
// Laravel; these are the same shapes written by hand, for the cases that are quick to see.)

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError })

test('tableRows: a bare paginate() ({data, current_page, last_page, total}) keeps its pagination', () => {
    const paginator = { data: [{ id: 1 }], current_page: 1, last_page: 3, per_page: 1, total: 3, links: [] }
    const { rows, meta } = answerShapes.tableRows(paginator, '/rows')
    assert.deepEqual(rows, [{ id: 1 }])
    assert.equal(meta.last_page, 3)
    assert.equal(meta.total, 3)
})

test('tableRows: a bare simplePaginate() is a table too', () => {
    const { rows, meta } = answerShapes.tableRows({ data: [{ id: 1 }], current_page: 2, per_page: 1, next_page_url: null }, '/rows')
    assert.equal(rows.length, 1)
    assert.equal(meta.current_page, 2)
})

test('tableRows: an object that has a `data` list but is neither a resource nor a paginator is refused', () => {
    assert.throws(() => answerShapes.tableRows({ data: [{ id: 1 }], total: 3 }, '/rows'), error => isContractError(error) && error.reason === 'shape')
})

test('resource: a bare model with a JSON column called "data" is not unwrapped into that column', () => {
    const resource = answerShapes.resource({ id: 1, name: 'Ana', data: { color: 'red' } }, '/r')
    assert.equal(resource.name, 'Ana')
    assert.deepEqual(resource.data, { color: 'red' })
})

test('resource: a model with only a `data` column is unwrapped only if it is exactly a resource ({data, meta, links})', () => {
    assert.deepEqual(answerShapes.resource({ data: { id: 1 } }, '/r'), { id: 1 })
    assert.deepEqual(answerShapes.resource({ data: { id: 1 }, meta: {}, links: {} }, '/r'), { id: 1 })
    assert.deepEqual(answerShapes.resource({ data: { id: 1 }, name: 'x' }, '/r'), { data: { id: 1 }, name: 'x' })
})
