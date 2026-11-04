import test from 'node:test'
import assert from 'node:assert/strict'
import { observeSize, onceSized } from '../../resources/js/observeSize.js'

/** A ResizeObserver that a test can trigger, and an element whose size the test sets. */
function pageWithObserver() {
    const observers = []
    globalThis.ResizeObserver = class {
        constructor(callback) { this.callback = callback; this.observing = null; observers.push(this) }
        observe(element) { this.observing = element }
        disconnect() { this.observing = null }
    }
    const element = { size: { width: 0, height: 0 }, getBoundingClientRect() { return this.size } }
    const resizeTo = (width, height) => { element.size = { width, height }; observers.forEach(observer => observer.observing && observer.callback()) }
    return { element, resizeTo, observers, leave: () => delete globalThis.ResizeObserver }
}

test('a hidden element (0 px) says nothing; once it is shown the size arrives', () => {
    const page = pageWithObserver()
    try {
        const sizes = []
        observeSize(page.element, size => sizes.push(size))

        page.resizeTo(0, 0)
        page.resizeTo(300, 0)
        assert.deepEqual(sizes, [])

        page.resizeTo(300, 150)
        assert.deepEqual(sizes, [{ width: 300, height: 150 }])
    } finally { page.leave() }
})

test('the same size twice is one call, and a new size is another', () => {
    const page = pageWithObserver()
    try {
        const sizes = []
        observeSize(page.element, size => sizes.push(size))

        page.resizeTo(300, 150)
        page.resizeTo(300, 150)
        page.resizeTo(400, 150)
        assert.equal(sizes.length, 2)
    } finally { page.leave() }
})

test('stop() disconnects the observer', () => {
    const page = pageWithObserver()
    try {
        const sizes = []
        const watch = observeSize(page.element, size => sizes.push(size))
        watch.stop()
        page.resizeTo(300, 150)
        assert.deepEqual(sizes, [])
    } finally { page.leave() }
})

test('onceSized calls back the first time there is a size and never again', () => {
    const page = pageWithObserver()
    try {
        let calls = 0
        onceSized(page.element, () => { calls++ })
        page.resizeTo(0, 0)
        page.resizeTo(300, 150)
        page.resizeTo(500, 150)
        assert.equal(calls, 1)
    } finally { page.leave() }
})
