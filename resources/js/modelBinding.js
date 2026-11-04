'use strict'

// ONE rule for every control that has a model (`x-model`, `x-modelable`) and also a value written by the view.
// The remote select, the searchable select and the character counter of the textarea use this file and nothing else, so they
// cannot disagree about who wins when the model and the view say different things.
//
// THE RULE
//
//   1. At the start the MODEL wins if it holds something. If it is empty (null, '', or an empty list), the VALUE of the view
//      is shown and the model RECEIVES it (the typical edit form: the model starts empty and the saved value comes from the view).
//   2. A value that is not among the options is never erased or changed: the control keeps it (the select keeps an option for it).
//      Only the reload caused by the control it depends on may empty it, because then the old value really is out of date.
//   3. A change of the model from outside is always shown, whenever it happens: the control OBSERVES its model for as long as it
//      lives (`observe()`), not only while it starts. A form that loads its record by fetch sets the model seconds after the
//      page was drawn.
//   4. What the user chooses is written to the model, and the control announces `dbl-<component>-changed`.
//
// THE CONTRACT OF A CONTROL WITH JAVASCRIPT (the second half of this file)
//
//   A control that draws its own interface (the searchable select, the key-value and list editors, the uploader, the signature,
//   the rich text) must still behave toward the page as the native control it replaces:
//     1. its value lives in a field with a `name` (a hidden input), which is what the form sends;
//     2. every time that value changes, the field announces it with the native events `input` and `change` (bubbling), the same
//        ones a `<input>` or a `<select>` fires when the user edits it. Whoever listens to a form (`depends-on`, a `@change` on the
//        <form>, a validator, a library) hears them with no knowledge of the control.
//   `announceValue()` and `writeValue()` below are the only way a control does it. tests/Browser/ControlContractBrowserTest.php
//   walks every control of the catalog and checks both events in its named field.
//
// Tested once in node (tests/js/model-binding.test.mjs) and once in the browser (tests/Browser/SelectBrowserTest.php).

/** True when a model holds nothing: null, undefined, '' or an empty list. (0 and false are values.) */
export const isEmptyValue = value => value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)

/**
 * The model of an element, as `{ get, set }`, or null when it has none.
 * Alpine has no public way to read the model of an element, but the `x-model` directive leaves `el._x_model` whatever its
 * spelling (`x-model.number`, `x-model.debounce.500ms`, one attached with `Alpine.bind()`...) and Alpine's own `x-modelable`
 * reads it too. Checked with Alpine 3.14. If a future Alpine drops it, this returns null and the control works without a model.
 */
export const modelOfElement = element => element?._x_model ?? null

/**
 * @param {{ get: () => *, set: (value: *) => void } | null} access  how to read and write the model (null: the control has none)
 */
export function modelBinding(access) {
    return {
        /** Rule 3: what the model holds now. Without a model, `valueOfTheControl`. Reading it inside an Alpine effect tracks it. */
        current: valueOfTheControl => (access ? access.get() : valueOfTheControl),

        /**
         * Rule 1: decides what the control holds. `valueOfTheView` is what the view wrote (`value="2"`), in the shape the model
         * uses (a list for a `multiple` select). Returns the value to hold; if that is the one of the view, the model gets it too.
         */
        reconcile(valueOfTheView) {
            const model = access ? access.get() : undefined
            if (!isEmptyValue(model)) return model
            if (!isEmptyValue(valueOfTheView) && access) access.set(valueOfTheView)
            return isEmptyValue(valueOfTheView) ? model : valueOfTheView
        },

        /**
         * Rule 3, for as long as the control lives: calls `onChange(value)` after every change of the model.
         * `watch` is the `$watch` of the component (Alpine follows whatever `get()` reads, so it must be called inside Alpine's
         * scope). A control without a model has nothing to observe.
         */
        observe(watch, onChange) {
            if (!access) return
            watch(() => access.get(), onChange)
        },

        /** Rule 4: the user chose `value`: it goes to the model. */
        write(value) { access?.set(value) },
    }
}

// ── The contract of a control with JavaScript (see the top of this file) ───────────────────────────────────────────────────

/** Tell the page that the value of `field` changed, with the two native events a native control fires. */
export function announceValue(field) {
    for (const type of ['input', 'change']) field.dispatchEvent(new Event(type, { bubbles: true }))
}

/**
 * Write `value` (a text) in the field with a name and announce it, but only when it is not already there: a control that writes
 * its field every time it redraws (an Alpine effect) must not shout "changed" when nothing changed.
 */
export function writeValue(field, value) {
    if (!field || field.value === value) return
    field.value = value
    announceValue(field)
}

/**
 * For a field whose value Alpine writes by itself (`<input type="hidden" :value="toJson()">`): announce it whenever `read()` gives
 * another value. The announcement waits for the next tick because Alpine writes the field in its own effect, and that effect may
 * run after this watcher: announcing first would tell the page about the OLD value.
 *
 * @param {object}   component  the Alpine component (`this` inside its methods)
 * @param {Function} read       gives the value that the field shows (anything Alpine can track)
 * @param {Function} field      gives the field (it is looked up when the announcement is made, not before)
 */
export function announceWhenItChanges(component, read, field) {
    component.$watch(read, () => component.$nextTick(() => announceValue(field())))
}
