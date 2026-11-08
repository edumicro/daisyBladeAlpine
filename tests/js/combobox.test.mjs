import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { searchParams, toRows, readResults, filterRows, localRows, latestRequest, errorInfo, dbCombobox } from '../../resources/js/daisyblade.js'

// ── Lo puro ─────────────────────────────────────────────────────────────────

test('searchParams: texto, límite, parámetros fijos y dependencia, cada uno con su nombre', () => {
    assert.deepEqual(
        searchParams({ query: 'ibu', limit: 20, params: { country_code: 'ES' }, dependsOn: 'lab', dependencyValue: '7' }),
        { country_code: 'ES', lab: '7', q: 'ibu', limit: 20 },
    )
})

test('searchParams: query-param cambia el nombre, y sin límite ni dependencia no salen', () => {
    assert.deepEqual(searchParams({ queryParam: 'term', query: 'x' }), { term: 'x' })
})

test('searchParams: el texto tecleado y el límite mandan sobre un params fijo con el mismo nombre', () => {
    assert.deepEqual(searchParams({ query: 'a', limit: 5, params: { q: 'viejo', limit: 99 } }), { q: 'a', limit: 5 })
})

test('toRows: la forma de select2 {results: [...]}', () => {
    const rows = toRows({ results: [{ id: 1, text: 'Ibuprofeno', lab: 'Cinfa' }] })
    assert.deepEqual(rows, [{ id: '1', text: 'Ibuprofeno', row: { id: 1, text: 'Ibuprofeno', lab: 'Cinfa' } }])
})

test('toRows: un array pelado de {id, text}, de {value, label}, y un mapa {valor: etiqueta}', () => {
    assert.deepEqual(toRows([{ id: 0, text: 'Cero' }]).map(r => [r.id, r.text]), [['0', 'Cero']])
    assert.deepEqual(toRows([{ value: 'a', label: 'Ana' }]).map(r => [r.id, r.text]), [['a', 'Ana']])
    assert.deepEqual(toRows({ es: 'España', pt: 'Portugal' }).map(r => [r.id, r.text]), [['es', 'España'], ['pt', 'Portugal']])
})

test('toRows: nada, un 204 o una forma que no es ninguna dan lista vacía, sin lanzar', () => {
    for (const body of [null, undefined, '', 5, true]) assert.deepEqual(toRows(body), [])
    assert.deepEqual(toRows([null, { text: 'sin id' }]), [])
})

test('toRows: las filas conservan sus campos extra y option-label nombra el campo del texto', () => {
    const [row] = toRows([{ id: 3, nombre: 'Paracetamol', foto: 'p.png' }], { optionLabel: 'nombre' })
    assert.equal(row.text, 'Paracetamol')
    assert.equal(row.row.foto, 'p.png')
})

test('readResults: process-results convierte una respuesta de forma rara en filas pintables', () => {
    const body = { payload: { hits: [{ code: 'A1', name: 'Ana', surname: 'López' }, { code: 'B2', name: 'Pau', surname: 'Ferrer' }] } }
    // Con esa forma, sin función, no hay nada que pintar.
    assert.deepEqual(readResults(body), [])
    const rows = readResults(body, {
        processResults: data => data.payload.hits.map(h => ({ id: h.code, text: `${h.name} ${h.surname}`, hit: h })),
    })
    assert.deepEqual(rows.map(r => [r.id, r.text]), [['A1', 'Ana López'], ['B2', 'Pau Ferrer']])
    assert.equal(rows[0].row.hit.surname, 'López')
})

test('readResults: si hay process-results, option-label no cuenta', () => {
    const rows = readResults([{ id: 1, nombre: 'N', text: 'T' }], { optionLabel: 'nombre', processResults: data => data })
    assert.equal(rows[0].text, 'T')
})

test('filterRows y localRows: el modo local filtra por texto o id, también con grupos', () => {
    const rows = localRows({ Frutas: { m: 'Manzana', p: 'Pera' }, x: 'Suelta' })
    assert.deepEqual(rows.map(r => r.group), ['Frutas', 'Frutas', ''])
    assert.deepEqual(filterRows(rows, 'pe').map(r => r.id), ['p'])
    assert.deepEqual(filterRows(rows, ' ').length, 3)
})

test('latestRequest: solo la última petición es la vigente, y begin() aborta la anterior', () => {
    const tracker = latestRequest()
    const first = tracker.begin()
    const second = tracker.begin()
    assert.equal(first.signal.aborted, true)
    assert.equal(tracker.isCurrent(first.number), false)
    assert.equal(tracker.isCurrent(second.number), true)
    tracker.cancel()
    assert.equal(tracker.isCurrent(second.number), false)
})

test('errorInfo: status y cuerpo como en axios, y no lanza con un error raro', () => {
    assert.deepEqual(errorInfo({ message: 'x', response: { status: 500, data: { message: 'boom' } } }), { status: 500, data: { message: 'boom' }, message: 'x' })
    assert.deepEqual(errorInfo(undefined), { status: null, data: null, message: '' })
})

// ── El componente, con un fetch falso ───────────────────────────────────────

