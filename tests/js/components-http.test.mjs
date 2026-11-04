import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblCalendar } from '../../resources/js/components/calendar.js'
import { dblMap, registerMap } from '../../resources/js/components/map.js'
import { dblUpload } from '../../resources/js/components/upload.js'

// The optional components call the server through the shared ladder (http.js). These tests check, from each
// component, that the injected client is used, that fetch works when there is none, and that other domains
// go through fetch.

const realFetch = globalThis.fetch
let fetchCalls

function fakeFetch(body = '{}', status = 200, type = 'application/json') {
    fetchCalls = []
    globalThis.fetch = async (url, init) => {
        fetchCalls.push({ url, init })
        return new Response(body, { status, headers: { 'content-type': type } })
    }
}

beforeEach(() => {
    globalThis.location = { href: 'https://app.test/page', origin: 'https://app.test' }
    globalThis.window = {}
    globalThis.document = { cookie: '', querySelector: () => null }
    fakeFetch()
})
afterEach(() => {
    globalThis.fetch = realFetch
    for (const name of ['location', 'window', 'document', 'XMLHttpRequest']) delete globalThis[name]
})

/** Alpine gives components `$root`, `$refs`, `$dispatch`, `$nextTick`: here, the minimum. */
const withAlpine = (component, extra = {}) => Object.assign(component, { $dispatch() {}, $nextTick: fn => fn(), $root: {}, $refs: {}, ...extra })

// ── calendar ───────────────────────────────────────────────────────────────

test('calendar: asks with the injected axios and hands it the range', async () => {
    const seen = []
    const axios = { get: async (url, config) => { seen.push({ url, params: config.params }); return { data: [{ id: 1, title: 'A', start: '2026-10-05' }] } } }
    const calendar = withAlpine(dblCalendar({ loadUrl: '/events', initialDate: '2026-10-05' }, { axios }))

    calendar.init()
    await new Promise(resolve => setTimeout(resolve))

    assert.equal(seen[0].url, '/events')
    assert.deepEqual(Object.keys(seen[0].params), ['start', 'end'])
    assert.equal(calendar.events.length, 1)
    assert.equal(fetchCalls.length, 0)
})

test('calendar: without axios it uses fetch, and a 500 puts it in the error state (with retry)', async () => {
    fakeFetch('boom', 500, 'text/plain')
    const calendar = withAlpine(dblCalendar({ loadUrl: '/events?x=1', initialDate: '2026-10-05' }))

    calendar.init()
    await new Promise(resolve => setTimeout(resolve))

    assert.match(fetchCalls[0].url, /^\/events\?x=1&start=2026-09-28&end=2026-11-08$/)
    assert.equal(fetchCalls[0].init.headers['x-requested-with'], 'XMLHttpRequest')
    assert.equal(calendar.error, true)
    assert.equal(calendar.loading, false)
})

// ── map ────────────────────────────────────────────────────────────────────

const layer = (url) => ({ id: 'l', label: 'L', loadUrl: url, color: '#000', icon: '', cluster: false, visible: true, loading: true })
const mapConfig = { labels: { layer_failed: 'Capa no cargada', search_failed: 'Búsqueda fallida', init_failed: 'No se ha podido cargar el mapa.' }, geocodeUrl: '/geocode' }

test('map: a layer of our own goes through the injected axios, one of another domain through fetch (no Laravel headers)', async () => {
    const axiosCalls = []
    registerMap({ data() {} }, { axios: { get: async (url, config) => { axiosCalls.push({ url, headers: config.headers }); return { data: { type: 'FeatureCollection', features: [] } } } } })
    const map = withAlpine(dblMap(mapConfig))

    await map.load(layer('/api/own.geojson'))
    await map.load(layer('https://tiles.example.org/public.geojson'))

    assert.equal(axiosCalls.length, 1)
    assert.equal(axiosCalls[0].url, '/api/own.geojson')
    assert.equal(axiosCalls[0].headers.accept, 'application/json')
    assert.equal(fetchCalls.length, 1)
    assert.equal(fetchCalls[0].url, 'https://tiles.example.org/public.geojson')
    assert.equal(fetchCalls[0].init.headers['x-requested-with'], undefined)
    assert.equal(fetchCalls[0].init.headers.accept, 'application/json')
})

