import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSpreadsheetImport } from '../../resources/js/daisyblade.js'
import { dblUpload } from '../../resources/js/components/upload.js'
import { dblCalendar } from '../../resources/js/components/calendar.js'
import { dblMap } from '../../resources/js/components/map.js'

// The rule of the whole package: where the component reads JSON (calendar, map, upload...), any text is an error, an HTML
// page above all (the login page that an expired session redirects to). Where it does not need JSON (the importer), only an
// HTML page is an error; "OK" with a 200 is a success because the rows were imported.
const LOGIN_PAGE = '<!DOCTYPE html><html><body><form>login</form></body></html>'
const realConsoleError = console.error
let answer
beforeEach(() => {
    console.error = () => {}
    answer = { data: LOGIN_PAGE, status: 200 }
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.document = { cookie: '', querySelector: () => null }
    globalThis.window = { axios: { get: async () => answer, post: async () => answer } }
})
afterEach(() => { console.error = realConsoleError; for (const n of ['window', 'location', 'document']) delete globalThis[n] })

test('calendar: an HTML answer is an error, not an empty month', async () => {
    const calendar = dblCalendar({ loadUrl: '/events' })
    calendar.cursor = new Date(2026, 9, 1)
    await calendar.load()
    assert.equal(calendar.error, true)
})

test('calendar: an HTML answer is not cached as an empty month', async () => {
    const calendar = dblCalendar({ loadUrl: '/events' })
    calendar.cursor = new Date(2026, 9, 1)
    await calendar.load()
    assert.deepEqual(Object.keys(calendar.cache), [])
})

test('map: an HTML answer to the address search shows the failure message', async () => {
    const map = dblMap({ geocodeUrl: '/geocode', labels: { search_failed: 'Search failed' } })
    map.query = 'madrid'
    await map.search()
    assert.equal(map.error, 'Search failed')
})

test('map: an HTML answer for a layer is a failed layer', async () => {
    const map = dblMap({ labels: { layer_failed: 'Layer failed' } })
    const layer = { id: 'a', label: 'Shops', loadUrl: '/shops', loading: true }
    await map.load(layer)
    assert.equal(map.error, 'Layer failed: Shops')
})

test('upload: an answer without an id (204, or {}) is an error, the file does not become "done"', async () => {
    for (const empty of [{ data: '', status: 204 }, { data: {}, status: 200 }]) {
        answer = empty
        const upload = Object.assign(dblUpload({ uploadUrl: '/upload', messages: { failed: 'Falló' } }), { $dispatch() {} })
        const item = upload.makeItem({ name: 'a.pdf', size: 3, file: new Blob(['abc']), status: 'uploading' })
        await upload.send(item)
        assert.equal(item.status, 'error')
    }
})

test('upload: a plain-text 200 is an error too (the form needs the id of the file)', async () => {
    answer = { data: 'OK', status: 200 }
    const upload = Object.assign(dblUpload({ uploadUrl: '/upload', messages: { failed: 'Falló' } }), { $dispatch() {} })
    const item = upload.makeItem({ name: 'a.pdf', size: 3, file: new Blob(['abc']), status: 'uploading' })
    await upload.send(item)
    assert.equal(item.status, 'error')
})

test('spreadsheet import: a plain-text 200 stays "done" (the rows were imported)', async () => {
    answer = { data: 'OK', status: 200 }
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })
    importer.file = new Blob(['a'])
    await importer.upload()
    assert.equal(importer.step, 'done')
})

test('spreadsheet import: an HTML page is an error', async () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })
    importer.file = new Blob(['a'])
    await importer.upload()
    assert.equal(importer.step, 'error')
})
