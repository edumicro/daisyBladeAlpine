import test from 'node:test'
import assert from 'node:assert/strict'
import { latestOnly } from '../../resources/js/http.js'

// A task that ends when the test says so (success or failure), to make requests finish in any order.
function manualTask() {
    let finish, fail
    const promise = new Promise((resolve, reject) => { finish = resolve; fail = reject })
    return { task: () => promise, finish, fail }
}

test('latestOnly: the only request counts and gives its value', async () => {
    const outcome = await latestOnly().run(async () => 'rows')
    assert.deepEqual(outcome, { stale: false, value: 'rows' })
})

test('latestOnly: a failure of the newest request comes back as `error`, never thrown', async () => {
    const boom = new Error('boom')
    const outcome = await latestOnly().run(async () => { throw boom })
    assert.deepEqual(outcome, { stale: false, error: boom })
})

test('latestOnly: the answer of an old request that arrives late is stale', async () => {
    const latest = latestOnly()
    const first = manualTask()
    const second = manualTask()
    const firstOutcome = latest.run(first.task)
    const secondOutcome = latest.run(second.task)
    second.finish('new')
    first.finish('old')
    assert.deepEqual(await secondOutcome, { stale: false, value: 'new' })
    assert.deepEqual(await firstOutcome, { stale: true })
})

test('latestOnly: the ERROR of an old request that arrives late is stale too', async () => {
    const latest = latestOnly()
    const first = manualTask()
    const second = manualTask()
    const firstOutcome = latest.run(first.task)
    latest.run(second.task)
    first.fail(new Error('late'))
    assert.deepEqual(await firstOutcome, { stale: true })
})

test('latestOnly: cancel() makes what is in flight stale without starting another request', async () => {
    const latest = latestOnly()
    const only = manualTask()
    const outcome = latest.run(only.task)
    latest.cancel()
    only.finish('x')
    assert.deepEqual(await outcome, { stale: true })
})
