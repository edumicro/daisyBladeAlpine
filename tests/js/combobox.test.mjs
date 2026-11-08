import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { searchParams, toRows, readResults, filterRows, localRows, latestRequest, errorInfo, hasMore, dbCombobox } from '../../resources/js/daisyblade.js'

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

test('toRows: una lista de {id, text} conserva la fila entera con sus campos extra', () => {
    const rows = toRows([{ id: 1, text: 'Ibuprofeno', lab: 'Cinfa' }])
    assert.deepEqual(rows, [{ id: '1', text: 'Ibuprofeno', row: { id: 1, text: 'Ibuprofeno', lab: 'Cinfa' } }])
})

test('toRows: un array pelado de {id, text} y de {value, label}', () => {
    assert.deepEqual(toRows([{ id: 0, text: 'Cero' }]).map(r => [r.id, r.text]), [['0', 'Cero']])
    assert.deepEqual(toRows([{ value: 'a', label: 'Ana' }]).map(r => [r.id, r.text]), [['a', 'Ana']])
})

test('toRows: el mapa {valor: etiqueta} (lo que da pluck) se lee como las opciones de un select', () => {
    assert.deepEqual(toRows({ 12: 'Paracetamol', 7: 'Ibuprofeno' }).map(r => [r.id, r.text]), [['7', 'Ibuprofeno'], ['12', 'Paracetamol']])
    assert.deepEqual(toRows({ es: 'España', pt: 'Portugal' }).map(r => [r.id, r.text]), [['es', 'España'], ['pt', 'Portugal']])
})

test('toRows: una lista vacía es «sin resultados», no un error', () => {
    assert.deepEqual(toRows([]), [])
    assert.deepEqual(toRows({}), [])   // un mapa sin entradas
})

test('toRows: lo que no es un mapa ni una lista NO es «cero resultados», es una respuesta que no se entiende', () => {
    for (const body of [{ results: [{ id: 1, text: 'x' }] }, { results: [] }, { data: [{ id: 1, text: 'x' }] }, { ok: true }, { ok: true, mensaje: 'caducada' }, { a: { b: 1 } }, { a: null }, null, undefined, '', 5, true]) {
        assert.throws(() => toRows(body), /neither a key-value map nor a list of rows/, JSON.stringify(body))
    }
})

test('toRows: el mensaje del error dice cómo arreglarlo (process-results)', () => {
    assert.throws(() => toRows({ results: [] }), /process-results="response\.results"/)
})

test('readResults: process-results="response.results" desenvuelve la forma de select2 y devuelve filas', () => {
    const rows = readResults({ results: [{ id: 1, text: 'Ana' }] }, { processResults: response => response.results })
    assert.deepEqual(rows.map(r => r.text), ['Ana'])
    // y si la función devuelve otra forma, el mismo trato
    assert.throws(() => readResults({ results: [] }, { processResults: response => response }), /neither a key-value map/)
})

test('toRows: las filas sin id o que no son objetos se descartan', () => {
    assert.deepEqual(toRows([null, 'x', { text: 'sin id' }, { id: 1, text: 'ok' }]).map(r => r.id), ['1'])
})

test('toRows: las filas conservan sus campos extra y option-label nombra el campo del texto', () => {
    const [row] = toRows([{ id: 3, nombre: 'Paracetamol', foto: 'p.png' }], { optionLabel: 'nombre' })
    assert.equal(row.text, 'Paracetamol')
    assert.equal(row.row.foto, 'p.png')
})

test('readResults: process-results convierte una respuesta de forma rara en filas pintables', () => {
    const body = { payload: { hits: [{ code: 'A1', name: 'Ana', surname: 'López' }, { code: 'B2', name: 'Pau', surname: 'Ferrer' }] } }
    // Con esa forma, sin función, no se entiende.
    assert.throws(() => readResults(body))
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
        $refs: { field, hidden }, $root: { dispatchEvent: () => true }, $nextTick: async () => {},
    })
}

test('un cuerpo {ok: true} o un {results: [...]} sin process-results es un fallo que se dice: mensaje, «buscando» apagado, lo elegido intacto', async () => {
    for (const body of [{ ok: true }, { results: [{ id: 1, text: 'x' }] }]) {
        globalThis.fetch = async () => answer(200, body)
        const combo = mount({ value: '42', text: 'Paracetamol' })
        combo.query = 'ibu'
        await combo.search()
        assert.notEqual(combo.error, '', JSON.stringify(body))
        assert.deepEqual(combo.rows, [])
        assert.equal(combo.loading, false)
        assert.equal(combo.value, '42')
        assert.equal(combo.committed.text, 'Paracetamol')
    }
})

