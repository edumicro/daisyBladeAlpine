import test, { before, after } from 'node:test'
import assert from 'node:assert/strict'
import http from 'node:http'
import axios from 'axios'
import { request } from '../../resources/js/http.js'

// The contract against the REAL axios and a REAL HTTP server (the other tests use a fake axios).
let server, base
const routes = {
    '/fragment': [200, 'text/html; charset=UTF-8', '<p>Hola</p>'],
    '/empty-html': [200, 'text/html; charset=UTF-8', ''],
    '/json': [200, 'application/json', '{"data":[{"value":1,"label":"A"}]}'],
    '/json-422-for-html': [422, 'application/json', '{"message":"Nope","errors":{"a":["x"]}}'],
    '/login-with-comment': [200, 'text/html; charset=UTF-8', '<!-- cached 12:00 --><!DOCTYPE html><html><body>login</body></html>'],
    '/login-with-bom': [200, 'text/html; charset=UTF-8', '﻿<!DOCTYPE html><html><body>login</body></html>'],
    '/warning-then-json': [200, 'application/json', 'Warning: Undefined variable\n{"data":[]}'],
    '/json-as-html-type': [200, 'text/html', '{"data":[]}'],
    '/null': [200, 'application/json', 'null'],
}
before(async () => {
    server = http.createServer((req, res) => {
        const [status, type, body] = routes[req.url.split('?')[0]] ?? [404, 'text/plain', 'no']
        res.writeHead(status, { 'content-type': type }); res.end(body)
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    base = `http://127.0.0.1:${server.address().port}`
    globalThis.location = { href: `${base}/page`, origin: base }
})
after(() => { server.close(); delete globalThis.location })
const quiet = async fn => { const e = console.error; console.error = () => {}; try { return await fn() } finally { console.error = e } }

test('axios real: a fragment arrives as text', async () => {
    const { data } = await request({ url: `${base}/fragment`, answer: 'html' }, { axios })
    assert.equal(data, '<p>Hola</p>')
})

test('axios real: an HTML page that opens with a comment is a page, not a fragment', async () => {
    await assert.rejects(quiet(() => request({ url: `${base}/login-with-comment`, answer: 'html' }, { axios })), { code: 'ERR_CONTRACT' })
})

test('fetch: an HTML page that opens with a comment is a page, not a fragment', async () => {
    await assert.rejects(quiet(() => request({ url: `${base}/login-with-comment`, answer: 'html' }, {})), { code: 'ERR_CONTRACT' })
})

test('fetch: an HTML page that opens with a BOM is a page, not a fragment', async () => {
    await assert.rejects(quiet(() => request({ url: `${base}/login-with-bom`, answer: 'html' }, {})), { code: 'ERR_CONTRACT' })
})

test('a lazy tab whose fragment is empty (200, text/html, no body) is an empty tab, not a contract error', async () => {
    for (const client of [{ axios }, {}]) {
        const outcome = await quiet(() => request({ url: `${base}/empty-html`, answer: 'html' }, client).then(r => r.data, e => e.code))
        assert.equal(outcome, '')
    }
})

test('axios real and fetch agree on every route', async () => {
    const differences = []
    for (const route of Object.keys(routes)) for (const answer of ['json', 'json-or-empty', 'html']) {
        const outcome = client => request({ url: `${base}${route}`, answer }, client).then(r => `ok:${JSON.stringify(r.data)}`, e => `${e.code}:${e.reason ?? e.status}`)
        const [viaAxios, viaFetch] = await quiet(() => Promise.all([outcome({ axios }), outcome({})]))
        if (viaAxios !== viaFetch) differences.push(`${route} [${answer}] axios=${viaAxios} fetch=${viaFetch}`)
    }
    assert.deepEqual(differences, [])
})
