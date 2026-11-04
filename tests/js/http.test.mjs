import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { request, joinUrl, isCrossOrigin, isCanceled, isContractError } from '../../resources/js/http.js'

// ── A fake browser: only what http.js looks at ─────────────────────────────

const realFetch = globalThis.fetch

/** The headers every request carries by contract (see the top of http.js), as an axios client receives them. */
const contractHeaders = { accept: 'application/json', 'x-requested-with': 'XMLHttpRequest' }
let fetchCalls

/** Replace `fetch` with a function that records the call and answers with `reply`. */
function fakeFetch(reply = { status: 200, body: '{"ok":true}', type: 'application/json' }) {
    fetchCalls = []
    globalThis.fetch = async (url, init) => {
        fetchCalls.push({ url, init })
        if (reply instanceof Error) throw reply
        return new Response(reply.body, { status: reply.status, headers: { 'content-type': reply.type } })
    }
}

/** An axios stand-in that records every call. */
function fakeAxios() {
    const calls = []
    const record = name => (...args) => { calls.push({ name, args }); return Promise.resolve({ data: { from: 'axios' }, status: 200 }) }
    return { calls, get: record('get'), post: record('post'), delete: record('delete') }
}

beforeEach(() => {
    globalThis.location = { href: 'https://app.test/page', origin: 'https://app.test' }
    globalThis.window = {}
    globalThis.document = { cookie: '', querySelector: () => null }
    fakeFetch()
    XhrFake.last = undefined
})

afterEach(() => {
    globalThis.fetch = realFetch
    delete globalThis.location
    delete globalThis.window
    delete globalThis.document
    delete globalThis.XMLHttpRequest
})

// ── The ladder: injected client, window.axios, fetch ───────────────────────

test('with an injected axios, that client is used (before window.axios and before fetch)', async () => {
    const injected = fakeAxios()
    const global = fakeAxios()
    globalThis.window.axios = global

    const { data } = await request({ url: '/api/users', params: { page: 2 } }, { axios: injected })

    assert.deepEqual(data, { from: 'axios' })
    assert.equal(injected.calls.length, 1)
    assert.deepEqual(injected.calls[0].args, ['/api/users', { params: { page: 2 }, headers: contractHeaders }])
    assert.equal(global.calls.length, 0)
    assert.equal(fetchCalls.length, 0)
})

test('without an injected client, window.axios is used', async () => {
    const global = fakeAxios()
    globalThis.window.axios = global

    await request({ url: '/api/users' })

    assert.equal(global.calls.length, 1)
    assert.equal(fetchCalls.length, 0)
})

test('with no axios at all, fetch is used', async () => {
    const { data, status } = await request({ url: '/api/users', params: { page: 2 } })

    assert.deepEqual(data, { ok: true })     // the JSON is read by itself
    assert.equal(status, 200)
    assert.equal(fetchCalls.length, 1)
    assert.equal(fetchCalls[0].url, '/api/users?page=2')
})

test('axios methods get the arguments axios expects: post(url, data, config), delete(url, { data })', async () => {
    const client = fakeAxios()
    const signal = new AbortController().signal

    await request({ method: 'POST', url: '/save', data: { a: 1 }, headers: { 'X-A': '1' }, signal }, { axios: client })
    await request({ method: 'delete', url: '/remove/3', data: { why: 'old' } }, { axios: client })

    assert.deepEqual(client.calls[0].args, ['/save', { a: 1 }, { headers: { ...contractHeaders, 'x-a': '1' }, signal }])
    assert.deepEqual(client.calls[1].args, ['/remove/3', { headers: contractHeaders, data: { why: 'old' } }])
})

test('a client with no get/post methods is called through request() or as a function', async () => {
    const seen = []
    const withRequest = { request: async config => { seen.push(config); return { data: { n: 1 } } } }
    const asFunction = async config => { seen.push(config); return { data: { n: 2 } } }

    assert.equal((await request({ url: '/a' }, { axios: withRequest })).data.n, 1)
    assert.equal((await request({ method: 'put', url: '/b', data: { x: 1 } }, { axios: asFunction })).data.n, 2)
    assert.deepEqual(seen[0], { method: 'get', url: '/a', headers: contractHeaders })
    assert.deepEqual(seen[1], { method: 'put', url: '/b', data: { x: 1 }, headers: contractHeaders })
})

// ── Another domain: always fetch, no Laravel headers ───────────────────────

