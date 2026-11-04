// One test (or a few) per convention of the 3.x line, on the JavaScript side.
// Run with: node --test tests/js/conventions.test.mjs
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

import * as daisyblade from '../../resources/js/daisyblade.js'
import {
    readStored, dblSidebar, dblWizard, dblAutoForm, dblDataTable, dblResourceDetails, dblSelectRemote, dblTabsLazy, dblSpreadsheetImport,
} from '../../resources/js/daisyblade.js'
import { dblCalendar } from '../../resources/js/components/calendar.js'
import { dblUpload } from '../../resources/js/components/upload.js'
import { dblMap } from '../../resources/js/components/map.js'
import { dblSignature } from '../../resources/js/components/signature.js'
import { dblRichText } from '../../resources/js/components/rich-text.js'
import { dblFlowchart } from '../../resources/js/components/flowchart.js'

const ROOT = new URL('../../resources/js/', import.meta.url).pathname

/** Every .js file under resources/js, as { 'components/map.js': source }. */
const scripts = (directory = ROOT) => Object.fromEntries(
    readdirSync(directory).flatMap(name => {
        const path = join(directory, name)
        if (statSync(path).isDirectory()) return Object.entries(scripts(path)).map(([key, value]) => [join(name, key), value])
        return name.endsWith('.js') ? [[relative(directory, path), readFileSync(path, 'utf8')]] : []
    }),
)

// ── 1. One prefix: dbl ─────────────────────────────────────────────────────

test('1. every factory exported by daisyblade.js starts with dbl', () => {
    const factories = Object.keys(daisyblade).filter(name => /^db[A-Z]|^dbl[A-Z]/.test(name))
    assert.ok(factories.length >= 14, 'the factories are there')
    assert.deepEqual(factories.filter(name => !name.startsWith('dbl')), [])
})

/** A minimal localStorage: enough for getItem, setItem and removeItem. */
const memoryStorage = (initial = {}) => {
    const data = new Map(Object.entries(initial))
    return {
        getItem: key => (data.has(key) ? data.get(key) : null),
        setItem: (key, value) => { data.set(key, String(value)) },
        removeItem: key => { data.delete(key) },
        keys: () => [...data.keys()],
    }
}

test('1. readStored reads the new key as it is', () => {
    const storage = memoryStorage({ dbl_sidebar: 'false', db_sidebar: 'true' })
    assert.equal(readStored(storage, 'dbl_sidebar'), 'false')
    assert.deepEqual(storage.keys().sort(), ['db_sidebar', 'dbl_sidebar'], 'with the new key present, the old one is not touched')
})

test('1. readStored moves a 2.x key to the 3.x key once, and removes the old one', () => {
    const storage = memoryStorage({ db_sidebar: 'true' })
    assert.equal(readStored(storage, 'dbl_sidebar'), 'true')
    assert.deepEqual(storage.keys(), ['dbl_sidebar'])
    assert.equal(readStored(storage, 'dbl_sidebar'), 'true', 'the second read finds the new key')
})

test('1. readStored gives null when neither key exists', () => {
    const storage = memoryStorage()
    assert.equal(readStored(storage, 'dbl_sidebar'), null)
    assert.deepEqual(storage.keys(), [])
})

/** The globals that dblSidebar() and dblWizard() read: localStorage and window. */
const withBrowser = (storage, run) => {
    const saved = { localStorage: globalThis.localStorage, window: globalThis.window }
    globalThis.localStorage = storage
    globalThis.window = { innerWidth: 1280 }
    try { return run() } finally {
        globalThis.localStorage = saved.localStorage
        globalThis.window = saved.window
    }
}

test('1. the sidebar keeps what a 2.x user had saved, under the new key', () => {
    const storage = memoryStorage({ db_sidebar: 'false' })
    const sidebar = withBrowser(storage, () => { const s = dblSidebar(); s.init(); return s })
    assert.equal(sidebar.open, false, 'the saved choice is applied')
    assert.deepEqual(storage.keys(), ['dbl_sidebar'])
})

test('1. the wizard keeps the progress a 2.x user had saved, under the new key', () => {
    const oldKey = 'db_wizard_7_alta_v1'
    const storage = memoryStorage({ [oldKey]: JSON.stringify({ step: 2, data: { name: 'Ana' } }) })
    const wizard = withBrowser(storage, () => {
        const w = dblWizard({ userId: 7, formId: 'alta' })
        w.init()
        return w
    })
    assert.equal(wizard.step, 2)
    assert.equal(wizard.data.name, 'Ana')
    assert.deepEqual(storage.keys(), ['dbl_wizard_7_alta_v1'])
})

