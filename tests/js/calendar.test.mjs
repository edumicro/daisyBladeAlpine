import test from 'node:test'
import assert from 'node:assert/strict'
import { buildMonthGrid, eventDays, groupByDay, addDays, hasTime } from '../../resources/js/components/calendar.js'

test('la cuadrícula tiene 6 semanas y empieza en lunes', () => {
    const grid = buildMonthGrid(2026, 9, 1) // octubre 2026: el día 1 es jueves
    assert.equal(grid.length, 42)
    assert.equal(grid[0].date, '2026-09-28')
    assert.equal(grid[0].inMonth, false)
    assert.equal(grid[3].date, '2026-10-01')
    assert.equal(grid[3].inMonth, true)
    assert.equal(grid.filter(c => c.inMonth).length, 31)
})

test('con semana en domingo la primera celda es domingo', () => {
    const grid = buildMonthGrid(2026, 9, 0)
    assert.equal(grid[0].date, '2026-09-27')
})

test('addDays cruza meses y años', () => {
    assert.equal(addDays('2026-12-31', 1), '2027-01-01')
    assert.equal(addDays('2026-03-01', -1), '2026-02-28')
})

test('un evento de varios días ocupa cada día del rango', () => {
    assert.deepEqual(eventDays({ start: '2026-10-30', end: '2026-11-02' }), ['2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'])
})

test('sin fin, o con fin anterior, solo ocupa el día de inicio', () => {
    assert.deepEqual(eventDays({ start: '2026-10-05T10:00' }), ['2026-10-05'])
    assert.deepEqual(eventDays({ start: '2026-10-05', end: '2026-10-01' }), ['2026-10-05'])
})

test('hasTime distingue fecha con hora, sin hora y all_day', () => {
    assert.equal(hasTime({ start: '2026-10-05T10:00' }), true)
    assert.equal(hasTime({ start: '2026-10-05' }), false)
    assert.equal(hasTime({ start: '2026-10-05T10:00', all_day: true }), false)
})

test('groupByDay reparte y ordena: sin hora primero, luego por inicio', () => {
    const by = groupByDay([
        { id: 1, title: 'tarde', start: '2026-10-05T17:00' },
        { id: 2, title: 'mañana', start: '2026-10-05T09:00' },
        { id: 3, title: 'congreso', start: '2026-10-04', end: '2026-10-05', all_day: true },
    ])
    assert.deepEqual(by['2026-10-05'].map(e => e.id), [3, 2, 1])
    assert.deepEqual(by['2026-10-04'].map(e => e.id), [3])
})