test('a URL on another domain goes through fetch even with axios, and without Laravel headers', async () => {
    const injected = fakeAxios()
    globalThis.window.axios = fakeAxios()
    globalThis.document.querySelector = () => ({ getAttribute: () => 'TOKEN' })

    await request({ method: 'post', url: 'https://tiles.example.org/layer.json', data: { a: 1 } }, { axios: injected })

    assert.equal(injected.calls.length, 0)
    assert.equal(fetchCalls.length, 1)
    const { headers, credentials } = fetchCalls[0].init
    assert.equal(headers['x-requested-with'], undefined)
    assert.equal(headers['x-csrf-token'], undefined)
    assert.equal(headers['x-xsrf-token'], undefined)
    assert.equal(credentials, undefined)    // fetch default: no cookies to other domains
})

test('a URL with the same origin as the page is not "another domain", even when written in full', () => {
    assert.equal(isCrossOrigin('/api/x'), false)
    assert.equal(isCrossOrigin('https://app.test/api/x'), false)
    assert.equal(isCrossOrigin('https://other.test/api/x'), true)
    assert.equal(isCrossOrigin('https://app.test:8443/api/x'), true)   // another port is another origin
    assert.equal(isCrossOrigin('//cdn.test/x'), true)
})

// ── fetch on the same domain: headers, cookies, CSRF ───────────────────────

test('same-origin fetch sends X-Requested-With and the session cookie, and no CSRF on a GET', async () => {
    globalThis.document.querySelector = () => ({ getAttribute: () => 'TOKEN' })

    await request({ url: '/api/users' })

    const { headers, credentials, method } = fetchCalls[0].init
    assert.equal(method, 'GET')
    assert.equal(headers['x-requested-with'], 'XMLHttpRequest')
    assert.equal(headers['x-csrf-token'], undefined)
    assert.equal(credentials, 'same-origin')
})

test('a POST sends the CSRF token from the meta tag and the body as JSON', async () => {
    globalThis.document.querySelector = selector => (selector === 'meta[name="csrf-token"]' ? { getAttribute: () => 'META-TOKEN' } : null)
    globalThis.document.cookie = 'XSRF-TOKEN=cookie-token'

    await request({ method: 'post', url: '/api/users', data: { name: 'Ana' } })

    const { headers, body, method } = fetchCalls[0].init
    assert.equal(method, 'POST')
    assert.equal(headers['x-csrf-token'], 'META-TOKEN')      // the meta wins over the cookie
    assert.equal(headers['x-xsrf-token'], undefined)
    assert.equal(headers['content-type'], 'application/json')
    assert.equal(body, '{"name":"Ana"}')
})

test('without the meta tag, a PUT sends the (decoded) XSRF-TOKEN cookie', async () => {
    globalThis.document.cookie = 'theme=dark; XSRF-TOKEN=ab%3Dcd%2F; other=1'

    await request({ method: 'put', url: '/api/users/1', data: {} })

    assert.equal(fetchCalls[0].init.headers['x-xsrf-token'], 'ab=cd/')
    assert.equal(fetchCalls[0].init.headers['x-csrf-token'], undefined)
})

test('a FormData body is sent as it is, and the browser is left to write the multipart content type', async () => {
    const form = new FormData()
    form.append('file', 'x')

    await request({ method: 'post', url: '/upload', data: form, headers: { 'Content-Type': 'multipart/form-data' } })

    assert.equal(fetchCalls[0].init.body, form)
    assert.equal(fetchCalls[0].init.headers['content-type'], undefined)
})

test('headers given by the caller win, whatever the case of their names', async () => {
    await request({ url: '/layer', headers: { Accept: 'application/geo+json' } })

    assert.equal(fetchCalls[0].init.headers.accept, 'application/geo+json')
})

// ── Responses and errors with the shape of axios ───────────────────────────

test('a JSON answer comes back as an object even with another content type', async () => {
    fakeFetch({ status: 200, body: '{"a":1}', type: 'text/plain' })
    assert.deepEqual((await request({ url: '/odd' })).data, { a: 1 })
})

test('an HTML fragment is only accepted where the component asks for `answer: html`, and then Accept says so', async () => {
    fakeFetch({ status: 200, body: '<p>hello</p>', type: 'text/html' })
    assert.equal((await request({ url: '/tab', answer: 'html' })).data, '<p>hello</p>')
    assert.match(fetchCalls[0].init.headers.accept, /^text\/html/)

    await assert.rejects(request({ url: '/tab' }), error => isContractError(error) && error.reason === 'not-json')
})

test('every request asks for JSON (Accept) and says it is Ajax (X-Requested-With, same origin only)', async () => {
    await request({ url: '/api/users' })
    await request({ url: 'https://tiles.example.org/layer.json' })

    assert.equal(fetchCalls[0].init.headers.accept, 'application/json')
    assert.equal(fetchCalls[0].init.headers['x-requested-with'], 'XMLHttpRequest')
    assert.equal(fetchCalls[1].init.headers.accept, 'application/json')
    assert.equal(fetchCalls[1].init.headers['x-requested-with'], undefined)
})