test('1. no script still names a factory, a CSS class or a storage key with the 2.x prefix', () => {
    const offenders = Object.entries(scripts())
        .flatMap(([file, source]) => (source.match(/\bdb[A-Z]\w*|\bdb-[\w-]+|\bdb_[a-z]\w*/g) ?? []).map(found => `${file}: ${found}`))
    assert.deepEqual(offenders, [])
})

// ── 2. URL props: load-url, submit-url, upload-url ──────────────────────────

/** A page whose axios records the URL of every call, and answers with `answer` as the data. */
function pageThatRecordsUrls(answer = {}) {
    const urls = []
    const reply = url => { urls.push(url); return { data: answer, status: 200 } }
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { get: async url => reply(url), post: async url => reply(url), put: async url => reply(url) } }
    return urls
}

const leavePage = () => { delete globalThis.window; delete globalThis.location }

test('2. the components that read data ask the URL given as loadUrl', async () => {
    const urls = pageThatRecordsUrls({ data: [] })
    try {
        await dblDataTable({ loadUrl: '/table' }).init()
        await dblResourceDetails({ loadUrl: '/details' }).init()
        await dblTabsLazy({ tabs: [{ loadUrl: '/tab' }] }).init()
        const select = Object.assign(dblSelectRemote({ loadUrl: '/options' }), {
            $root: { querySelector: () => ({ form: null, selectedOptions: [], querySelectorAll: () => [] }) },
            $nextTick: async () => {},
        })
        await select.init()
        await dblCalendar({ loadUrl: '/events', initialDate: '2026-10-05' }).init()
    } finally { leavePage() }

    assert.deepEqual(urls.map(url => url.split('?')[0]), ['/table', '/details', '/tab', '/options', '/events'])
})

test('2. auto-form and wizard send what the user filled in to submitUrl', async () => {
    const urls = pageThatRecordsUrls({})
    try {
        await dblAutoForm({ submitUrl: '/save-form' }).submit()
        await dblWizard({ submitUrl: '/save-wizard' }).submit()
    } finally { leavePage() }

    assert.deepEqual(urls, ['/save-form', '/save-wizard'])
})

test('2. the spreadsheet import and the upload send their files to uploadUrl', async () => {
    const urls = pageThatRecordsUrls({ id: 1, url: '/f/1' })
    try {
        const importer = dblSpreadsheetImport({ uploadUrl: '/import' })
        importer.file = new Blob(['a,b'])
        await importer.upload()

        const upload = Object.assign(dblUpload({ uploadUrl: '/files' }), { $dispatch() {}, announce() {} })
        await upload.send({ file: new Blob(['x']), name: 'x.txt', size: 1 })
    } finally { leavePage() }

    assert.deepEqual(urls, ['/import', '/files'])
})

// ── 8. Events: dbl-<component>-<what happened>, always with $dispatch and an object as the detail ──

/** Gives a component the `$dispatch` of Alpine, recording what it announces as [{ name, detail }]. */
const recording = component => {
    const announced = []
    return [Object.assign(component, { $dispatch: (name, detail = {}) => announced.push({ name, detail }) }), announced]
}

test('8. the calendar announces dbl-calendar-event-clicked with the event as an object', () => {
    const [calendar, announced] = recording(dblCalendar({ loadUrl: '/e' }))
    calendar.emit({ id: 3, title: 'Reunión' })
    assert.deepEqual(announced, [{ name: 'dbl-calendar-event-clicked', detail: { id: 3, title: 'Reunión' } }])
})

test('8. the map announces dbl-map-picked with { lat, lng }', () => {
    const [map, announced] = recording(dblMap({}))
    map.setPicked(39.123456789, -0.5)
    assert.deepEqual(announced, [{ name: 'dbl-map-picked', detail: { lat: 39.123457, lng: -0.5 } }])
})

