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
//   3. A change of the model from outside is always shown.
//   4. What the user chooses is written to the model, and the control announces `dbl-<component>-changed`.
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

        /** Rule 4: the user chose `value`: it goes to the model. */
        write(value) { access?.set(value) },
    }
}
