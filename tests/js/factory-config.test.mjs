import test from 'node:test'
import assert from 'node:assert/strict'
import { withConfig } from '../../resources/js/factoryConfig.js'
import { dblSidebar, dblSteps, dblRepeater } from '../../resources/js/daisyblade.js'

// withConfig() is the declaration of what a factory reads (cause 7). The comparison with the views is in FactoryConfigTest.php.

test('withConfig: a missing key and a null key take the default, a given key stays', () => {
    const factory = withConfig({ a: 1, b: 'x', c: 3 }, config => config)

    assert.deepEqual(factory({ b: null, c: 0 }), { a: 1, b: 'x', c: 0 })
    assert.deepEqual(factory(), { a: 1, b: 'x', c: 3 })
    assert.deepEqual(factory(null), { a: 1, b: 'x', c: 3 })
})

test('withConfig: keys that are not declared pass through (a callback given from JavaScript)', () => {
    const callback = () => {}
    assert.equal(withConfig({ a: 1 }, config => config)({ onSuccess: callback }).onSuccess, callback)
})

test('withConfig: two components never share the default list or object', () => {
    const first = dblRepeater({ name: 'r' })
    const second = dblRepeater({ name: 'r' })
    first.rows.push({ a: 1 })

    assert.deepEqual(second.rows, [])
})

test('withConfig: it hands the declaration to whoever wants to compare it, and the rest of the arguments to the factory', () => {
    const factory = withConfig({ a: 1 }, (config, deps) => ({ config, deps }), { optional: ['a'] })

    assert.deepEqual(factory.declared, { defaults: { a: 1 }, optional: ['a'] })
    assert.deepEqual(factory({}, { axios: 1 }).deps, { axios: 1 })
    assert.deepEqual(dblSteps.declared.defaults, { current: 1, total: 0 })
})

// ── The sidebar: the case that started cause 7 ─────────────────────────────

function withBrowser(storage, width, run) {
    globalThis.window = { innerWidth: width }
    globalThis.localStorage = storage
    try { return run() } finally { delete globalThis.window; delete globalThis.localStorage }
}

test('sidebar collapsible=false: open at any width, and neither the saved choice nor a toggle move it', () => {
    const saved = new Map([['dbl_sidebar', 'false']])
    const storage = { getItem: key => saved.get(key) ?? null, setItem: (key, value) => saved.set(key, String(value)), removeItem: key => saved.delete(key) }

    withBrowser(storage, 500, () => {
        const sidebar = dblSidebar({ collapsible: false })
        sidebar.init()
        sidebar.toggle()
        sidebar.close()

        assert.equal(sidebar.open, true)
        assert.equal(saved.get('dbl_sidebar'), 'false')   // not even written
    })
})

test('sidebar collapsible (the default): it starts by the width, remembers the choice and toggles', () => {
    const saved = new Map()
    const storage = { getItem: key => saved.get(key) ?? null, setItem: (key, value) => saved.set(key, String(value)), removeItem: key => saved.delete(key) }

    withBrowser(storage, 500, () => {
        const sidebar = dblSidebar({})
        sidebar.init()
        assert.equal(sidebar.open, false)
        sidebar.toggle()
        assert.equal(sidebar.open, true)
        assert.equal(saved.get('dbl_sidebar'), 'true')
    })
})
