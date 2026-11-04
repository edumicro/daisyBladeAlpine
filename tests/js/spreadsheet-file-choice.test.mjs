import test from 'node:test'
import assert from 'node:assert/strict'
import { dblSpreadsheetImport } from '../../resources/js/daisyblade.js'

const namedFile = name => Object.assign(new Blob(['x']), { name })

test('choosing a file empties the input, so the same file can be chosen again after an error or Cancel', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import' })
    const input = { files: [new Blob(['a'])], value: 'C:\\fakepath\\a.csv' }

    importer.handleFile({ target: input })

    assert.equal(input.value, '')
    assert.equal(importer.step, 'confirm')   // the file was taken before the input was emptied
})

test('a dropped file of another type than `accept` is refused, with a message', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import', accept: '.xlsx,.xls,.csv', messages: { wrongType: 'Not accepted' } })

    importer.handleDrop({ dataTransfer: { files: [namedFile('photo.png')] } })

    assert.equal(importer.step, 'upload')
    assert.equal(importer.refusal, 'Not accepted')
})

test('a dropped file of an accepted type goes to the confirmation, whatever the case of its extension', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import', accept: '.xlsx,.xls,.csv' })

    importer.handleDrop({ dataTransfer: { files: [namedFile('DATA.CSV')] } })

    assert.equal(importer.step, 'confirm')
    assert.equal(importer.refusal, '')
})

test('accept also understands MIME types and families', () => {
    const importer = dblSpreadsheetImport({ uploadUrl: '/import', accept: 'text/csv,image/*' })

    assert.equal(importer.accepts({ name: 'a', type: 'text/csv' }), true)
    assert.equal(importer.accepts({ name: 'a', type: 'image/png' }), true)
    assert.equal(importer.accepts({ name: 'a', type: 'application/pdf' }), false)
})

test('without accept anything goes', () => {
    assert.equal(dblSpreadsheetImport({ uploadUrl: '/import' }).accepts(namedFile('a.png')), true)
})

// ── The file dialog filters by `accept` too ────────────────────────────────

test('a file chosen in the dialog that is not of an accepted type is refused, like a dropped one', () => {
    const importer = dblSpreadsheetImport({ accept: '.csv,.xlsx', messages: { wrongType: 'Tipo no admitido' } })
    const input = { files: [{ name: 'photo.png', type: 'image/png' }], value: 'C:\\photo.png' }

    importer.handleFile({ target: input })

    assert.equal(importer.step, 'upload')
    assert.equal(importer.refusal, 'Tipo no admitido')
    assert.equal(importer.file, null)
    assert.equal(input.value, '')   // emptied anyway, so the same path can be chosen again
})

test('a file chosen in the dialog that is accepted goes to the confirmation', () => {
    const importer = dblSpreadsheetImport({ accept: '.csv,.xlsx' })

    importer.handleFile({ target: { files: [{ name: 'DATA.CSV', type: 'text/csv' }], value: 'x' } })

    assert.equal(importer.step, 'confirm')
})
