import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dbSpreadsheetImport } from '../../resources/js/daisyblade.js'
import { dbUpload } from '../../resources/js/components/upload.js'

// 2.4.0 took a plain-text 200 as success (a server that answers "OK" or redirects back() to a page); the rows WERE
// imported and the file DID arrive. Only an answer that looks like an HTML page (the login page) is a failure
// (see html-answer-is-failure.test.mjs).
beforeEach(() => {
    console.error = () => {}
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.document = { cookie: '', querySelector: () => null }
    globalThis.window = { axios: { post: async () => ({ data: 'OK', status: 200 }) } }
})
afterEach(() => { for (const n of ['window', 'location', 'document']) delete globalThis[n] })

test('spreadsheet: a plain-text 200 stays "done" (the rows were imported)', async () => {
    const importer = dbSpreadsheetImport({ uploadUrl: '/import' })
    importer.file = new Blob(['a'])
    await importer.upload()
    assert.equal(importer.step, 'done')
})

test('upload: a plain-text 200 stays "done"', async () => {
    const upload = Object.assign(dbUpload({ uploadUrl: '/upload' }), { $dispatch() {} })
    const item = upload.makeItem({ name: 'a.pdf', size: 3, file: new Blob(['abc']), status: 'uploading' })
    await upload.send(item)
    assert.equal(item.status, 'done')
})
