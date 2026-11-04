'use strict'

// One HTTP function for every DaisyBlade component (daisyblade.js and components/*.js).
//
//   const { data } = await request({ url: '/api/users', params: { page: 2 } }, { axios })
//
// The client is chosen in this order, the first one that exists wins:
//
//   1. the client injected by the app:  registerXxx(Alpine, { axios })
//   2. window.axios                      (what Laravel's bootstrap.js sets up)
//   3. fetch                             (when the page has no axios at all)
//
// A URL on ANOTHER domain always goes through fetch, even if axios exists, and without any
// Laravel header: Laravel's axios adds `X-Requested-With`, and servers that are not ours reject
// it in the CORS preflight.
//
// Every client resolves to the same shape ({ data, status, ... }) and fails with the same shape
// (`error.response.status`, `error.response.data`), so the callers look at a 422 or at
// `error.response.data.message` without caring which client ran.

/** Methods that carry a body. The others (get, head) put everything in the URL. */
const METHODS_WITH_BODY = ['post', 'put', 'patch', 'delete']

// ── URLs ────────────────────────────────────────────────────────────────────

/** Add a value to a URLSearchParams the way axios does: lists as `key[]`, objects as `key[name]`. */
function appendParam(search, key, value) {
    if (value === undefined || value === null) return          // axios skips these too
    if (Array.isArray(value)) {
        value.forEach(item => appendParam(search, `${key}[]`, item))
    } else if (value instanceof Date) {
        search.append(key, value.toISOString())
    } else if (typeof value === 'object') {
        Object.entries(value).forEach(([name, item]) => appendParam(search, `${key}[${name}]`, item))
    } else {
        search.append(key, String(value))
    }
}

/**
 * The only place that glues query params onto a URL.
 * It respects a `?` that is already there and puts the params before a `#fragment`.
 */
export function joinUrl(url, params = {}) {
    const search = new URLSearchParams()
    Object.entries(params ?? {}).forEach(([key, value]) => appendParam(search, key, value))
    const query = search.toString()
    if (!query) return url

    const hashStart = url.indexOf('#')
    const path = hashStart === -1 ? url : url.slice(0, hashStart)
    const fragment = hashStart === -1 ? '' : url.slice(hashStart)
    const separator = path.includes('?') ? (/[?&]$/.test(path) ? '' : '&') : '?'
    return `${path}${separator}${query}${fragment}`
}

/** True when the URL points to a different origin (scheme, host or port) than the page. */
export function isCrossOrigin(url) {
    const pageUrl = globalThis.location?.href ?? 'http://localhost/'
    try {
        return new URL(String(url), pageUrl).origin !== new URL(pageUrl).origin
    } catch {
        return false   // not a URL we can read: let the client fail with its own error
    }
}

// ── Laravel headers (same-origin fetch and XMLHttpRequest only) ────────────

/** Value of a cookie, URL-decoded, or '' when it is not there. */
function readCookie(name) {
    for (const pair of (globalThis.document?.cookie ?? '').split('; ')) {
        const separatorAt = pair.indexOf('=')
        if (separatorAt > 0 && pair.slice(0, separatorAt) === name) return decodeURIComponent(pair.slice(separatorAt + 1))
    }
    return ''
}

/**
 * What axios + Laravel send by themselves, for the clients that do not:
 *  - `X-Requested-With`, so `$request->ajax()` is true on the server;
 *  - the CSRF token on anything that is not a GET: from `<meta name="csrf-token">`, or else from the
 *    `XSRF-TOKEN` cookie (Laravel accepts the cookie value in the `X-XSRF-TOKEN` header).
 */
function laravelHeaders(method) {
    const headers = { 'x-requested-with': 'XMLHttpRequest' }
    if (method === 'get' || method === 'head') return headers

    const metaToken = globalThis.document?.querySelector?.('meta[name="csrf-token"]')?.getAttribute('content')
    const cookieToken = readCookie('XSRF-TOKEN')
    if (metaToken) headers['x-csrf-token'] = metaToken
    else if (cookieToken) headers['x-xsrf-token'] = cookieToken
    return headers
}

// ── Request and response bodies ─────────────────────────────────────────────

/** Lower-case the names of a headers object, so 'Accept' and 'accept' are the same header. */
const lowerCaseKeys = headers => Object.fromEntries(Object.entries(headers ?? {}).map(([name, value]) => [name.toLowerCase(), value]))

/** Bodies the browser knows how to send as they are. */
function isRawBody(data) {
    return typeof data === 'string'
        || (typeof FormData !== 'undefined' && data instanceof FormData)
        || (typeof URLSearchParams !== 'undefined' && data instanceof URLSearchParams)
        || (typeof Blob !== 'undefined' && data instanceof Blob)
        || (typeof ArrayBuffer !== 'undefined' && data instanceof ArrayBuffer)
}

