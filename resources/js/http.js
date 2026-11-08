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
//
// THE CONTRACT WITH THE SERVER (the whole of it lives in this file; the forms of the answers are in docs/contrato.md):
//
//   - Every request asks for JSON: `Accept: application/json` and, on the same domain, `X-Requested-With`. With them Laravel
//     answers by itself a 401 / 419 / 422 in JSON instead of redirecting to the login page.
//   - The contract looks at WHAT ARRIVED, never at where it came from: no URL is compared, so a `baseURL`, a `<base href>`
//     or a redirect that ends in valid JSON cannot confuse it. An answer is good when its `Content-Type` is the one the
//     component asks for (JSON, or `text/html` in the lazy tabs) AND its form is the expected one (see `answerRules`).
//     A login page that a session expired sends to a request for JSON is HTML, so it fails by its type.
//   - Anything else is a CONTRACT ERROR (`ContractError`, `isContractError()`), with a clear warning in the console for the
//     developer. It is never taken as data: a login page is not an empty list.
//   - An empty answer (a 204) only counts where the component says so with `answer: 'json-or-empty'`.
//   - The FORM of the JSON is checked by one function per component, in `answerShapes` at the end of this file.

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

/**
 * Text of a response -> JSON when it is JSON (by content type, or because it is an object or a list), else the text.
 * An answer that the component wants as HTML (`keepAsText`) is never parsed: a fragment such as "[Draft] <b>x</b>" is text.
 */
