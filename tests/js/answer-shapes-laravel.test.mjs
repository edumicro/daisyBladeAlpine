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

test('tableRows: an object with a `data` list is a list, whatever else it carries (additional())', () => {
    const { rows, meta } = answerShapes.tableRows({ data: [{ id: 1 }], success: true }, '/rows')
    assert.equal(rows.length, 1)
    assert.deepEqual(meta, {})
})

test('resource: the one rule, a `data` that is an object is a resource: a model with a JSON column called "data" is unwrapped', () => {
    // The controller must wrap such a model itself, `['data' => $model]` (docs/contrato.md).
    assert.deepEqual(answerShapes.resource({ id: 1, name: 'Ana', data: { color: 'red' } }, '/r'), { color: 'red' })
    assert.deepEqual(answerShapes.resource({ data: { id: 1, name: 'Ana', data: { color: 'red' } } }, '/r'), { id: 1, name: 'Ana', data: { color: 'red' } })
})

test('resource: a resource is unwrapped with meta, links or additional() beside it', () => {
    assert.deepEqual(answerShapes.resource({ data: { id: 1 } }, '/r'), { id: 1 })
    assert.deepEqual(answerShapes.resource({ data: { id: 1 }, meta: {}, links: {} }, '/r'), { id: 1 })
    assert.deepEqual(answerShapes.resource({ data: { id: 1 }, success: true }, '/r'), { id: 1 })
})

test('resource: a `data` that is a list is not a resource', () => {
    assert.deepEqual(answerShapes.resource({ data: [1, 2], name: 'x' }, '/r'), { data: [1, 2], name: 'x' })
})

test('tableRows: the arrows of paginate(), simplePaginate() and cursorPaginate() lead to the neighbours', () => {
    const numbered = answerShapes.tableRows({ data: [{ id: 1 }], current_page: 2, last_page: 3 }, '/rows').pager
    const simple = answerShapes.tableRows({ data: [{ id: 1 }], current_page: 2, prev_page_url: '/r?page=1', next_page_url: null }, '/rows').pager
    const cursor = answerShapes.tableRows({ data: [{ id: 1 }], next_cursor: 'abc', prev_cursor: null }, '/rows').pager
    const resourceCursor = answerShapes.tableRows({ data: [{ id: 1 }], links: { prev: null, next: '/r?cursor=zz' }, meta: { next_cursor: 'zz', prev_cursor: null } }, '/rows').pager

    assert.deepEqual(numbered, { previous: { page: 1 }, next: { page: 3 } })
    assert.deepEqual(simple, { previous: { page: 1 }, next: null })
    assert.deepEqual(cursor, { previous: null, next: { cursor: 'abc' } })
    assert.deepEqual(resourceCursor, { previous: null, next: { cursor: 'zz' } })
})

test('tableRows: an answer without pagination has nowhere to go', () => {
    assert.deepEqual(answerShapes.tableRows({ data: [{ id: 1 }] }, '/rows').pager, { previous: null, next: null })
})