test('an empty body (a 204) is a contract error, unless the component says `json-or-empty`; then it reads as {}', async () => {
    fakeFetch({ status: 204, body: null, type: 'text/plain' })
    await assert.rejects(request({ url: '/none' }), error => isContractError(error) && error.reason === 'empty')
    assert.deepEqual((await request({ url: '/none', answer: 'json-or-empty' })).data, {})
})

test('plain text with a 200 is a contract error, and the console tells the developer which endpoint and where to read', async () => {
    const logged = []
    const realError = console.error
    console.error = message => logged.push(message)
    fakeFetch({ status: 200, body: 'OK', type: 'text/plain' })
    try {
        await assert.rejects(request({ url: '/save' }), error => isContractError(error) && error.reason === 'not-json')
    } finally { console.error = realError }

    assert.equal(logged.length, 1)
    assert.match(logged[0], /\/save/)
    assert.match(logged[0], /docs\/contrato\.md/)
})

test('a redirect is a contract error, with fetch, XMLHttpRequest and axios', async () => {
    const silenced = console.error
    console.error = () => {}
    try {
        // fetch says it by itself
        globalThis.fetch = async () => Object.defineProperty(new Response('{"ok":true}', { status: 200, headers: { 'content-type': 'application/json' } }), 'redirected', { value: true })
        await assert.rejects(request({ url: '/api/users' }), error => isContractError(error) && error.reason === 'redirected')

        // axios leaves the final URL in request.responseURL
        const redirectedAxios = { get: async () => ({ data: { ok: true }, status: 200, request: { responseURL: 'https://app.test/login' } }) }
        await assert.rejects(request({ url: '/api/users' }, { axios: redirectedAxios }), error => isContractError(error) && error.reason === 'redirected')

        // the same endpoint (with the query string axios writes in its own way) is not a redirect
        const sameAxios = { get: async () => ({ data: { ok: true }, status: 200, request: { responseURL: 'https://app.test/api/users?page=2' } }) }
        assert.deepEqual((await request({ url: '/api/users', params: { page: 2 } }, { axios: sameAxios })).data, { ok: true })
    } finally { console.error = silenced }
})

test('a 422 fails with error.response.status and error.response.data, like axios', async () => {
    fakeFetch({ status: 422, body: '{"message":"Invalid","errors":{"name":["Required"]}}', type: 'application/json' })

    await assert.rejects(request({ method: 'post', url: '/api/users', data: {} }), error => {
        assert.equal(error.response.status, 422)
        assert.deepEqual(error.response.data.errors, { name: ['Required'] })
        assert.equal(error.response.data.message, 'Invalid')
        assert.equal(error.code, 'ERR_BAD_REQUEST')
        assert.equal(error.message, 'Request failed with status code 422')
        return true
    })
})

test('a 500 is an error too, with its own code and the text of the answer', async () => {
    fakeFetch({ status: 500, body: 'Server exploded', type: 'text/plain' })

    await assert.rejects(request({ url: '/boom' }), error => {
        assert.equal(error.response.status, 500)
        assert.equal(error.response.data, 'Server exploded')
        assert.equal(error.code, 'ERR_BAD_RESPONSE')
        return true
    })
})

test('with no answer at all the error has no response, like a network error in axios', async () => {
    fakeFetch(new TypeError('Failed to fetch'))

    await assert.rejects(request({ url: '/offline' }), error => {
        assert.equal(error.response, undefined)
        assert.equal(error.code, 'ERR_NETWORK')
        assert.equal(error.message, 'Network Error')
        return true
    })
})

test('aborting is a cancellation (code ERR_CANCELED), never a network error', async () => {
    const abort = new Error('aborted')
    abort.name = 'AbortError'
    fakeFetch(abort)

    await assert.rejects(request({ url: '/slow' }), error => {
        assert.equal(error.code, 'ERR_CANCELED')
        assert.equal(isCanceled(error), true)
        return true
    })
    assert.equal(isCanceled(new Error('other')), false)
    assert.equal(isCanceled(new Error('x'), { isCancel: () => true }), true)   // old axios: client.isCancel
})

// ── Upload progress without axios: XMLHttpRequest ──────────────────────────

/** Just enough of XMLHttpRequest for http.js; `XhrFake.last` is the one in use. */
class XhrFake {
    static last
    constructor() { XhrFake.last = this; this.requestHeaders = {}; this.upload = {}; this.aborted = false }
    open(method, url) { this.method = method; this.url = url }
    setRequestHeader(name, value) { this.requestHeaders[name] = value }
    send(body) { this.body = body }
    abort() { this.aborted = true; this.onabort?.() }
    getResponseHeader() { return this.contentType }
    getAllResponseHeaders() { return `Content-Type: ${this.contentType}\r\nX-Other: 1\r\n` }
    // Test helper: the server answers.
    reply(status, text, contentType = 'application/json') {
        Object.assign(this, { status, statusText: '', responseText: text, contentType })
        this.onload()
    }
}