function parseBody(text, contentType, keepAsText) {
    if (text === '' || keepAsText) return text
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

/**
 * The server did not keep the contract (see the top of this file): its answer is not of the type and form the component
 * needs. `reason` says which one (not-json | empty | not-html | shape). The developer gets a warning in the
 * console that names the endpoint, because the user only sees a generic "could not load".
 */
function contractError(reason, settings, detail = '') {
    const causes = {
        'not-json': 'did not answer JSON (an expired session answering with the login page? a controller that does back()?)',
        empty: 'answered with an empty body (a 204) and this component does not accept that',
        'not-html': 'did not answer an HTML fragment',
        shape: detail,
    }
    const error = new Error(`DaisyBlade: the endpoint ${settings.url} ${causes[reason]}. See docs/contrato.md`)
    error.name = 'ContractError'
    error.code = 'ERR_CONTRACT'
    error.reason = reason
    error.config = settings
    console.error(error.message)
    return error
}

/** True when `error` is a failure of the contract with the server (see `contractError`). */
export const isContractError = error => error?.code === 'ERR_CONTRACT'

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

// ── The contract: type and form of the answer ──────────────────────────────

/** `application/json` and its relatives (`application/problem+json`, `application/vnd.api+json`), with or without `; charset`. */
const isJsonType = type => /^application\/([\w.-]+\+)?json\b/i.test(type)

/** `text/html`, with or without `; charset`. */
const isHtmlType = type => /^text\/html\b/i.test(type)

const isObjectOrList = data => data !== null && typeof data === 'object'

/**
 * What a page can start with before it says what it is: a byte order mark (U+FEFF), blanks and HTML comments (`<!-- cached at 12:00 -->`).
 * They are skipped before looking at the first tag, or a login page that opens with a comment would pass as a fragment.
 */
const BEFORE_THE_FIRST_TAG = /^(?:\uFEFF|\s|<!--[\s\S]*?-->)*/

/** A fragment of HTML is a piece of a page: a whole document (`<!doctype>`, `<html>`) is a page, such as the login one. */
const isHtmlFragment = data => typeof data === 'string' && !/^<(!doctype|html)/i.test(data.replace(BEFORE_THE_FIRST_TAG, ''))

/**
 * Pattern "table of rules": what each kind of `answer` accepts, written once and chosen by name, so that checking an
 * answer is the same sequence of steps for all of them (emptiness, type, form) with no branch per kind.
 *
 *   isRightType   the `Content-Type` that the component asks for
 *   isRightForm   the form of the body (a JSON object or list; a piece of HTML)
 *   emptyReason   what an empty body (a 204) is: a ContractError of that reason, or null when the component accepts it
 *   emptyData     what an accepted empty body reads as: an object without fields (JSON), or an empty text (an empty tab)
 *   failure       the reason of the ContractError when the type or the form is wrong
 */
const answerRules = {
    json: { isRightType: isJsonType, isRightForm: isObjectOrList, emptyReason: 'empty', emptyData: {}, failure: 'not-json' },
    'json-or-empty': { isRightType: isJsonType, isRightForm: isObjectOrList, emptyReason: null, emptyData: {}, failure: 'not-json' },
    // A tab with nothing in it is a valid tab: an empty fragment is still a fragment (what is not one is a page, such as the login).
    html: { isRightType: isHtmlType, isRightForm: isHtmlFragment, emptyReason: null, emptyData: '', failure: 'not-html' },
}

/**
 * The contract (see the top of this file) applied to a 2xx answer, whichever client brought it.
 * `contentType` is what the client knows about the type: a text, or `undefined` for a client that does not say it (an
 * injected axios without headers), and then the form of the body is the only judge. Returns the response, with `data` ready
 * for `answerShapes`.
 */
function acceptAnswer(response, settings, contentType) {
    const rule = answerRules[settings.answer ?? 'json']
    const { data } = response

    if (data === '' || data === undefined || data === null) {
        if (rule.emptyReason) throw contractError(rule.emptyReason, settings)
        // An accepted empty answer reads as an empty object (or an empty text), so the callers never test for null.
        return { ...response, data: structuredClone(rule.emptyData) }
    }
    const typeIsWrong = contentType !== undefined && !rule.isRightType(contentType)
    if (typeIsWrong || !rule.isRightForm(data)) throw contractError(rule.failure, settings)
    return response
}

/** The same result and the same failures for fetch and XMLHttpRequest. */
function settle({ status, statusText, text, contentType, headers }, settings) {
    const response = { data: parseBody(text, contentType, settings.answer === 'html'), status, statusText, headers }
    if (status < 200 || status >= 300) throw httpError(response, settings)
    return acceptAnswer(response, settings, contentType ?? '')
}

// ── Clients ─────────────────────────────────────────────────────────────────

/** Drop the keys that are undefined, so we never hand a client an explicit `undefined`. */
const compact = object => Object.fromEntries(Object.entries(object).filter(([, value]) => value !== undefined))

/** axios (or anything with its interface): its own methods when it has them, else `request`, else call it. */
async function viaClient(client, settings) {
    const { method, url, params, data, signal, onUploadProgress } = settings
    // axios already adds `X-Requested-With` in a Laravel app, but a client injected by hand may not.
    const headers = buildHeaders(settings, false)
    // An HTML fragment must reach us as text: axios would try to read "[1] item" as JSON.
    const responseType = settings.answer === 'html' ? 'text' : undefined
    const options = compact({ params, headers, signal, onUploadProgress, responseType })

    let response
    if (typeof client[method] === 'function') {
        if (!METHODS_WITH_BODY.includes(method)) response = await client[method](url, options)
        // axios.delete(url, config) takes the body inside the config; post/put/patch take it as an argument.
        else if (method === 'delete') response = await client.delete(url, compact({ ...options, data }))
        else response = await client[method](url, data, options)
    } else {
        const full = compact({ method, url, data, ...options })
        response = await (typeof client.request === 'function' ? client.request(full) : client(full))
    }
    return acceptAnswer(response, settings, headerOf(response?.headers, 'content-type'))
}

/** A header of an axios answer (`AxiosHeaders` has `get`, a plain object has the lower-case name), or undefined. */
const headerOf = (headers, name) => headers?.get?.(name) ?? headers?.[name]

/** Headers for every client: the contract's, the Laravel ones if same-origin, then the caller's. */
function buildHeaders(settings, crossOrigin) {
    return {
        accept: settings.answer === 'html' ? 'text/html, */*' : 'application/json',
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
 * @param {'json'|'json-or-empty'|'html'} [options.answer='json']  what the component accepts as an answer: JSON (the default),
 *                                        JSON or an empty body (a 204), or an HTML fragment (lazy tabs)
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

// ── Latest only (cause 6: an old answer must never overwrite a newer one) ───

/**
 * Pattern "latest only". A component that loads the same thing again and again (the next page of a table, the next month of
 * a calendar, the options for a new value of another control) makes one of these and sends every request through `run()`.
 * Only the NEWEST request counts: the answer AND the error of an older one are thrown away, so a slow request that finishes
 * late can neither paint old data over new data nor show an error for something the user already left.
 *
 *     const latest = latestOnly()
 *     ...
 *     const outcome = await latest.run(() => request({ url }))
 *     if (outcome.stale) return          // a newer load took over: it owns `loading`, the rows and the error
 *     if (outcome.error) { ...show the error... } else { ...use outcome.value... }
 *
 * `run()` never throws: the three possible results are `{ stale: true }`, `{ stale: false, value }` and `{ stale: false, error }`.
 * `cancel()` makes whatever is in flight old without starting another request (the calendar uses it when the new month is cached).
 */
export function latestOnly() {
    let newestTicket = 0    // number of the newest request; a request is old when its own number is smaller

    return {
        async run(task) {
            const myTicket = ++newestTicket
            try {
                const value = await task()
                return myTicket === newestTicket ? { stale: false, value } : { stale: true }
            } catch (error) {
                return myTicket === newestTicket ? { stale: false, error } : { stale: true }
            }
        },
        cancel() { newestTicket++ },
    }
}

// ── The form of each answer (one function per component; docs/contrato.md explains them) ──────

const isObject = value => value !== null && typeof value === 'object' && !Array.isArray(value)

/** If the answer is not right: log what the component expects (for the developer) and fail with a ContractError. */
function expectShape(url, component, expected, isRight) {
    if (isRight) return
    throw contractError('shape', { url }, `answered something that ${component} cannot read: it expects ${expected}`)
}

// The forms that an answer may have are exactly the ones that Laravel produces, and nothing else is guessed:
//
//   a list    an array                                    return $rows;
//             a resource collection {data, meta, links}   return UserResource::collection($users);
//             a paginator as it is {data, current_page}   return User::paginate();   (also simplePaginate() and cursorPaginate())
//             any of them with extra keys at the root     return UserResource::collection($users)->additional(['ok' => true]);
//   a detail  a resource {data: {...}}                    return new UserResource($user);
//             the object as it is                         return $user;
//
// THE ONE RULE OF THE DETAIL: an object that has `data` and it is an object is a `JsonResource`, and it is unwrapped, always
// (with `additional()` or `with()` too). A model that has a column called `data` must go through a `JsonResource`, so the
// body is `{ data: { …, data: … } }` and only the outer one is removed. docs/contrato.md explains it with an example.
//
// tests/Feature/LaravelAnswerShapesTest.php makes these answers with the real classes of Laravel and reads them with this file.

/** What `paginate()`, `simplePaginate()` and `cursorPaginate()` return as they are: the position of the page in the root, without a `meta`. */
const isBarePaginator = value => isObject(value) && ('current_page' in value || 'next_cursor' in value || 'prev_cursor' in value)

/** A list: an array, or an object whose `data` is one (a resource collection, a paginator, with or without `additional()`). */
function listOf(data) {
    if (Array.isArray(data)) return data
    return Array.isArray(data?.data) ? data.data : undefined
}

/**
 * Where Laravel writes the position of a page, in ONE flat object, whichever way the answer came:
 *   - a resource collection: `meta` (`current_page`, `last_page`, `next_cursor`…) and `links` (`prev`, `next`);
 *   - a paginator as it is: all of it in the root of the body (`current_page`, `next_page_url`, `next_cursor`…).
 */
function pagePositionOf(data) {
    if (!isObject(data.meta)) return data
    const links = isObject(data.links) ? data.links : {}
    return { ...data.meta, prev_page_url: links.prev, next_page_url: links.next }
}

/**
 * Pattern "table of rules", for the three paginators of Laravel. Each reader says when it applies (`reads`) and where the
 * previous and the next page are (`previous`, `next`): a target `{ page }` or `{ cursor }` for the next request, or null when
 * there is no such page. They are tried in order and the last one always applies, so `pagerOf` has no branch of its own.
 */
const pagerReaders = [
    { // cursorPaginate(): the position is a cursor, never a number
        reads: position => 'next_cursor' in position || 'prev_cursor' in position,
        previous: position => position.prev_cursor ? { cursor: position.prev_cursor } : null,
        next: position => position.next_cursor ? { cursor: position.next_cursor } : null,
    },
    { // paginate(): the totals are known, so the last page is known
        reads: position => typeof position.last_page === 'number',
        previous: position => position.current_page > 1 ? { page: position.current_page - 1 } : null,
        next: position => position.current_page < position.last_page ? { page: position.current_page + 1 } : null,
    },
    { // simplePaginate(): no totals; Laravel only says whether a previous and a next page exist
        reads: position => typeof position.current_page === 'number',
        previous: position => position.prev_page_url ? { page: position.current_page - 1 } : null,
        next: position => position.next_page_url ? { page: position.current_page + 1 } : null,
    },
    { // not paginated: there is nowhere to go
        reads: () => true,
        previous: () => null,
        next: () => null,
    },
]

/** `{ previous, next }` of an answer: where the arrows of a table lead to (each one a target or null). */
function pagerOf(data) {
    const position = pagePositionOf(data)
    const reader = pagerReaders.find(candidate => candidate.reads(position))
    return { previous: reader.previous(position), next: reader.next(position) }
}

/** One row of a search answer as `{ id, text, row }`, or undefined when it is neither `{ id, text }` nor `{ value, label }`. */
function searchRowOf(row, textField = 'text') {
    if (!isObject(row)) return undefined
    if ('id' in row && textField in row) return { id: row.id, text: String(row[textField]), row }
    if ('value' in row && 'label' in row) return { id: row.value, text: String(row.label), row }
    return undefined
}

const OPTION_FORMS = 'a { "value": "label" } map or a list of { "id": …, "text": … } / { "value": …, "label": … } rows (anything wrapped or foreign: use `process-results`, e.g. process-results="response.results")'

/** A `{ value: label }` map: every value a text or a number, as in a <select>. (A body such as `{ "ok": true }` is not one.) */
const isOptionMap = data => isObject(data) && Object.values(data).every(label => ['string', 'number'].includes(typeof label))

/**
 * The options of an answer as `[{ id, text, row }]`, or undefined when it has none of the forms of `answerShapes.searchResults`:
 * a list of rows, or a map. There is no wrapper (`results`, `data`): an object with a list inside has values that are not text, so it is
 * not a map, and it comes out as undefined (an answer that is not understood), never as an empty list.
 */
function optionRowsOf(data, textField) {
    if (Array.isArray(data)) {
        const rows = data.map(row => searchRowOf(row, textField))
        return rows.every(Boolean) ? rows : undefined
    }
    if (!isOptionMap(data)) return undefined
    return Object.entries(data).map(([id, text]) => ({ id, text: String(text), row: { id, text } }))
}

/**
 * What every component that reads JSON expects from the server. Each function receives the body of a good answer (and the URL,
 * only to name it in the warning), throws a ContractError if its form is wrong, and returns the part the component uses.
 */
export const answerShapes = {
    /**
     * select with load-url: the options in the vocabulary of a <select> -> `[{ value, label }]`. The forms are the ones of `searchResults`
     * (below) and the same for both ways of getting options from a URL: a `{ "value": "label" }` map, or a list of rows `{ id, text }`
     * or `{ value, label }`. No wrapper.
     */
    selectOptions(data, url) {
        const rows = optionRowsOf(data)
        expectShape(url, 'the remote select', OPTION_FORMS, rows !== undefined)
        return rows.map(({ id, text, row }) => ({ ...row, value: id, label: text }))
    },
    /**
     * Searchable select with `search-url`: the server's answer to "what matches what was typed?" -> a list of `{ id, text, row }`.
     * The vocabulary is that of a <select>, and the same as in `selectOptions`, in two forms and NO wrapper:
     *   { "12": "Paracetamol" }                        a map value -> label (Laravel's `pluck('name', 'id')`)
     *   [{ "id": 12, "text": "…" }]                    a list of rows (also `{ "value", "label" }`): what `process-results` returns
     * `textField` is the field that holds the label when it is not `text` (`option-label`). `row` is the whole row as the server sent it
     * (a laboratory, a photo...); a map has no more than `{ id, text }`.
     * Nothing else is guessed: any other shape is a ContractError (the user sees the error message with Retry), and
     * `process-results` is the way to read anything wrapped or foreign (`process-results="response.results"`, `"response.data"`...).
     * An object whose values are not all text or numbers is NOT a map: it is an answer that is not understood, never "zero results".
     */
    searchResults(data, url, textField = 'text') {
        const rows = optionRowsOf(data, textField)
        expectShape(url, 'the search of the select', OPTION_FORMS, rows !== undefined)
        return rows
    },
    /**
     * data-table: a resource collection `{ data, meta, links }` or a paginator as it is (`paginate()`, `simplePaginate()`,
     * `cursorPaginate()`), with or without `additional()` -> { rows, meta, pager }.
     * The `meta` of a paginator as it is is the paginator itself (that is where Laravel writes `last_page`, `total`…).
     * `pager` is where the arrows lead to: `{ previous, next }`, each one `{ page }`, `{ cursor }` or null.
     */
    tableRows(data, url) {
        const rows = listOf(data)
        expectShape(url, 'the data table', 'a resource collection { "data": […], "meta": {…} } or a paginator { "data": […], "current_page": 1, … }',
            Array.isArray(rows) && !Array.isArray(data))
        return { rows, meta: isObject(data.meta) ? data.meta : (isBarePaginator(data) ? data : {}), pager: pagerOf(data) }
    },
    /** resource-details: a resource `{ data: {…} }` (also with `additional()`) -> its `data`; any other object is the resource as it is. */
    resource(data, url) {
        expectShape(url, 'the resource details', '{ …fields } or { "data": { …fields } }', isObject(data))
        return isObject(data.data) ? data.data : data
    },
    /** calendar: `[{ id, title, start, … }]` or `{ data: [ … ] }` -> the list. */
    calendarEvents(data, url) {
        const events = listOf(data)
        expectShape(url, 'the calendar', '[{ "id": …, "title": …, "start": "YYYY-MM-DD" }] or { "data": [ … ] }',
            Array.isArray(events) && events.every(event => isObject(event) && 'title' in event && typeof event.start === 'string'))
        return events
    },
    /** map layer: a GeoJSON FeatureCollection -> itself. */
    geoJsonLayer(data, url) {
        expectShape(url, 'the map', 'a GeoJSON FeatureCollection { "type": "FeatureCollection", "features": [ … ] }',
            isObject(data) && Array.isArray(data.features))
        return data
    },
    /** map geocoder: `{ data: [{ lat, lng, … }] }` -> the list. */
    geocoderResults(data, url) {
        expectShape(url, 'the map search', '{ "data": [{ "lat": …, "lng": …, "label": … }] }',
            isObject(data) && Array.isArray(data.data) && data.data.every(place => isObject(place) && 'lat' in place && 'lng' in place))
        return data.data
    },
    /** upload: `{ id, name?, url?, thumb?, size? }` -> itself. The form sends the `id`, so a file without one is not uploaded. */
    uploadedFile(data, url) {
        expectShape(url, 'the upload', '{ "id": …, "name": …, "url": … }', isObject(data) && data.id !== undefined && data.id !== null)
        return data
    },
    /** rich-text image upload: `{ url }` -> itself. */
    uploadedImage(data, url) {
        expectShape(url, 'the rich text editor', '{ "url": "https://…/image.jpg" }', isObject(data) && typeof data.url === 'string' && data.url !== '')
        return data
    },
    /** auto-form and wizard: any JSON object (`{ redirect? }`); an empty answer arrives here as `{}`. */
    formAnswer(data, url) {
        expectShape(url, 'the form', 'a JSON object, such as { "redirect": "/users" }', isObject(data))
        return data
    },
    /** spreadsheet import: a JSON object (`{ imported? }`). */
    importResult(data, url) {
        expectShape(url, 'the import', 'a JSON object, such as { "imported": 120 }', isObject(data))
        return data
    },
}
