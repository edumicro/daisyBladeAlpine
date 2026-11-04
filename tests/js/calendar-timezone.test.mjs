// The zone must be fixed BEFORE any Date is made: this file has its own node process (node --test runs one per file).
process.env.TZ = 'Europe/Madrid'

import test from 'node:test'
import assert from 'node:assert/strict'
import { dblCalendar, eventDays, groupByDay, hasZone, dayOf } from '../../resources/js/components/calendar.js'

// Laravel serializes a Carbon as '2026-05-31T23:30:00.000000Z'. Those are the 01:30 of June 1st in Madrid (summer: UTC+2):
// the day of the event is the one of the person who looks, and it must agree with the hour that is shown.

test('hasZone: Z and offsets are zones; a date, or a date with an hour and no zone, is not', () => {
    for (const withZone of ['2026-05-31T23:30:00Z', '2026-05-31T23:30:00.000000Z', '2026-06-01T01:30:00+02:00', '2026-06-01T01:30:00-0300', '2026-06-01T01:30+02']) {
        assert.equal(hasZone(withZone), true, withZone)
    }
    for (const without of ['2026-06-01', '2026-06-01T01:30', '2026-06-01T01:30:00']) {
        assert.equal(hasZone(without), false, without)
    }
})

test('an event with a zone falls on the day of the viewer, not on the day written in the text', () => {
    assert.equal(dayOf('2026-05-31T23:30:00.000000Z'), '2026-06-01')
    assert.deepEqual(eventDays({ start: '2026-05-31T23:30:00.000000Z' }), ['2026-06-01'])
    assert.deepEqual(Object.keys(groupByDay([{ start: '2026-05-31T23:30:00Z', title: 'x' }])), ['2026-06-01'])
})

test('an event without a zone stays on the day it is written, as before', () => {
    assert.equal(dayOf('2026-06-01T01:30'), '2026-06-01')
    assert.deepEqual(eventDays({ start: '2026-05-31T23:30' }), ['2026-05-31'])
})

test('events of different zones are sorted by the hour of the viewer', () => {
    const early = { title: 'early', start: '2026-06-01T01:00:00+02:00' }      // 01:00 in Madrid
    const late = { title: 'late', start: '2026-05-31T22:30:00Z' }            // 00:30 in Madrid, written with an EARLIER date text
    // As text, "2026-05-31..." sorts before "2026-06-01...", and late (00:30) is before early (01:00) in Madrid too; swap the pair to prove it is not luck.
    const grouped = groupByDay([early, late])
    assert.deepEqual(grouped['2026-06-01'].map(event => event.title), ['late', 'early'])
})

test('the hour shown is the one of the viewer, the same one that decided the day', () => {
    const calendar = dblCalendar({ loadUrl: '/c', locale: 'en' }, {})
    assert.match(calendar.timeLabel({ start: '2026-05-31T23:30:00Z' }), /01:30/)
})

// ── The keyboard focus after changing month ────────────────────────────────

test('after prev() and next() a day has the focus of the keyboard (the grid keeps a tab stop)', async () => {
    const answers = { get: async () => ({ data: [], status: 200 }) }
    const calendar = Object.assign(dblCalendar({ loadUrl: '/c', initialDate: '2026-05-10', initialView: 'month' }, { axios: answers }), { $nextTick: fn => fn(), $root: {} })
    globalThis.window = {}
    globalThis.location = { href: 'https://app.test/', origin: 'https://app.test' }
    try {
        calendar.init()
        calendar.next()
        assert.equal(calendar.focused, '2026-06-01')
        calendar.prev()
        calendar.prev()
        assert.equal(calendar.focused, '2026-04-01')
    } finally { delete globalThis.window; delete globalThis.location }
})
