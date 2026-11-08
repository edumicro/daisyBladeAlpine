import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectSearch, searchParams } from '../../resources/js/daisyblade.js'
import { answerShapes, isContractError } from '../../resources/js/http.js'

// form.select with `search-url`: the server decides what to show while the user types. Pure parts (the query, the forms of the
// answer) and the behaviour that must never break: old answers dropped, a cancellation is no error, a failure keeps what was chosen.

// ── The query ──

test('searchParams: the fixed params, the control we depend on, the typed text and the limit', () => {
    const config = { params: { country_code: 'ES' }, dependsOn: 'family', queryParam: 'term', limit: 20 }
    assert.deepEqual(searchParams(config, 'ibu', 'ANALG'), { country_code: 'ES', family: 'ANALG', term: 'ibu', limit: 20 })
})

test('searchParams: no dependency, no param for it; the search itself cannot be hidden by `params`', () => {
    const config = { params: { q: 'mine', limit: 1 }, dependsOn: '', queryParam: 'q', limit: 5 }
    assert.deepEqual(searchParams(config, 'ibu', null), { q: 'ibu', limit: 5 })
})

// ── The forms of the answer ──

const rows = [{ id: 1, text: 'Ibuprofen', lab: 'Acme' }, { id: 2, text: 'Ibuprofen 600', lab: 'Zeta' }]

test('searchResults reads `{ results }` and a bare list, and keeps the whole row', () => {
    const wanted = [{ id: 1, text: 'Ibuprofen', row: rows[0] }, { id: 2, text: 'Ibuprofen 600', row: rows[1] }]
    assert.deepEqual(answerShapes.searchResults({ results: rows }, '/s'), wanted)              // select2
    assert.deepEqual(answerShapes.searchResults(rows, '/s'), wanted)                           // a bare list
    assert.deepEqual(answerShapes.searchResults([{ value: 'a', label: 'A' }], '/s'), [{ id: 'a', text: 'A', row: { value: 'a', label: 'A' } }])
    assert.deepEqual(answerShapes.searchResults({ results: [] }, '/s'), [])
    assert.deepEqual(answerShapes.searchResults([], '/s'), [])
})

test('searchResults does not guess: a map, a Laravel `data` and a status body are not results', () => {
    const realError = console.error
    console.error = () => {}
    try {
        for (const notResults of [{ ok: true, mensaje: 'Guardado' }, { a: 'A', 2: 'Two' }, { data: rows }, { items: rows }]) {
            assert.throws(() => answerShapes.searchResults(notResults, '/s'), isContractError, JSON.stringify(notResults))
        }
    } finally { console.error = realError }
})

test('the status body is shown as an error with Retry, never as rows; process-results is the way to read it', async () => {
    page({ ok: true, mensaje: 'Guardado' })
    const { select } = searching()
    await select.runSearch('ibu')
    assert.equal(select.state, 'error')
    assert.deepEqual(select.flatList, [])
    assert.equal(select.hasRetry, true)

    page({ ok: true, mensaje: 'Guardado', lista: [{ id: 1, text: 'Uno' }] })
    const reading = searching({ processResults: 'response.lista' })
    await reading.select.runSearch('u')
    assert.deepEqual(reading.select.flatList.map(item => item.label), ['Uno'])
})

test('searchResults: `option-label` names the field of the label', () => {
    const answer = [{ id: 7, nombre: 'Paracetamol' }]
    assert.deepEqual(answerShapes.searchResults(answer, '/s', 'nombre'), [{ id: 7, text: 'Paracetamol', row: answer[0] }])
})

test('searchResults: a wrong row or body is a contract error that names the endpoint', () => {
    const realError = console.error
    console.error = () => {}
    try {
        for (const bad of [{ results: 'x' }, [{ id: 1 }], [1, 2], { a: { b: 1 } }, null]) {
            assert.throws(() => answerShapes.searchResults(bad, '/s'), isContractError, JSON.stringify(bad))
        }
    } finally { console.error = realError }
})

// ── The component ──

const realConsoleError = console.error
let errors
beforeEach(() => { errors = []; console.error = (...args) => errors.push(args) })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location; delete globalThis.told; delete globalThis.picked })

/** A page whose axios answers with what `answers` says for each call (an Error fails, a function is called with the request). */
function page(...answers) {
    const calls = []
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = {
        // Alpine.evaluate, for the expressions of the caller: the names of `scope` are in scope.
        Alpine: { evaluate: (el, expression, { scope }) => new Function(...Object.keys(scope), `return (${expression})`)(...Object.values(scope)) },
        axios: {
            get: async (url, options) => {
                calls.push({ url, ...options })
                const answer = answers.shift()
                const reply = typeof answer === 'function' ? await answer(options) : answer
                if (reply instanceof Error) throw reply
                return { data: reply, status: 200, headers: { 'content-type': 'application/json' } }
            },
        },
    }
    return calls
}

