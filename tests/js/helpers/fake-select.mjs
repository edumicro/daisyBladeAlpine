// A <select> and the bit of the DOM that dblSelectRemote touches, for tests that run in node (no browser).
// Not a test itself (the name does not end in .test.mjs): `node --test tests/js/*.test.mjs` does not run it.

/** An <option> as `document.createElement('option')` would give it, with the attributes it was given. */
const fakeOption = () => ({
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value },
    remove() { this.removed = true },
})

/**
 * The <select>. `options` holds the <option>s that were written in it (`append`), `selectedOptions` the ones the user
 * chose (a test sets it, as the browser would after a click). `overrides` replaces any member.
 */
export function fakeSelect(overrides = {}) {
    const attributes = {}
    const field = {
        form: null, selectedOptions: [], value: '', disabled: false,
        attributes,
        options: [],
        setAttribute(name, value) { attributes[name] = value },
        // dblSelectRemote asks for the options of the server (`data-initial`) and for its own (`data-loaded`): the first
        // are not in a fake select; the second are the ones appended, minus the ones removed.
        querySelectorAll(selector) {
            if (selector !== 'option[data-loaded]') return []
            return field.options.filter(option => 'data-loaded' in option.attributes && !option.removed)
        },
        append(...options) { field.options.push(...options) },
        ownerDocument: { createElement: () => fakeOption() },
        dispatchEvent() {},
        ...overrides,
    }
    return field
}

/** The <option>s a fake select shows now: the ones appended and not removed, as { value, label, selected }. */
export const shownOptions = field =>
    field.options.filter(option => !option.removed).map(option => ({ value: option.value, label: option.textContent, selected: option.selected }))

/** A dblSelectRemote's `$root`: the element with the x-data, whose parent holds the <select>. */
export const rootBesides = field => ({ parentElement: { querySelector: () => field } })