test('8. the upload announces dbl-upload-uploaded and dbl-upload-removed', async () => {
    const urls = pageThatRecordsUrls({ id: 5, name: 'a.txt', url: '/f/5' })
    globalThis.window.confirm = () => true
    try {
        const [upload, announced] = recording(dblUpload({ uploadUrl: '/files' }))
        upload.announce = () => {}
        const item = upload.makeItem({ file: new Blob(['x']), name: 'a.txt', size: 1, status: 'uploading' })
        upload.items.push(item)
        await upload.send(item)
        await upload.remove(item)
        assert.deepEqual(announced.map(event => event.name), ['dbl-upload-uploaded', 'dbl-upload-removed'])
        assert.deepEqual(announced[1].detail, { id: 5, name: 'a.txt' })
        assert.deepEqual(urls, ['/files'])
    } finally { leavePage() }
})

test('8. the signature announces dbl-signature-signed or dbl-signature-cleared with { name, value }', () => {
    const [signature, announced] = recording(dblSignature({ name: 'firma' }))
    signature.$refs = { input: { value: '' } }
    signature.pad = { isEmpty: () => false, toDataURL: () => 'data:image/png;base64,AAA' }
    signature.sync(true)
    signature.pad = { isEmpty: () => true }
    signature.sync(true)
    assert.deepEqual(announced, [
        { name: 'dbl-signature-signed', detail: { name: 'firma', value: 'data:image/png;base64,AAA' } },
        { name: 'dbl-signature-cleared', detail: { name: 'firma', value: '' } },
    ])
})

test('8. the rich text announces dbl-rich-text-changed with { name, value }', () => {
    const [richText, announced] = recording(dblRichText({ name: 'cuerpo' }, {}))
    richText.$refs = { input: { value: '<p>Hola</p>' } }
    richText.announce()
    assert.deepEqual(announced, [{ name: 'dbl-rich-text-changed', detail: { name: 'cuerpo', value: '<p>Hola</p>' } }])
})

/** The parts of Alpine that dblSelectRemote uses around a fake <select> whose chosen options are `chosen`. */
const remoteSelectWith = (config, chosen) => {
    const field = { form: null, selectedOptions: chosen.map(value => ({ value })), querySelectorAll: () => [] }
    return recording(Object.assign(dblSelectRemote(config), { $root: { querySelector: () => field } }))
}

test('8. the remote select announces dbl-select-changed with { name, value }: a text, or a list when multiple', () => {
    const [single, singleEvents] = remoteSelectWith({ name: 'city', loadUrl: '/c' }, ['ES-TF'])
    single.remember()
    const [multiple, multipleEvents] = remoteSelectWith({ name: 'cities', multiple: true, loadUrl: '/c' }, ['A', 'B'])
    multiple.remember()
    const [empty, emptyEvents] = remoteSelectWith({ name: 'city', loadUrl: '/c' }, [])
    empty.remember()

    assert.deepEqual(singleEvents, [{ name: 'dbl-select-changed', detail: { name: 'city', value: 'ES-TF' } }])
    assert.deepEqual(multipleEvents, [{ name: 'dbl-select-changed', detail: { name: 'cities', value: ['A', 'B'] } }])
    assert.deepEqual(emptyEvents, [{ name: 'dbl-select-changed', detail: { name: 'city', value: '' } }])
})

test('8. the flowchart announces dbl-flowchart-* from its root, takes the orders of the window by name, and asks the toast with dbl-toast-show', () => {
    const events = []
    const root = { dispatchEvent(event) { events.push({ name: event.type, detail: event.detail, bubbles: event.bubbles }); return true } }
    const flowchart = Object.assign(dblFlowchart({ name: 'flujo', nodes: [{ id: 'a', label: 'A' }], edges: [] }), { $root: root, $nextTick: fn => fn(), $refs: {} })
    flowchart.init()

    flowchart.nodeClick(flowchart.vNodes[0])
    flowchart.notify('Mal')
    assert.deepEqual(events.map(event => event.name), ['dbl-flowchart-node-clicked', 'dbl-toast-show'])
    assert.ok(events.every(event => event.bubbles && typeof event.detail === 'object'), 'bubble up and carry an object')

    flowchart.onSet({ name: 'otro', nodes: [], edges: [] })
    assert.equal(flowchart.vNodes.length, 1, 'an order for another diagram is ignored')
    flowchart.onSet({ name: 'flujo', nodes: [], edges: [] })
    assert.equal(flowchart.vNodes.length, 0)

    let focused
    flowchart.focus = id => { focused = id }
    flowchart.onFocus({ name: 'flujo', id: 7 })
    assert.equal(focused, 7)
})