test('process-results="response.results" hace que un endpoint con envoltorio funcione', async () => {
    globalThis.fetch = async () => answer(200, { results: [{ id: 1, text: 'Ibuprofeno' }] })
    const combo = mount({ processResults: 'response.results' })
    combo.query = 'ibu'
    await combo.search()
    assert.equal(combo.error, '')
    assert.deepEqual(combo.rows.map(r => r.text), ['Ibuprofeno'])
})

test('un cuerpo vacío o que no es JSON de objeto SÍ es un fallo, con mensaje', async () => {
    globalThis.fetch = async () => answer(200, 5)
    const combo = mount({})
    combo.query = 'ibu'
    await combo.search()
    assert.notEqual(combo.error, '')
    assert.equal(combo.loading, false)
})

test('un process-results devuelve filas de un cuerpo raro y no hay error', async () => {
    globalThis.fetch = async () => answer(200, { ok: true, hits: [{ code: 'A', nombre: 'Ana' }] })
    const combo = mount({ processResults: 'data.hits.map(h => ({ id: h.code, text: h.nombre }))' })
    combo.query = 'an'
    await combo.search()
    assert.equal(combo.error, '')
    assert.deepEqual(combo.rows.map(r => r.text), ['Ana'])
})

test('con allow-free-text lo tecleado no se convierte en id: value queda vacío y el texto se queda en el input', () => {
    const combo = mount({ allowFreeText: true, value: '42', text: 'Paracetamol' })
    combo.field.value = 'Algo nuevo'
    combo.onInput({ target: combo.field })
    assert.equal(combo.value, '')
    combo.close()
    assert.equal(combo.value, '')
    assert.equal(combo.query, 'Algo nuevo')
    assert.equal(combo.field.value, 'Algo nuevo')
})

test('sin allow-free-text, lo tecleado sin elegir vuelve a lo último elegido al cerrar', () => {
    const combo = mount({ value: '42', text: 'Paracetamol' })
    combo.field.value = 'basura'
    combo.onInput({ target: combo.field })
    combo.close()
    assert.equal(combo.value, '42')
    assert.equal(combo.field.value, 'Paracetamol')
})

