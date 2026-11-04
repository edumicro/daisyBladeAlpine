import test from 'node:test'
import assert from 'node:assert/strict'
import { dblSelectSearch } from '../../resources/js/daisyblade.js'

// The `x-model` of the searchable select holds the chosen KEY, as on a native <select> (the view pairs it with
// `x-modelable="chosen"`), and the text box shows the label of that key. '' while nothing is chosen, like a native select.
// This is the factory side: `chosen` is the model, both ways.

/** A searchable select with a fake text box, a fake hidden input and a list of what was dispatched. */
function searchableWithBox(config = {}) {
    const inputEvents = []
    const announced = []
    const box = { value: '', dispatchEvent: event => { inputEvents.push([event.type, box.value]); select.typed(box.value) } }
    const select = Object.assign(dblSelectSearch({ name: 'city', options: { 1: 'One', 2: 'Two' }, ...config }), {
        $root: { querySelector: () => box },
        $refs: { value: { value: '' } },
        $dispatch: (name, detail) => announced.push([name, detail]),
    })
    return { select, box, inputEvents, announced }
}

test('choosing an option puts its KEY in the model and its label in the box', () => {
    const { select, box } = searchableWithBox()
    box.value = 'On'
    select.typed('On')

    select.choose({ key: '1', label: 'One' })

    assert.equal(select.chosen, '1')
    assert.equal(box.value, 'One')
    assert.equal(select.$refs.value.value, '1')
})

test('while typing with nothing chosen, the model is empty (as a native select)', () => {
    const { select } = searchableWithBox()

    select.typed('On')

    assert.equal(select.chosen, '')
    assert.equal(select.search, 'On')
})

test('typing after choosing empties the model and the hidden input and announces it', () => {
    const { select, announced } = searchableWithBox()
    select.choose({ key: '1', label: 'One' })
    announced.length = 0

    select.typed('')

    assert.equal(select.chosen, '')
    assert.equal(select.$refs.value.value, '')
    assert.deepEqual(announced, [['dbl-select-changed', { name: 'city', value: '' }]])
})

test('the input that choosing sends to the box (for the @input of the caller) does not undo the choice', () => {
    const { select, inputEvents, announced } = searchableWithBox()

    select.choose({ key: '2', label: 'Two' })

    assert.deepEqual(inputEvents, [['input', 'Two']])
    assert.equal(select.chosen, '2')
    assert.deepEqual(announced, [['dbl-select-changed', { name: 'city', value: '2' }]])
})

test('the model changed from outside makes the box show the label of that key', () => {
    const { select, box } = searchableWithBox()

    select.chosen = '2'     // what x-modelable does when form.city changes
    select.sync()

    assert.equal(select.search, 'Two')
    assert.equal(box.value, 'Two')
    assert.equal(select.$refs.value.value, '2')
})

test('a number in the model is the same key as its text', () => {
    const { select, box } = searchableWithBox()

    select.chosen = 1
    select.sync()

    assert.equal(box.value, 'One')
})

test('the model emptied from outside (a reset) empties the box and the hidden input', () => {
    const { select, box } = searchableWithBox({ chosen: '1', search: 'One' })
    box.value = 'One'

    select.chosen = null
    select.sync()

    assert.equal(box.value, '')
    assert.equal(select.$refs.value.value, '')
})

test('syncing does not touch the text the user is typing', () => {
    const { select, box } = searchableWithBox()
    box.value = 'Tw'
    select.typed('Tw')

    select.sync()

    assert.equal(box.value, 'Tw')
})

test('the label of a key is found in grouped options too', () => {
    const { select } = searchableWithBox({ options: { Europa: { es: 'España' }, Asia: { jp: 'Japón' } } })

    assert.equal(select.labelOf('jp'), 'Japón')
    assert.equal(select.labelOf('zz'), '')
})
