'use strict'

// THE CONFIGURATION OF A FACTORY IS DECLARED, NOT GUESSED.
//
// A view starts its component with `x-data="dblName(@js($config))"`: PHP builds the object, the factory reads it. When each side
// only "knew" the keys, one could change without the other noticing: the sidebar never received `collapsible` and nothing failed.
//
// So every factory declares at its top the keys it reads and the value each one has when the view does not give it:
//
//     const dblSteps = withConfig({ current: 1, total: 0 }, config => ({ ...uses config.current and config.total... }))
//
// and tests/Feature/FactoryConfigTest.php compares that declaration with what each view really passes: no key that the view
// sends and the factory ignores, none that the factory reads and the view forgets.

/** A copy of the default, so two components never share (and mutate) the same list or object. */
const freshCopy = value => (typeof value === 'object' && value !== null ? structuredClone(value) : value)

/**
 * @param {object}   defaults   every key the factory reads, with its default (null and a missing key both mean "not given")
 * @param {Function} factory    (config, ...rest) => the Alpine data object; `config` already has every declared key
 * @param {object}   [options]
 * @param {string[]} [options.optional]  declared keys that a VIEW does not pass because only JavaScript can give them
 *                                       (a callback such as `onSuccess`). The test of the views skips them.
 * @returns {Function} (config, ...rest) => the Alpine data object; its `.declared` holds `{ defaults, optional }`
 */
export function withConfig(defaults, factory, { optional = [] } = {}) {
    const configured = (given, ...rest) => {
        const config = { ...(given ?? {}) }
        for (const [key, fallback] of Object.entries(defaults)) config[key] = config[key] ?? freshCopy(fallback)
        return factory(config, ...rest)
    }
    configured.declared = { defaults, optional }
    return configured
}