/** The body to send and, as a side effect, the right `content-type` in `headers`. */
function encodeBody(data, headers) {
    if (data === undefined || data === null) return undefined
    if (typeof FormData !== 'undefined' && data instanceof FormData) {
        // The browser writes `multipart/form-data; boundary=...` by itself: a hand-made one has no boundary.
        delete headers['content-type']
        return data
    }
    if (isRawBody(data)) return data
    headers['content-type'] ??= 'application/json'
    return JSON.stringify(data)
}

/** Text of a response -> JSON when it is JSON (by content type, or because it is an object or a list), else the text. */
function parseBody(text, contentType) {
    if (text === '') return ''
    if (/json/i.test(contentType ?? '') || /^\s*[[{]/.test(text)) {
        try { return JSON.parse(text) } catch { return text }
    }
    return text
}

// ── Errors with the shape axios gives ───────────────────────────────────────

/** 4xx and 5xx: `error.response.status` and `error.response.data`, like axios. */
function httpError(response, settings) {
    const error = new Error(`Request failed with status code ${response.status}`)
    error.name = 'RequestError'
    error.code = response.status >= 500 ? 'ERR_BAD_RESPONSE' : 'ERR_BAD_REQUEST'
    error.status = response.status
    error.response = response
    error.config = settings
    return error
}

/** No answer at all (offline, DNS, CORS...): axios has no `response` here either. */
function networkError(cause, settings) {
    const error = new Error('Network Error', { cause })
    error.name = 'RequestError'
    error.code = 'ERR_NETWORK'
    error.config = settings
    return error
}

/** The caller aborted it with its AbortController. */
function canceledError(settings) {
    const error = new Error('canceled')
    error.name = 'CanceledError'
    error.code = 'ERR_CANCELED'
    error.config = settings
    return error
}

/** True when `error` is a cancellation, from any client. `client` is checked first for old axios versions. */
export function isCanceled(error, client) {
    return !!(client?.isCancel?.(error) || error?.code === 'ERR_CANCELED')
}

/** The same result and the same failures for fetch and XMLHttpRequest. */
function settle({ status, statusText, text, contentType, headers }, settings) {
    const response = { data: parseBody(text, contentType), status, statusText, headers }
    if (status < 200 || status >= 300) throw httpError(response, settings)
    return response
}

// ── Clients ─────────────────────────────────────────────────────────────────

/** Drop the keys that are undefined, so we never hand a client an explicit `undefined`. */
const compact = object => Object.fromEntries(Object.entries(object).filter(([, value]) => value !== undefined))

/** axios (or anything with its interface): its own methods when it has them, else `request`, else call it. */
function viaClient(client, settings) {
    const { method, url, params, data, headers, signal, onUploadProgress } = settings
    const options = compact({ params, headers, signal, onUploadProgress })

    if (typeof client[method] === 'function') {
        if (!METHODS_WITH_BODY.includes(method)) return client[method](url, options)
        // axios.delete(url, config) takes the body inside the config; post/put/patch take it as an argument.
        return method === 'delete'
            ? client.delete(url, compact({ ...options, data }))
            : client[method](url, data, options)
    }
    const full = compact({ method, url, data, ...options })
    return typeof client.request === 'function' ? client.request(full) : client(full)
}

/** Headers for fetch and XMLHttpRequest: our defaults, the Laravel ones if same-origin, then the caller's. */
function buildHeaders(settings, crossOrigin) {
    return {
        accept: 'application/json, text/plain, */*',
        ...(crossOrigin ? {} : laravelHeaders(settings.method)),
        ...lowerCaseKeys(settings.headers),
    }
}

async function viaFetch(settings, crossOrigin) {
    const headers = buildHeaders(settings, crossOrigin)
    const body = METHODS_WITH_BODY.includes(settings.method) ? encodeBody(settings.data, headers) : undefined

    let response
    try {
        response = await fetch(joinUrl(settings.url, settings.params), compact({
            method: settings.method.toUpperCase(),
            headers,
            body,
            signal: settings.signal,
            // Cookies (the session) go with a same-origin request. Across domains the default, no cookies, is kept.
            credentials: crossOrigin ? undefined : 'same-origin',
        }))
    } catch (cause) {
        throw cause?.name === 'AbortError' ? canceledError(settings) : networkError(cause, settings)
    }

    return settle({
        status: response.status,
        statusText: response.statusText,
        text: await response.text(),
        contentType: response.headers.get('content-type'),
        headers: Object.fromEntries(response.headers.entries()),
    }, settings)
}

/** "name: value\r\n..." (XMLHttpRequest) -> { name: value } */
function parseRawHeaders(raw) {
    const headers = {}
    for (const line of raw.trim().split(/[\r\n]+/)) {
        const separatorAt = line.indexOf(':')
        if (separatorAt > 0) headers[line.slice(0, separatorAt).trim().toLowerCase()] = line.slice(separatorAt + 1).trim()
    }
    return headers
}

/**
 * Same as viaFetch, but with XMLHttpRequest because it is the only browser API that reports how much
 * of the body has been sent. It is used when a caller asks for `onUploadProgress` and there is no axios.
 */
function viaXhr(settings) {
    return new Promise((resolve, reject) => {
        if (settings.signal?.aborted) return reject(canceledError(settings))

        const headers = buildHeaders(settings, false)
        const body = METHODS_WITH_BODY.includes(settings.method) ? encodeBody(settings.data, headers) : undefined
        const xhr = new XMLHttpRequest()
        xhr.open(settings.method.toUpperCase(), joinUrl(settings.url, settings.params), true)
        Object.entries(headers).forEach(([name, value]) => xhr.setRequestHeader(name, value))

        // Same shape axios gives its progress callback: `total` is missing when the size is unknown.
        if (settings.onUploadProgress && xhr.upload) {
            xhr.upload.onprogress = event => settings.onUploadProgress({ loaded: event.loaded, total: event.lengthComputable ? event.total : undefined })
        }
        const abort = () => xhr.abort()
        settings.signal?.addEventListener('abort', abort, { once: true })
        const done = () => settings.signal?.removeEventListener('abort', abort)

        xhr.onload = () => {
            done()
            try {
                resolve(settle({
                    status: xhr.status,
                    statusText: xhr.statusText,
                    text: xhr.responseText ?? '',
                    contentType: xhr.getResponseHeader('content-type'),
                    headers: parseRawHeaders(xhr.getAllResponseHeaders?.() ?? ''),
                }, settings))
            } catch (error) { reject(error) }
        }
        xhr.onerror = () => { done(); reject(networkError(undefined, settings)) }
        xhr.onabort = () => { done(); reject(canceledError(settings)) }
        xhr.send(body)
    })
}

// ── The ladder ──────────────────────────────────────────────────────────────

/**
 * @param {object}   options
 * @param {string}   options.url
 * @param {string}   [options.method='get']   get | post | put | patch | delete
 * @param {object}   [options.params]         query string
 * @param {*}        [options.data]           body: an object (sent as JSON), FormData or a string
 * @param {object}   [options.headers]
 * @param {AbortSignal} [options.signal]
 * @param {Function} [options.onUploadProgress]  ({ loaded, total }) => void
 * @param {object}   [deps]
 * @param {object}   [deps.axios]             the client the app injected
 * @returns {Promise<{ data: *, status: number }>}
 */
export async function request(options, deps = {}) {
    const settings = { ...options, method: String(options.method ?? 'get').toLowerCase() }
    const crossOrigin = isCrossOrigin(settings.url)

    // Another domain: plain fetch, no matter what clients exist (see the top of this file).
    if (crossOrigin) return viaFetch(settings, true)

    const client = deps.axios ?? globalThis.window?.axios
    if (client) return viaClient(client, settings)

    // No axios. fetch cannot report upload progress, XMLHttpRequest can.
    if (settings.onUploadProgress && typeof XMLHttpRequest !== 'undefined') return viaXhr(settings)
    return viaFetch(settings, false)
}

// What a component that SENDS something (an upload, an import) does with the answer. Plain text with a 200 is still a
// success, as in 2.4.0 (a server that answers "OK" or redirects back() to a page). Only an answer that looks like an HTML
// PAGE (it starts with `<`: the login page that an expired session redirects to) is a failure.
export const rejectHtmlPage = data => {
    if (typeof data === 'string' && data.trimStart().startsWith('<')) throw new Error('The server sent an HTML page (an expired session redirected to the login page?)')
    return data
}

// What a component that loads JSON (select options, table rows, a resource) does with the body of the answer.
// When the session has expired the server redirects to the login page; axios and fetch follow the redirect and hand
// over its HTML as a normal 200 answer, which read as data looks like an empty list. So a text body is an error: the
// component shows its "could not load" message with Retry. An empty body (a 204) is not text: it still means "nothing".
export const expectJson = data => {
    if (typeof data === 'string' && data !== '') throw new Error('Expected JSON but the server sent text (an expired session redirected to the login page?)')
    return data
}
