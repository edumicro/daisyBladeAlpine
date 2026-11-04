import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { request, isContractError } from '../../resources/js/http.js'

// The contract with the server looks at the TYPE and the FORM of the answer, never at URLs. Comparing the URL asked for with
// the URL that answered took every request for a redirection as soon as the app had `axios.defaults.baseURL` or a `<base href>`.

const realConsoleError = console.error
beforeEach(() => { console.error = () => {}; globalThis.location = { href: 'https://app.test/page', origin: 'https://app.test' } })
afterEach(() => { console.error = realConsoleError; delete globalThis.location; delete globalThis.window })

test('axios with defaults.baseURL: an answer from /api/options is not a redirect of the url "/options"', async () => {
    // axios joins `baseURL` ('/api') and the url ('/options'): the XHR `responseURL` is https://app.test/api/options
    const axios = { get: async () => ({ status: 200, data: [{ value: 1, label: 'One' }], headers: { 'content-type': 'application/json' }, request: { responseURL: 'https://app.test/api/options' } }) }
    const answer = await request({ url: '/options' }, { axios }).catch(error => error)
    assert.equal(isContractError(answer), false, `raised ${answer?.message}`)
    assert.deepEqual(answer.data, [{ value: 1, label: 'One' }])
})

test('a POST that answers 302 and ends in a GET of the same path is judged the same by fetch and by XMLHttpRequest: by what arrives', async () => {
    const body = '{"saved":true}'
    globalThis.fetch = async () => Object.defineProperty(new Response(body, { status: 200, headers: { 'content-type': 'application/json' } }), 'redirected', { value: true })
    const viaFetch = await request({ method: 'post', url: '/same', data: {} })

    globalThis.XMLHttpRequest = class {
        open() {}
        setRequestHeader() {}
        getResponseHeader() { return 'application/json' }
        getAllResponseHeaders() { return 'content-type: application/json' }
        send() { Object.assign(this, { status: 200, statusText: '', responseText: body, responseURL: 'https://app.test/same' }); queueMicrotask(() => this.onload()) }
    }
    const viaXhr = await request({ method: 'post', url: '/same', data: {}, onUploadProgress() {} })
    delete globalThis.XMLHttpRequest

    assert.deepEqual(viaFetch.data, { saved: true })
    assert.deepEqual(viaXhr.data, { saved: true })
})
