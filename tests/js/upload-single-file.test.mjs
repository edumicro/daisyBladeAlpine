import test from 'node:test'
import assert from 'node:assert/strict'
import { dblUpload } from '../../resources/js/components/upload.js'

// A field of ONE file replaces the saved file only when the new one arrives and is accepted. Before, it discarded the saved one first:
// dropping a text, or a file of the wrong type, left the form without the id it had.

const pdf = name => ({ name, size: 10, type: 'application/pdf' })

function singleFileField(config = {}) {
    const uploads = []
    const upload = Object.assign(dblUpload({ uploadUrl: '/u', accept: '.pdf', maxSize: 1, existing: [{ id: 7, name: 'saved.pdf' }], messages: { bad_type: 'wrong type' }, ...config }), {
        $dispatch() {},
        $watch() {},    // the announcement of the field (modelBinding.js) is not the subject here
        send: async item => { uploads.push(item.name) },    // the network is not the subject here
    })
    upload.init()
    return { upload, uploads }
}

test('dropping something that is not a file (text, a link) changes nothing', () => {
    const { upload } = singleFileField()

    upload.add([])

    assert.deepEqual(upload.ids, [7])
    assert.equal(upload.items.length, 1)
})

test('a rejected file (wrong type) leaves the saved one, and shows its own error', () => {
    const { upload } = singleFileField()

    upload.add([{ name: 'photo.png', size: 10, type: 'image/png' }])

    assert.deepEqual(upload.ids, [7])
    assert.deepEqual(upload.items.map(item => item.status), ['done', 'error'])
    assert.equal(upload.items[1].error, 'wrong type')
})

test('a file that is too big is rejected the same way', () => {
    const { upload } = singleFileField()

    upload.add([{ name: 'big.pdf', size: 5 * 1048576, type: 'application/pdf' }])

    assert.deepEqual(upload.ids, [7])
})

test('an accepted file replaces the saved one, and the errors of before go away too', () => {
    const { upload, uploads } = singleFileField()
    upload.add([{ name: 'photo.png', size: 10, type: 'image/png' }])   // an error stays beside the saved file

    upload.add([pdf('new.pdf')])

    assert.deepEqual(upload.items.map(item => item.name), ['new.pdf'])
    assert.deepEqual(uploads, ['new.pdf'])
})

test('only the first of several dropped files is taken', () => {
    const { upload, uploads } = singleFileField()

    upload.add([pdf('one.pdf'), pdf('two.pdf')])

    assert.deepEqual(uploads, ['one.pdf'])
})

test('a field of many files still adds each file to the list, and counts the maximum', () => {
    const { upload } = singleFileField({ multiple: true, maxFiles: 2, messages: { too_many: 'too many' } })

    upload.add([pdf('a.pdf'), pdf('b.pdf')])   // the saved one is the first of the two allowed

    assert.deepEqual(upload.items.map(item => item.name), ['saved.pdf', 'a.pdf', 'b.pdf'])
    assert.equal(upload.items[2].error, 'too many')
})
