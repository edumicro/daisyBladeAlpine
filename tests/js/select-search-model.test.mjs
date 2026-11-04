import test from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectSearch } from '../../resources/js/daisyblade.js'

// The searchable select takes the x-model of the caller on its TEXT box (the guide says so). Two things were wrong:
//  (a) the model kept the half-word typed ("On") after the user chose "One": choosing changed the box programmatically,
//      and a programmatic change does not fire `input`;
//  (b) after choosing, the user emptied the box and the hidden input kept sending the key of the old choice.

/** A searchable select with a fake text box, a fake hidden input and a list of what was dispatched on each. */
function searchableWithBox() {
    const inputEvents = []
    const announced = []
    const box = { value: '', dispatchEvent: event => { inputEvents.push([event.type, box.value]); select.typed(box.value) } }
    const select = Object.assign(dblSelectSearch({ name: 'city', options: { 1: 'One', 2: 'Two' } }), {
        $root: { querySelector: () => box },
        $refs: { value: { value: '' } },
        $dispatch: (name, detail) => announced.push([name, detail]),
    })
    return { select, box, inputEvents, announced }
}

test('choosing an option writes its label in the text box and tells it with an input event', () => {
    const { select, box, inputEvents } = searchableWithBox()
    box.value = 'On'
    select.typed('On')

    select.choose({ key: '1', label: 'One' })

    assert.equal(box.value, 'One')
    assert.deepEqual(inputEvents, [['input', 'One']])   // the x-model / @input of the caller hear "One", not "On"
    assert.equal(select.search, 'One')
})

test('choosing keeps the key in the hidden input (the input event of the choice does not empty it)', () => {
    const { select, announced } = searchableWithBox()

    select.choose({ key: '1', label: 'One' })

    assert.equal(select.$refs.value.value, '1')
    assert.deepEqual(announced, [['dbl-select-changed', { name: 'city', value: '1' }]])
})

test('typing after choosing empties the hidden input and announces it', () => {
    const { select, announced } = searchableWithBox()
    select.choose({ key: '1', label: 'One' })
    announced.length = 0

    select.typed('')

    assert.equal(select.$refs.value.value, '')
    assert.deepEqual(announced, [['dbl-select-changed', { name: 'city', value: '' }]])
})

test('typing with nothing chosen announces nothing', () => {
    const { select, announced } = searchableWithBox()

    select.typed('On')

    assert.deepEqual(announced, [])
    assert.equal(select.search, 'On')
})

test('showSearch writes the text of the component in the box, only when it differs', () => {
    const { select, box } = searchableWithBox()
    select.search = 'Two'

    select.showSearch()

    assert.equal(box.value, 'Two')
})
