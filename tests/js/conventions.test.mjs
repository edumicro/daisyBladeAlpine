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
