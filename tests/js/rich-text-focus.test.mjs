import test from 'node:test'
import assert from 'node:assert/strict'
import { dblRichText } from '../../resources/js/components/rich-text.js'

// The label of form.rich-text cannot use `for` (the editor is a <div>), so it calls focus() on the component.

/** Tiptap's Editor, as far as dblRichText.init() and focus() touch it. */
function fakeEditorDeps(calls) {
    class Editor {
        constructor(options) {
            this.options = options
            this.isEmpty = true
            this.commands = { focus: () => calls.push('focus') }
        }
        getText() { return '' }
        getHTML() { return '' }
        destroy() { calls.push('destroy') }
    }
    return { Editor, StarterKit: { configure: () => ({}) } }
}

const richTextWith = deps => Object.assign(dblRichText({ name: 'body' }, deps), { $refs: { editor: {}, input: { value: '' }, file: {} }, $root: { closest: () => null }, $nextTick: () => {}, $watch() {} })

test('focus() puts the cursor in the editor', () => {
    const calls = []
    const richText = richTextWith(fakeEditorDeps(calls))
    richText.init()

    richText.focus()

    assert.deepEqual(calls, ['focus'])
})

test('focus() before the editor exists, or after it is destroyed, does nothing', () => {
    const calls = []
    const richText = richTextWith(fakeEditorDeps(calls))

    richText.focus()                 // no editor yet
    richText.init()
    richText.destroy()
    richText.focus()                 // no editor any more

    assert.deepEqual(calls, ['destroy'])
})
