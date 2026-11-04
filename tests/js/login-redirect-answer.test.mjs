import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dbSelectRemote, dbDataTable, dbResourceDetails } from '../../resources/js/daisyblade.js'

// The session expired and the data URL redirects to the login page. axios and fetch follow the redirect and hand over
// its HTML as a normal 200 answer. Read as data, `data.data ?? []` turned it into "zero rows" or "zero options": the user
// saw an empty table or select and no way to retry. An answer that is text instead of JSON is an error.

const LOGIN_PAGE = '<!DOCTYPE html><html><body><form action="/login">...</form></body></html>'

function pageWithAxios(answer) {
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { get: async () => ({ data: answer, status: 200 }) } }
}

let consoleErrors
const realConsoleError = console.error
beforeEach(() => { consoleErrors = []; console.error = (...args) => consoleErrors.push(args) })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location })

/** A remote select with a <select> inside its container. */
function remoteSelect(config) {
    const field = { form: null, selectedOptions: [], value: '', querySelectorAll: () => [], dispatchEvent() {} }
    return Object.assign(dbSelectRemote(config), { $root: { querySelector: () => field }, $nextTick: async () => {} })
}

test('select remote: an HTML answer (login page) is an error with retry, not an empty list', async () => {
    pageWithAxios(LOGIN_PAGE)
    const select = remoteSelect({ url: '/options', messages: { loadFailed: 'No se han podido cargar las opciones' } })

    await select.init()

    assert.equal(select.error, 'No se han podido cargar las opciones')
    assert.deepEqual(select.options, [])
    assert.equal(select.loading, false)
    assert.equal(consoleErrors.length, 1)   // the detail goes to the console
})

test('select remote: after the login page, retrying loads the options and clears the message', async () => {
    pageWithAxios(LOGIN_PAGE)
    const select = remoteSelect({ url: '/options' })
    await select.init()

    pageWithAxios([{ value: 'a', label: 'A' }])
    await select.retry()

    assert.equal(select.error, '')
    assert.deepEqual(select.options, [{ value: 'a', label: 'A' }])
})

test('data-table: an HTML answer (login page) is an error with retry, not an empty table', async () => {
    pageWithAxios(LOGIN_PAGE)
    const table = dbDataTable({ loadUrl: '/rows', messages: { loadFailed: 'No se han podido cargar los datos' } })
    table.rows = [{ id: 1 }]

    await table.load()

    assert.equal(table.error, 'No se han podido cargar los datos')
    assert.deepEqual(table.rows, [])
    assert.equal(table.loading, false)
})

test('resource-details: an HTML answer (login page) is an error with retry, not a resource made of text', async () => {
    pageWithAxios(LOGIN_PAGE)
    const details = dbResourceDetails({ loadUrl: '/resource', messages: { loadFailed: 'Error al cargar el recurso' } })

    await details.load()

    assert.equal(details.error, 'Error al cargar el recurso')
    assert.equal(details.resource, null)
    assert.equal(details.loading, false)
})

test('an empty answer (204) is still "nothing", not an error', async () => {
    pageWithAxios('')
    const select = remoteSelect({ url: '/options' })
    const table = dbDataTable({ loadUrl: '/rows' })

    await select.init()
    await table.load()

    assert.equal(select.error, '')
    assert.deepEqual(select.options, [])
    assert.equal(table.error, '')
    assert.deepEqual(table.rows, [])
})
