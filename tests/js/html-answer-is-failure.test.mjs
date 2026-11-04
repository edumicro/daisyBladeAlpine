import test, { beforeEach, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { dblSpreadsheetImport } from '../../resources/js/daisyblade.js'
import { dblUpload } from '../../resources/js/components/upload.js'
import { dblRichText } from '../../resources/js/components/rich-text.js'

// A text answer (the login page after an expired session: the server redirects and axios/fetch hand over its HTML as a
// normal 200) is a failure for select, table, details, auto-form and wizard. For the spreadsheet import, the upload and
// the rich-text image it was taken as a success: "Import complete", a file "uploaded" with an undefined id, an image
// without `src`.
const LOGIN_PAGE = '<!DOCTYPE html><html><body><form>login</form></body></html>'
const realConsoleError = console.error
beforeEach(() => {
    console.error = () => {}
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    globalThis.document = { cookie: '', querySelector: () => null }
    globalThis.window = { axios: { post: async () => ({ data: LOGIN_PAGE, status: 200 }) } }
})
afterEach(() => { console.error = realConsoleError; for (const n of ['window', 'location', 'document']) delete globalThis[n] })

test('spreadsheet import: an HTML answer to the upload is a failure, not "Import complete"', async () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import', messages: { uploadFailed: 'Fallo' } })
    importer.file = new Blob(['a'])

    await importer.upload()

    assert.equal(importer.step, 'error')
})

test('upload: an HTML answer to a file is an error, and the file does not get an id', async () => {
    const upload = Object.assign(dblUpload({ uploadUrl: '/upload', messages: { failed: 'Falló' } }), { $dispatch() {} })
    const item = upload.makeItem({ name: 'a.pdf', size: 3, file: new Blob(['abc']), status: 'uploading' })

    await upload.send(item)

    assert.equal(item.status, 'error')
    assert.deepEqual(upload.ids.map(String), [])   // not [undefined]: the form would post files[]=
})

test('rich-text: an HTML answer to an image upload inserts nothing and shows the error', async () => {
    const inserted = []
    const chain = { focus: () => chain, setImage: attrs => { inserted.push(attrs); return chain }, run() {} }
    class Editor {
        constructor() { this.isEmpty = true }
        getText() { return '' } getHTML() { return '' } chain() { return chain } destroy() {}
    }
    const richText = Object.assign(
        dblRichText({ name: 'b', uploadUrl: '/img', labels: { uploadFailed: 'Fallo' } }, { Editor, StarterKit: { configure: () => ({}) } }),
        { $refs: { editor: {}, input: { value: '' }, file: {} }, $dispatch() {}, $root: { closest: () => null }, $nextTick: () => {}, $watch() {} },
    )
    richText.init()

    await richText.upload({ target: { files: [new Blob(['x'], { type: 'image/png' })], value: 'x' } })

    assert.deepEqual(inserted, [])
    assert.equal(richText.error, 'Fallo')
})
