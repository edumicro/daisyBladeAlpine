import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblAutoForm, dblWizard } from '../../resources/js/daisyblade.js'

// 2.4.0 made a text answer (the login page after a redirect) an error for the select, the table and the details.
// auto-form and wizard took ANY 2xx as success: an expired session, or a `submit-url` left empty by a 2.x `action=`
// (the POST then goes to the current page), showed "Saved!" and nothing was saved.
// Chrome, 3.0 before the fix: `<x-dbl::sections.auto-form action="/save">` + submit -> POST /current-page, success = true.

function pageWithAxios(answer) {
    const calls = []
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.window = { axios: { post: async (url, data) => { calls.push({ url, data }); return { data: answer, status: 200 } } }, location: { href: '' } }
    globalThis.localStorage = { getItem: () => null, setItem() {}, removeItem() {} }
    return calls
}

const realConsoleError = console.error
beforeEach(() => { console.error = () => {} })
afterEach(() => { console.error = realConsoleError; delete globalThis.window; delete globalThis.location; delete globalThis.localStorage })

test('auto-form: an HTML answer to the submit is a failure, not a success', async () => {
    pageWithAxios('<!DOCTYPE html><html><body><form>login</form></body></html>')
    const form = dblAutoForm({ submitUrl: '/save', values: { name: 'x' }, messages: { failed: 'Fallo' } })

    await form.submit()

    assert.equal(form.success, false)
    assert.equal(form.globalError, 'Fallo')
})

test('auto-form: a JSON answer is still a success, and an empty one (a 204) too', async () => {
    pageWithAxios({ message: 'ok' })
    const form = dblAutoForm({ submitUrl: '/save', values: {} })
    await form.submit()
    assert.equal(form.success, true)

    pageWithAxios('')
    const again = dblAutoForm({ submitUrl: '/save', values: {} })
    await again.submit()
    assert.equal(again.success, true)
    assert.equal(again.globalError, '')
})

test('wizard: an HTML answer to the submit is a failure, and the saved progress is kept', async () => {
    pageWithAxios('<!DOCTYPE html><html><body>login</body></html>')
    let cleared = false
    globalThis.localStorage.removeItem = () => { cleared = true }
    const wizard = dblWizard({ submitUrl: '/save', values: {}, messages: { failed: 'Fallo' } })

    await wizard.submit()

    assert.equal(wizard.globalError, 'Fallo')
    assert.equal(cleared, false)
})

test('wizard: a JSON answer clears the saved progress', async () => {
    pageWithAxios({ message: 'ok' })
    let cleared = false
    globalThis.localStorage.removeItem = () => { cleared = true }
    const wizard = dblWizard({ submitUrl: '/save', values: {} })

    await wizard.submit()

    assert.equal(cleared, true)
    assert.equal(wizard.globalError, '')
})