test('with onUploadProgress and no axios, XMLHttpRequest is used and reports the progress', async () => {
    globalThis.XMLHttpRequest = XhrFake
    const progress = []
    const form = new FormData()

    const pending = request({ method: 'post', url: '/upload', data: form, onUploadProgress: event => progress.push(event) })
    const xhr = XhrFake.last
    xhr.upload.onprogress({ loaded: 50, total: 200, lengthComputable: true })
    xhr.upload.onprogress({ loaded: 80, total: 0, lengthComputable: false })
    xhr.reply(200, '{"id":7}')

    assert.deepEqual(await pending, { data: { id: 7 }, status: 200, statusText: '', headers: { 'content-type': 'application/json', 'x-other': '1' } })
    assert.equal(fetchCalls.length, 0)
    assert.equal(xhr.method, 'POST')
    assert.equal(xhr.body, form)
    assert.equal(xhr.requestHeaders['x-requested-with'], 'XMLHttpRequest')
    assert.deepEqual(progress, [{ loaded: 50, total: 200 }, { loaded: 80, total: undefined }])
})

test('XMLHttpRequest fails with the same shape as axios: a 422 has response.status and response.data', async () => {
    globalThis.XMLHttpRequest = XhrFake
    globalThis.document.cookie = 'XSRF-TOKEN=tok'

    const pending = request({ method: 'post', url: '/upload', data: new FormData(), onUploadProgress() {} })
    XhrFake.last.reply(422, '{"errors":{"file":["Too big"]}}')

    await assert.rejects(pending, error => {
        assert.equal(error.response.status, 422)
        assert.deepEqual(error.response.data.errors, { file: ['Too big'] })
        return true
    })
    assert.equal(XhrFake.last.requestHeaders['x-xsrf-token'], 'tok')
})

test('XMLHttpRequest: a network failure and an abort from the AbortController', async () => {
    globalThis.XMLHttpRequest = XhrFake

    const offline = request({ method: 'post', url: '/upload', data: new FormData(), onUploadProgress() {} })
    XhrFake.last.onerror()
    await assert.rejects(offline, error => error.code === 'ERR_NETWORK' && error.response === undefined)

    const controller = new AbortController()
    const canceled = request({ method: 'post', url: '/upload', data: new FormData(), signal: controller.signal, onUploadProgress() {} })
    controller.abort()
    await assert.rejects(canceled, error => error.code === 'ERR_CANCELED')
    assert.equal(XhrFake.last.aborted, true)
})

test('upload progress is only for the same domain: another domain still goes through fetch', async () => {
    globalThis.XMLHttpRequest = XhrFake

    await request({ method: 'post', url: 'https://files.example.org/up', data: new FormData(), onUploadProgress() {} })

    assert.equal(fetchCalls.length, 1)
    assert.equal(XhrFake.last, undefined)
})

test('with axios, the progress callback is handed to axios (no XMLHttpRequest of ours)', async () => {
    globalThis.XMLHttpRequest = XhrFake
    const client = fakeAxios()
    const onUploadProgress = () => {}

    await request({ method: 'post', url: '/upload', data: new FormData(), onUploadProgress }, { axios: client })

    assert.equal(client.calls[0].args[2].onUploadProgress, onUploadProgress)
    assert.equal(XhrFake.last, undefined)
})

// ── The URL joiner ─────────────────────────────────────────────────────────

test('joinUrl adds the params with ? or & as needed and leaves the URL alone when there are none', () => {
    assert.equal(joinUrl('/a', { q: 'x y' }), '/a?q=x+y')
    assert.equal(joinUrl('/a?x=1', { q: 2 }), '/a?x=1&q=2')
    assert.equal(joinUrl('/a?', { q: 2 }), '/a?q=2')
    assert.equal(joinUrl('/a', {}), '/a')
    assert.equal(joinUrl('/a', undefined), '/a')
})

test('joinUrl skips null and undefined, keeps empty text, and writes lists like axios', () => {
    assert.equal(joinUrl('/a', { sort: null, page: undefined, search: '' }), '/a?search=')
    assert.equal(joinUrl('/a', { ids: [1, 2] }), '/a?ids%5B%5D=1&ids%5B%5D=2')
    assert.equal(joinUrl('/a', { filter: { status: 'on' } }), '/a?filter%5Bstatus%5D=on')
})

test('joinUrl puts the params before a #fragment', () => {
    assert.equal(joinUrl('/a#top', { q: 1 }), '/a?q=1#top')
})