test('map: a layer that fails shows the translated message; the geocoder reads `data` and reports its failure', async () => {
    registerMap({ data() {} }, {})
    const map = withAlpine(dblMap(mapConfig))

    fakeFetch('nope', 404, 'text/plain')
    await map.load(layer('/missing.geojson'))
    assert.equal(map.error, 'Capa no cargada: L')

    fakeFetch('{"data":[{"label":"Calle","lat":39.4,"lng":-0.3}]}')
    map.query = 'calle mayor'
    await map.search()
    assert.equal(fetchCalls[0].url, '/geocode?q=calle+mayor')
    assert.deepEqual(map.results, [{ label: 'Calle', lat: 39.4, lng: -0.3 }])

    fakeFetch('x', 500, 'text/plain')
    await map.search()
    assert.equal(map.error, 'Búsqueda fallida')
    assert.deepEqual(map.results, [])
})

test('map: when it cannot start the user sees the translated message, and the developer detail goes to the console', async () => {
    registerMap({ data() {} }, {})        // no maplibregl injected: the developer's mistake
    const errors = []
    const realConsoleError = console.error
    console.error = (...args) => errors.push(args)
    try {
        const map = withAlpine(dblMap({ ...mapConfig, layers: [] }), { $refs: { canvas: {} } })
        await map.init()

        assert.equal(map.error, 'No se ha podido cargar el mapa.')
        assert.doesNotMatch(map.error, /maplibregl|registerMap/)
        assert.match(String(errors[0][1].message), /maplibregl/)
    } finally { console.error = realConsoleError }
})

// ── upload ─────────────────────────────────────────────────────────────────

class XhrFake {
    static last
    constructor() { XhrFake.last = this; this.requestHeaders = {}; this.upload = {} }
    open(method, url) { this.method = method; this.url = url }
    setRequestHeader(name, value) { this.requestHeaders[name] = value }
    send(body) { this.body = body }
    abort() { this.onabort?.() }
    getResponseHeader() { return 'application/json' }
    getAllResponseHeaders() { return '' }
    reply(status, text) { Object.assign(this, { status, statusText: '', responseText: text }); this.onload() }
}

const uploader = (extra = {}) => withAlpine(dblUpload({ uploadUrl: '/upload', multiple: true, messages: { failed: 'Falló', cancelled: 'Cancelado' }, ...extra }))
const newItem = (upload) => upload.makeItem({ name: 'a.pdf', size: 3, file: new Blob(['abc']), status: 'uploading' })

test('upload: without axios the file goes by XMLHttpRequest, with progress, and a 422 gives the message of the server', async () => {
    globalThis.XMLHttpRequest = XhrFake
    const upload = uploader()

    const ok = newItem(upload)
    const sending = upload.send(ok)
    XhrFake.last.upload.onprogress({ loaded: 1, total: 4, lengthComputable: true })
    assert.equal(ok.progress, 25)
    XhrFake.last.reply(200, '{"id":9,"url":"/f/9"}')
    await sending
    assert.equal(ok.status, 'done')
    assert.equal(ok.id, 9)

    const rejected = newItem(upload)
    const failing = upload.send(rejected)
    XhrFake.last.reply(422, '{"message":"x","errors":{"file":["Too big"]}}')
    await failing
    assert.equal(rejected.status, 'error')
    assert.equal(rejected.error, 'Too big')
    assert.equal(XhrFake.last.requestHeaders['x-requested-with'], 'XMLHttpRequest')
})

test('upload: cancelling is reported as cancelled, not as a failure; deleting and reordering use the same ladder', async () => {
    globalThis.XMLHttpRequest = XhrFake
    const upload = uploader({ deleteUrl: '/upload/{id}', reorderUrl: '/upload/order' })

    const item = newItem(upload)
    const sending = upload.send(item)
    item.cancel()
    await sending
    assert.equal(item.error, 'Cancelado')

    globalThis.window.confirm = () => true
    const done = upload.makeItem({ id: 5, name: 'b.pdf', status: 'done' })
    upload.items = [done, upload.makeItem({ id: 6, name: 'c.pdf', status: 'done' }), upload.makeItem({ id: 7, name: 'd.pdf', status: 'done' })]
    await upload.remove(done)
    assert.equal(fetchCalls[0].init.method, 'DELETE')
    assert.equal(fetchCalls[0].url, '/upload/5')

    await upload.move(upload.items[0], 1)      // [6, 7] -> [7, 6]
    assert.equal(fetchCalls[1].url, '/upload/order')
    assert.equal(fetchCalls[1].init.body, '{"ids":[7,6]}')
})