const realConsoleError = console.error
const realFetch = globalThis.fetch
beforeEach(() => {
    console.error = () => {}
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { Alpine: { evaluate: (el, expression, extras) => new Function(...Object.keys(extras.scope), `return (${expression})`)(...Object.values(extras.scope)) } }
})
afterEach(() => {
    console.error = realConsoleError
    globalThis.fetch = realFetch
    delete globalThis.window
    delete globalThis.location
})

const answer = (status, body) => ({
    status, statusText: '', headers: new Map([['content-type', 'application/json']]),
    text: async () => JSON.stringify(body),
})

function mount(config) {
    const field = { value: '', disabled: false, form: null, setAttribute() {}, removeAttribute() {} }
    const hidden = { value: '', disabled: false, setAttribute() {}, dispatchEvent() {} }
    return Object.assign(dbCombobox({ id: 'c', searchUrl: '/s', minChars: 2, ...config }), {
        $refs: { field, hidden }, $root: { dispatchEvent() {} }, $nextTick: async () => {},
    })
}

test('una búsqueda que devuelve results pinta las filas y apaga «buscando»', async () => {
    globalThis.fetch = async () => answer(200, { results: [{ id: 1, text: 'Ibuprofeno' }] })
    const combo = mount({})
    combo.query = 'ibu'
    await combo.search()
    assert.deepEqual(combo.rows.map(r => r.text), ['Ibuprofeno'])
    assert.equal(combo.loading, false)
    assert.equal(combo.error, '')
})

for (const [label, fail] of [
    ['un 500', async () => answer(500, { message: 'boom' })],
    ['un 404', async () => answer(404, { message: 'no' })],
    ['un JSON ilegible', async () => ({ status: 200, statusText: '', headers: new Map([['content-type', 'application/json']]), text: async () => '{roto' })],
    ['un fallo de red', async () => { throw new TypeError('network down') }],
]) {
    test(`${label}: no lanza, deja el mensaje, apaga «buscando» y NO pierde lo ya elegido`, async () => {
        globalThis.fetch = fail
        const combo = mount({ value: '42', text: 'Paracetamol' })
        combo.query = 'ibu'
        await combo.search()   // si lanzara, el test fallaría aquí
        assert.notEqual(combo.error, '')
        assert.equal(combo.loading, false)
        assert.equal(combo.value, '42')
        assert.equal(combo.committed.text, 'Paracetamol')
        assert.equal(combo.$refs.hidden.value, '')   // el hidden se sincroniza solo al tocar; el valor sigue en `value`
        // y el campo sigue usable: la siguiente búsqueda, ya buena, funciona sin recargar
        globalThis.fetch = async () => answer(200, [{ id: 7, text: 'Ibuprofeno' }])
        await combo.retry()
        assert.equal(combo.error, '')
        assert.deepEqual(combo.rows.map(r => r.id), ['7'])
    })
}

test('un process-results que lanza es un error más: mensaje, sin colgar «buscando»', async () => {
    globalThis.fetch = async () => answer(200, { raro: true })
    const combo = mount({ processResults: 'data.nada.noExiste.map(x => x)' })
    combo.query = 'ibu'
    await combo.search()
    assert.notEqual(combo.error, '')
    assert.equal(combo.loading, false)
})

test('on-error recibe status y cuerpo, y si devuelve false el componente se calla', async () => {
    globalThis.fetch = async () => answer(503, { message: 'mantenimiento' })
    globalThis.seen = null
    const combo = mount({ onError: '(globalThis.seen = [status, data.message], false)' })
    combo.query = 'ibu'
    await combo.search()
    assert.deepEqual(globalThis.seen, [503, 'mantenimiento'])
    assert.equal(combo.error, '')
    delete globalThis.seen
})

test('on-error que no devuelve false: sale además el mensaje propio; y si on-error lanza, no revienta', async () => {
    globalThis.fetch = async () => answer(500, {})
    const combo = mount({ onError: 'lanza.ahora()' })
    combo.query = 'ibu'
    await combo.search()
    assert.notEqual(combo.error, '')
})

test('una petición cancelada no es un error, y la respuesta vieja que llega tarde se descarta', async () => {
    const pending = []
    globalThis.fetch = (url, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })))
        pending.push({ url, resolve })
    })
    const combo = mount({})
    combo.query = 'ib'
    const first = combo.search()
    combo.query = 'ibu'
    const second = combo.search()   // cancela la primera
    await first
    assert.equal(combo.error, '')   // la cancelación no parpadea un error
    assert.equal(combo.loading, true)   // la segunda sigue en vuelo
    pending[1].resolve(answer(200, [{ id: 2, text: 'Ibuprofeno' }]))
    await second
    assert.deepEqual(combo.rows.map(r => r.id), ['2'])
    assert.equal(combo.loading, false)
})

test('menos de min-chars caracteres no pregunta nada', async () => {
    let calls = 0
    globalThis.fetch = async () => { calls++; return answer(200, []) }
    const combo = mount({ minChars: 3 })
    combo.query = 'ib'
    await combo.search()
    assert.equal(calls, 0)
})

test('la petición lleva el texto, el límite, los params fijos y el query-param', async () => {
    let asked
    globalThis.fetch = async url => { asked = url; return answer(200, []) }
    const combo = mount({ queryParam: 'term', limit: 20, params: { country_code: 'ES' } })
    combo.query = ' ibu '
    await combo.search()
    assert.equal(asked, '/s?country_code=ES&term=ibu&limit=20')
})
