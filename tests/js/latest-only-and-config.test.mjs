import test from 'node:test'
import assert from 'node:assert/strict'
import { latestOnly } from '../../resources/js/http.js'
import { modelBinding } from '../../resources/js/modelBinding.js'
import { withConfig } from '../../resources/js/factoryConfig.js'

test('latestOnly: cancel() while a request is in flight makes it stale even if it FAILS', async () => {
    const latest = latestOnly()
    const outcome = latest.run(async () => { await new Promise(resolve => setTimeout(resolve, 5)); throw new Error('boom') })
    latest.cancel()
    assert.deepEqual(await outcome, { stale: true })
})

test('modelBinding: a model of 0 or false is a value, not an empty model', () => {
    let model = 0
    const binding = modelBinding({ get: () => model, set: value => { model = value } })
    assert.equal(binding.reconcile('3'), 0)
    model = false
    assert.equal(binding.reconcile('3'), false)
})

test('withConfig: a key with value 0, false or "" given by the view is kept', () => {
    const factory = withConfig({ total: 5, open: true, label: 'x' }, config => config)
    assert.deepEqual(factory({ total: 0, open: false, label: '' }), { total: 0, open: false, label: '' })
})

test('withConfig: a key the view sends that the factory did not declare still reaches the factory', () => {
    const factory = withConfig({ a: 1 }, config => config)
    assert.equal(factory({ a: 2, extra: 3 }).extra, 3)
})