function searching(config = {}) {
    const box = { value: '', form: null, addEventListener() {}, dispatchEvent() {}, setAttribute() {}, removeAttribute() {} }
    const events = []
    const select = Object.assign(dblSelectSearch({ name: 'medicine', searchUrl: '/medicines', debounce: 0, ...config }), {
        $root: { parentElement: { querySelector: () => box, contains: () => true } },
        $refs: { value: { value: config.chosen ?? '', dispatchEvent() {} } },
        $dispatch: (name, detail) => events.push([name, detail]),
        $nextTick: async () => {}, $watch() {},
    })
    return { select, events, box }
}

const httpFailure = status => Object.assign(new Error('failed'), { response: { status, data: { message: 'boom' } } })

test('asks the server with the typed text, the limit and the params', async () => {
    const calls = page({ results: rows })
    const { select } = searching({ params: { country_code: 'ES' }, limit: 15 })

    await select.runSearch('ibu')

    assert.equal(calls[0].url, '/medicines')
    assert.deepEqual(calls[0].params, { country_code: 'ES', q: 'ibu', limit: 15 })
    assert.deepEqual(select.flatList.map(item => item.label), ['Ibuprofen', 'Ibuprofen 600'])
    assert.equal(select.state, 'ready')
})

test('two searches that cross: only the last one counts', async () => {
    let releaseFirst
    page(() => new Promise(resolve => { releaseFirst = () => resolve({ results: [{ id: 1, text: 'OLD' }] }) }), { results: [{ id: 2, text: 'NEW' }] })
    const { select } = searching()

    const first = select.runSearch('ib')
    await select.runSearch('ibu')
    releaseFirst()
    await first

    assert.deepEqual(select.flatList.map(item => item.label), ['NEW'])
    assert.equal(select.state, 'ready')
})

test('the request in flight is aborted when a newer one starts, and that is NOT an error', async () => {
    const seen = []
    const canceled = Object.assign(new Error('canceled'), { code: 'ERR_CANCELED' })
    page(options => new Promise((resolve, reject) => {
        seen.push(options.signal)
        options.signal.addEventListener('abort', () => reject(canceled))
    }), { results: rows })
    const { select } = searching()

    const first = select.runSearch('ib')
    await select.runSearch('ibu')
    await first

    assert.equal(seen[0].aborted, true)
    assert.equal(errors.length, 0, 'nothing in the console')
    assert.equal(select.failure, false)
    assert.equal(select.state, 'ready')
})

test('a cancellation that reaches fail() is quiet: no message, no console, not loading', () => {
    page()
    const { select } = searching()
    select.state = 'loading'

    select.fail(Object.assign(new Error('canceled'), { code: 'ERR_CANCELED' }))

    assert.equal(select.state, 'idle')
    assert.equal(select.statusText.includes('Could not'), false)
    assert.equal(errors.length, 0)
})

test('typing below `min-chars` asks nothing and says so in words', () => {
    const calls = page()
    const { select } = searching({ minChars: 3, messages: { minChars: 'Type at least 3 characters' } })

    select.typed('ib')

    assert.equal(calls.length, 0)
    assert.equal(select.statusText, 'Type at least 3 characters')
    assert.equal(select.state, 'idle')
})

test('the debounce: one request for a burst of keys', async () => {
    const calls = page({ results: rows })
    const { select } = searching({ debounce: 20 })

    select.typed('ib'); select.typed('ibu'); select.typed('ibup')
    await new Promise(resolve => setTimeout(resolve, 80))

    assert.equal(calls.length, 1)
    assert.equal(calls[0].params.q, 'ibup')
})

// ── process-results and option-label ──

test('process-results turns an answer of any shape into rows, and receives the whole body', async () => {
    page({ meta: { total: 2 }, items: [{ codigo: 'A1', nombre: 'Aspirina', mg: 500 }, { codigo: 'B2', nombre: 'Bruffen', mg: 600 }] })
    const { select } = searching({ processResults: 'response.items.map(r => ({ id: r.codigo, text: r.nombre + " " + r.mg + " mg", ...r }))' })

    await select.runSearch('as')

    assert.deepEqual(select.flatList.map(item => [item.key, item.label]), [['A1', 'Aspirina 500 mg'], ['B2', 'Bruffen 600 mg']])
    assert.equal(select.flatList[0].row.mg, 500, 'the row keeps its other fields')
})

test('process-results wins over option-label; option-label alone names the field of the label', async () => {
    page([{ id: 1, nombre: 'Uno' }], { x: [{ id: 2, text: 'Dos', nombre: 'IGNORED' }] })
    const labelled = searching({ optionLabel: 'nombre' })
    await labelled.select.runSearch('u')
    assert.deepEqual(labelled.select.flatList.map(item => item.label), ['Uno'])

    const processed = searching({ optionLabel: 'nombre', processResults: 'response.x' })
    await processed.select.runSearch('d')
    assert.deepEqual(processed.select.flatList.map(item => item.label), ['Dos'])
})

