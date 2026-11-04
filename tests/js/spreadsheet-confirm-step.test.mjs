import test from 'node:test'
import assert from 'node:assert/strict'
import { dblSpreadsheetImport } from '../../resources/js/daisyblade.js'

// The import used to have a "preview" step that promised the rows of the file and always showed "0 rows detected" and an
// empty table: the browser never read the file (an .xlsx is a zip of XML; reading it needs a library the package does not
// ship). The step is now a confirmation with the name and size of the file, which is all the browser knows.

test('choosing a file goes to the confirmation step with that file, without reading it', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })
    const file = new Blob(['a;b\n1;2'])
    file.name = 'products.csv'

    importer.handleFile({ target: { files: [file] } })

    assert.equal(importer.step, 'confirm')
    assert.equal(importer.file, file)
})

test('dropping a file does the same as choosing it', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })

    importer.handleDrop({ dataTransfer: { files: [new Blob(['x'])] } })

    assert.equal(importer.step, 'confirm')
})

test('cancelling the file dialog (no file) stays on the first step', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })

    importer.handleFile({ target: { files: [] } })

    assert.equal(importer.step, 'upload')
    assert.equal(importer.file, null)
})

test('the size is written in KB or MB', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })

    importer.file = { size: 100 }
    assert.equal(importer.fileSizeText, '1 KB')
    importer.file = { size: 12 * 1024 }
    assert.equal(importer.fileSizeText, '12 KB')
    importer.file = { size: 1.5 * 1024 * 1024 }
    assert.equal(importer.fileSizeText, '1.5 MB')
})
