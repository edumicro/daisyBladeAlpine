import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dbDataTable, dbTabsLazy, dbSelectRemote, dbResourceDetails, dbAutoForm, dbSpreadsheetImport } from '../../resources/js/daisyblade.js'

// A page whose axios we control: each call takes the next answer of the list.
// An answer that is an Error makes the call fail; anything else is the `data` of a good response.
function pageWithAxios(...answers) {
    const calls = []
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = {
        axios: {
            get: async (url, config) => respond(calls, answers, { url, ...config }),
            post: async (url, data, config) => respond(calls, answers, { url, data, ...config }),
        },
    }
    return calls
}

function respond(calls, answers, call) {
    calls.push(call)
    const answer = answers.length > 1 ? answers.shift() : answers[0]
    if (answer instanceof Error) throw answer
    return { data: answer, status: 200 }
}

const failure = (status, data) => Object.assign(new Error(`Request failed with status code ${status}`), { response: { status, data } })

const realConsoleError = console.error
let consoleErrors

beforeEach(() => {
    // The failures below are expected: keep them out of the test report, but check they reach the console.
    consoleErrors = []
    console.error = (...args) => consoleErrors.push(args)
})
afterEach(() => {
    console.error = realConsoleError
    delete globalThis.window
    delete globalThis.location
})

// ── data-table ─────────────────────────────────────────────────────────────

test('data-table: a failed request ends the loading and leaves a translated message, not a spinner for ever', async () => {
    pageWithAxios(failure(500, {}))
    const table = dbDataTable({ loadUrl: '/rows', messages: { loadFailed: 'No se han podido cargar los datos' } })
    table.rows = [{ id: 1 }]

    await table.load()

    assert.equal(table.loading, false)
    assert.equal(table.error, 'No se han podido cargar los datos')
    assert.deepEqual(table.rows, [])
    assert.equal(consoleErrors.length, 1)   // the technical detail goes to the console
})

test('data-table: retrying after a failure loads the rows and clears the message', async () => {
    pageWithAxios(failure(500, {}), { data: [{ id: 7 }], meta: { total: 1 } })
    const table = dbDataTable({ loadUrl: '/rows' })

    await table.load()
    assert.equal(table.error, 'Could not load the data')   // English text when the view passed no translation

    await table.load()
    assert.equal(table.error, '')
    assert.equal(table.loading, false)
    assert.deepEqual(table.rows, [{ id: 7 }])
})

test('data-table: an answer that arrives after a newer request is ignored', async () => {
    let releaseFirst
    const calls = []
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = {
        axios: {
            get: (url, config) => {
                calls.push(config.params.page)
                if (calls.length === 1) return new Promise(resolve => { releaseFirst = () => resolve({ data: { data: [{ id: 'old' }] } }) })
                return Promise.resolve({ data: { data: [{ id: 'new' }] } })
            },
        },
    }
    const table = dbDataTable({ loadUrl: '/rows' })

    const first = table.load()
    table.page = 2
    await table.load()
    releaseFirst()
    await first

    assert.deepEqual(table.rows, [{ id: 'new' }])
    assert.equal(table.loading, false)
})

// ── tabs-lazy ──────────────────────────────────────────────────────────────

test('tabs-lazy: a tab that fails shows its message, stops loading, and retry brings the content', async () => {
    pageWithAxios(failure(404, '<p>nope</p>'), '<p>hello</p>')
    const tabs = dbTabsLazy({ tabs: [{ url: '/tab-0' }], messages: { loadFailed: 'No se ha podido cargar el contenido' } })

    await tabs.init()
    assert.equal(tabs.isLoading(0), false)
    assert.equal(tabs.hasError(0), true)
    assert.equal(tabs.errors[0], 'No se ha podido cargar el contenido')
    assert.equal(consoleErrors.length, 1)

    await tabs.retry(0)
    assert.equal(tabs.hasError(0), false)
    assert.equal(tabs.contents[0], '<p>hello</p>')
})

test('tabs-lazy: clicking a tab whose request is still running does not ask again', async () => {
    const calls = pageWithAxios('<p>x</p>')
    const tabs = dbTabsLazy({ tabs: [{ url: '/a' }, { url: '/b' }] })

    const loading = tabs.select(1)
    await tabs.select(1)
    await loading

    assert.equal(calls.length, 1)
})

// ── select with remote options ─────────────────────────────────────────────

/** The parts of Alpine that dbSelectRemote uses, around a fake <select>. */
function remoteSelect(config) {
    const field = { form: null, selectedOptions: [], value: '', querySelectorAll: () => [], dispatchEvent() {} }
    return Object.assign(dbSelectRemote(config), {
        $root: { querySelector: () => field },
        $nextTick: async () => {},
    })
}

test('select remote: a failed request leaves a message and no options, and the retry button loads them', async () => {
    pageWithAxios(failure(500, {}), [{ value: 'a', label: 'A' }])
    const select = remoteSelect({ url: '/options', messages: { loadFailed: 'No se han podido cargar las opciones' } })

    await select.init()
    assert.equal(select.loading, false)
    assert.equal(select.error, 'No se han podido cargar las opciones')
    assert.deepEqual(select.options, [])

    await select.retry()
    assert.equal(select.error, '')
    assert.deepEqual(select.options, [{ value: 'a', label: 'A' }])
})

// ── The default texts of the other components ──────────────────────────────

test('resource-details: the server message wins; without it the translated text, else the English one', async () => {
    pageWithAxios(failure(403, { message: 'Forbidden by policy' }), failure(500, {}))
    const translated = dbResourceDetails({ loadUrl: '/r', messages: { loadFailed: 'Error al cargar el recurso' } })
    await translated.load()
    assert.equal(translated.error, 'Forbidden by policy')
    await translated.load()
    assert.equal(translated.error, 'Error al cargar el recurso')

    pageWithAxios(failure(500, {}))
    const old = dbResourceDetails({ loadUrl: '/r' })
    await old.load()
    assert.equal(old.error, 'Error loading resource')
})

test('auto-form: a 422 fills the field errors; another failure shows the translated text, not the technical one', async () => {
    pageWithAxios(failure(422, { errors: { name: ['Required'] } }), failure(500, {}))
    const form = dbAutoForm({ action: '/save', method: 'post', messages: { failed: 'Ha ocurrido un error' } })

    await form.submit()
    assert.deepEqual(form.errors, { name: ['Required'] })
    assert.equal(form.globalError, '')

    await form.submit()
    assert.equal(form.globalError, 'Ha ocurrido un error')
    assert.equal(form.loading, false)
})

test('auto-form: a view published before 2.4 (no messages) keeps showing the error message of the client', async () => {
    pageWithAxios(failure(500, {}))
    const form = dbAutoForm({ action: '/save', method: 'post' })

    await form.submit()

    assert.equal(form.globalError, 'Request failed with status code 500')
})

test('spreadsheet import: a failed upload shows the translated text', async () => {
    pageWithAxios(failure(500, {}))
    const importer = dbSpreadsheetImport({ uploadUrl: '/up', messages: { uploadFailed: 'La subida ha fallado' } })
    importer.file = new Blob(['a,b'])

    await importer.upload()

    assert.equal(importer.error, 'La subida ha fallado')
    assert.equal(importer.step, 'error')
    assert.equal(importer.loading, false)
})