// ── Failing without breaking anything ──

for (const [name, answer] of [['a 500', httpFailure(500)], ['a 404', httpFailure(404)], ['an unreadable answer', 'not json at all'], ['a network failure', new Error('offline')]]) {
    test(`${name}: nothing throws, the message and Retry appear, loading turns off, and what was chosen is kept`, async () => {
        page(answer)
        const { select, events, box } = searching({ chosen: '5', search: 'Ibuprofen' })
        box.value = 'Ibuprofen'

        await select.runSearch('ibu')   // would reject if any failure escaped

        assert.equal(select.state, 'error')
        assert.notEqual(select.state, 'loading')
        assert.equal(select.hasRetry, true)
        assert.equal(select.chosen, '5', 'the choice is still there')
        assert.equal(select.$refs.value.value, '5', 'and the form still sends it')
        assert.equal(box.value, 'Ibuprofen')
        assert.equal(events.length, 0, 'no change was announced')
        assert.ok(errors.length >= 1, 'the detail went to the console')
    })
}

test('the field is usable after a failure: typing again searches again, and Retry too', async () => {
    const calls = page(httpFailure(500), { results: rows }, { results: [{ id: 9, text: 'Nine' }] })
    const { select } = searching()

    await select.runSearch('ibu')
    assert.equal(select.state, 'error')
    await select.runSearch('ibup')
    assert.equal(select.state, 'ready')
    assert.equal(select.failure, false)
    select.search = 'nin'
    await select.retry()

    assert.equal(calls.length, 3)
    assert.deepEqual(select.flatList.map(item => item.label), ['Nine'])
})

test('on-error is told (status and body) and the component still shows its message', async () => {
    page(httpFailure(503))
    const told = []
    globalThis.told = told
    const { select } = searching({ onError: 'told.push([status, data.message, error.response.status])' })

    await select.runSearch('ibu')

    assert.deepEqual(told, [[503, 'boom', 503]])
    assert.equal(select.failure, true)
    assert.equal(select.hasRetry, true)
})

test('on-error returning false silences the message of the component', async () => {
    page(httpFailure(500))
    const { select } = searching({ onError: 'false' })

    await select.runSearch('ibu')

    assert.equal(select.state, 'error')
    assert.equal(select.failure, false)
    assert.equal(select.hasRetry, false)
    assert.equal(select.statusText, '')
})

test('a process-results that throws is a failed search that goes through on-error, not a stuck "searching"', async () => {
    page({ results: rows })
    const told = []
    globalThis.told = told
    const { select } = searching({ processResults: 'response.nothing.here', onError: 'told.push(error.name)' })

    await select.runSearch('ibu')

    assert.equal(select.state, 'error')
    assert.equal(told.length, 1)
    assert.equal(select.hasRetry, true)
})

test('an on-select or an on-error that throws never breaks the component', async () => {
    page({ results: rows }, httpFailure(500))
    const { select, events } = searching({ onSelect: 'nonexistent.call()', onError: 'nonexistent.call()' })

    await select.runSearch('ibu')
    select.choose(select.flatList[0])
    assert.equal(events.at(-1)[0], 'dbl-select-changed')

    await select.runSearch('x')
    assert.equal(select.state, 'error')
})

// ── Choosing ──

test('choosing announces dbl-select-changed with the whole row, and on-select gets the row', async () => {
    page({ results: rows })
    globalThis.picked = []
    const { select, events } = searching({ onSelect: 'picked.push(row.lab)' })

    await select.runSearch('ibu')
    select.choose(select.flatList[1])

    assert.deepEqual(events.at(-1), ['dbl-select-changed', { name: 'medicine', value: 2, row: rows[1] }])
    assert.deepEqual(globalThis.picked, ['Zeta'])
    assert.equal(select.$refs.value.value, '2')
})

test('the local mode announces exactly what it did before: { name, value }', () => {
    page()
    const { select, events } = searching({ searchUrl: '', options: { 1: 'One' } })
    select.choose({ key: '1', label: 'One' })
    assert.deepEqual(events.at(-1), ['dbl-select-changed', { name: 'medicine', value: '1' }])
})

// ── The keyboard ──

test('the arrows move over the options (and wrap), Enter chooses, Escape closes', async () => {
    page({ results: rows })
    const { select, events } = searching()
    await select.runSearch('ibu')
    select.open = true
    const key = (name) => { const event = { key: name, preventDefault() {}, stopPropagation() {} }; select.onKeydown(event) }

    key('ArrowDown'); assert.equal(select.active, 0)
    key('ArrowDown'); assert.equal(select.active, 1)
    key('ArrowDown'); assert.equal(select.active, 0)
    key('ArrowUp');   assert.equal(select.active, 1)
    key('Enter')
    assert.equal(events.at(-1)[1].value, 2)
    assert.equal(select.open, false)

    select.open = true; select.active = 0
    key('Escape')
    assert.equal(select.open, false)
    assert.equal(select.active, -1)
})