test('una búsqueda que devuelve una lista pinta las filas y apaga «buscando»', async () => {
    globalThis.fetch = async () => answer(200, [{ id: 1, text: 'Ibuprofeno' }])
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

test('un fallo lanza select-error con {name, error: {status, data}}; si nadie lo cancela sale el mensaje', async () => {
    globalThis.fetch = async () => answer(503, { message: 'mantenimiento' })
    const combo = mount({ name: 'medicine_id' })
    const events = []
    combo.$root.dispatchEvent = event => { events.push(event); return !event.defaultPrevented }
    combo.query = 'ibu'
    await combo.search()
    assert.equal(events.length, 1)
    assert.equal(events[0].type, 'select-error')
    assert.equal(events[0].cancelable, true)
    assert.equal(events[0].detail.name, 'medicine_id')
    assert.equal(events[0].detail.error.status, 503)
    assert.deepEqual(events[0].detail.error.data, { message: 'mantenimiento' })
    assert.notEqual(combo.error, '')
})

test('preventDefault() sobre select-error: el componente se calla y deja pintar al consumidor', async () => {
    globalThis.fetch = async () => answer(500, {})
    const combo = mount({})
    combo.$root.dispatchEvent = event => { event.preventDefault(); return !event.defaultPrevented }
    combo.query = 'ibu'
    await combo.search()
    assert.equal(combo.error, '')
    assert.equal(combo.loading, false)
})

test('un oyente de select-error que lanza no revienta el componente', async () => {
    globalThis.fetch = async () => answer(500, {})
    const combo = mount({})
    combo.$root.dispatchEvent = () => { throw new Error('mi oyente') }
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

// ── Paginación con botón ────────────────────────────────────────────────────

const rowsOf = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => ({ id: from + i, text: `Fila ${from + i}` }))

test('hasMore: la palabra del servidor manda; si no, se cuenta contra el límite', () => {
    assert.equal(hasMore({ results: [], pagination: { more: true } }, 0, 20), true)
    assert.equal(hasMore({ results: [], pagination: { more: false } }, 20, 20), false)
    assert.equal(hasMore([], 20, 20), true)    // tantas como el límite: puede haber más
    assert.equal(hasMore([], 19, 20), false)   // menos: se acabó
    assert.equal(hasMore([], 0, 20), false)
    assert.equal(hasMore([], 5, 0), false)     // sin límite no se puede contar
})

test('searchParams: la primera página no lleva page, las siguientes sí, con el nombre de page-param', () => {
    assert.deepEqual(searchParams({ query: 'a', limit: 20, page: 1 }), { q: 'a', limit: 20 })
    assert.deepEqual(searchParams({ query: 'a', limit: 20, page: 3 }), { q: 'a', limit: 20, page: 3 })
    assert.deepEqual(searchParams({ query: 'a', limit: 20, page: 2, pageParam: 'p' }), { q: 'a', limit: 20, p: 2 })
})

test('un endpoint que no sabe de páginas funciona: una página llena enseña «más», y «más» AÑADE las filas', async () => {
    const asked = []
    globalThis.fetch = async url => {
        asked.push(url)
        return answer(200, url.includes('page=2') ? rowsOf(3, 3) : rowsOf(1, 2))
    }
    const combo = mount({ limit: 2 })
    combo.query = 'fi'
    await combo.search()
    assert.equal(combo.more, true)
    await combo.loadMore()
    assert.deepEqual(combo.rows.map(r => r.id), ['1', '2', '3'])   // se añaden, no se sustituyen
    assert.equal(combo.more, false)                                // llegó una página corta
    assert.equal(combo.loadingMore, false)
    assert.deepEqual(asked, ['/s?q=fi&limit=2', '/s?q=fi&limit=2&page=2'])
})

test('si el servidor manda pagination.more (y process-results desenvuelve) se le cree antes que a la cuenta', async () => {
    globalThis.fetch = async () => answer(200, { results: rowsOf(1, 2), pagination: { more: false } })
    const combo = mount({ limit: 2, processResults: 'response.results' })
    combo.query = 'fi'
    await combo.search()
    assert.equal(combo.more, false)
})

test('si la página siguiente falla, las filas que había se quedan y «más» sigue ahí; Reintentar pide esa página', async () => {
    globalThis.fetch = async () => answer(200, rowsOf(1, 2))
    const combo = mount({ limit: 2, value: '9', text: 'Elegido' })
    combo.query = 'fi'
    await combo.search()
    globalThis.fetch = async () => answer(500, {})
    await combo.loadMore()
    assert.deepEqual(combo.rows.map(r => r.id), ['1', '2'])
    assert.notEqual(combo.error, '')
    assert.equal(combo.more, true)
    assert.equal(combo.loadingMore, false)
    assert.equal(combo.value, '9')
    globalThis.fetch = async () => answer(200, rowsOf(3, 3))
    await combo.retry()
    assert.deepEqual(combo.rows.map(r => r.id), ['1', '2', '3'])
    assert.equal(combo.error, '')
})

test('teclear mientras llega la página siguiente la descarta: no se añade a la búsqueda nueva', async () => {
    let release
    globalThis.fetch = async url => url.includes('page=2')
        ? new Promise(resolve => { release = () => resolve(answer(200, rowsOf(3, 4))) })
        : answer(200, rowsOf(1, 2))
    const combo = mount({ limit: 2 })
    combo.query = 'fi'
    await combo.search()
    const pending = combo.loadMore()
    assert.equal(combo.loadingMore, true)
    combo.field.value = 'fil'
    combo.onInput({ target: combo.field })
    assert.equal(combo.loadingMore, false)
    release()
    await pending
    assert.deepEqual(combo.rows.map(r => r.id), ['1', '2'])
})

test('con el teclado se llega al botón «más»: ↓ pasa de la última fila a él, y Enter pide la página', async () => {
    globalThis.fetch = async url => answer(200, url.includes('page=2') ? rowsOf(3, 3) : rowsOf(1, 2))
    const combo = mount({ limit: 2 })
    combo.query = 'fi'
    combo.open = true
    await combo.search()
    const key = name => combo.onKeydown({ target: combo.field, key: name, preventDefault() {}, stopPropagation() {} })
    key('ArrowDown'); key('ArrowDown'); key('ArrowDown')
    assert.equal(combo.active, 2)   // rows.length: el botón
    key('Enter')
    await new Promise(resolve => setTimeout(resolve, 0))
    assert.deepEqual(combo.rows.map(r => r.id), ['1', '2', '3'])
    assert.equal(combo.active, 2)   // sigue desde la primera fila nueva
})
