'use strict'

import { request as sendRequest, latestOnly, answerShapes } from '../http.js'
import { withConfig } from '../factoryConfig.js'

// ── Fechas: funciones puras (se prueban con `node --test`) ─────────────────
// Los días se manejan como cadenas 'YYYY-MM-DD'. Así no hay zonas horarias de por medio.

const pad = n => String(n).padStart(2, '0')

/** Date -> 'YYYY-MM-DD' (en hora local). */
export const toISO = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

/** 'YYYY-MM-DD...' -> Date a las 00:00 locales. */
export const parseISO = s => new Date(+s.slice(0, 4), +s.slice(5, 7) - 1, +s.slice(8, 10))

export const addDays = (iso, n) => {
    const d = parseISO(iso)
    d.setDate(d.getDate() + n)
    return toISO(d)
}

/**
 * Cuadrícula del mes: siempre 6 semanas (42 días).
 * @param {number} year
 * @param {number} month        0-11
 * @param {number} weekStartsOn 0 = domingo, 1 = lunes...
 * @returns {{date: string, inMonth: boolean}[]}
 */
export function buildMonthGrid(year, month, weekStartsOn = 1) {
    const first = new Date(year, month, 1)
    const offset = (first.getDay() - weekStartsOn + 7) % 7
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(year, month, 1 - offset + i)
        return { date: toISO(d), inMonth: d.getMonth() === month }
    })
}

/**
 * ¿El texto de fecha dice su zona ("...Z", "...+02:00")? Entonces es un instante (así serializa Laravel un Carbon) y el día en que
 * cae depende de dónde esté quien mira: las 23:30 UTC del 31 de mayo son las 01:30 del 1 de junio en Madrid.
 */
export const hasZone = text => text.length > 10 && /(?:Z|[+-]\d{2}(?::?\d{2})?)$/.test(text)

/** El día ('YYYY-MM-DD') de un texto de fecha, a la hora de quien mira: con zona se convierte; sin ella se toma tal como está escrito. */
export const dayOf = text => (hasZone(text) ? toISO(new Date(text)) : text.slice(0, 10))

/** La fecha y hora ('YYYY-MM-DDTHH:MM') de un texto, a la hora de quien mira. Sirve para ordenar eventos de zonas distintas. */
const localStamp = text => (hasZone(text) ? `${toISO(new Date(text))}T${pad(new Date(text).getHours())}:${pad(new Date(text).getMinutes())}` : text)

/** Días (inclusive) que cubre un evento. Si el fin es anterior al inicio, solo el inicio. */
export function eventDays(ev) {
    const start = dayOf(ev.start)
    const end = dayOf(ev.end ?? ev.start)
    const days = []
    for (let d = start; d <= end || !days.length; d = addDays(d, 1)) days.push(d)
    return days
}

/** ¿El evento tiene hora? (all_day o fecha sin hora => no). */
export const hasTime = ev => !ev.all_day && ev.start.length > 10

/** Reparte los eventos por día: { 'YYYY-MM-DD': [ev, ...] }. Un evento de varios días sale en cada uno. */
export function groupByDay(events) {
    const byDay = {}
    for (const ev of events) {
        for (const day of eventDays(ev)) (byDay[day] ??= []).push(ev)
    }
    for (const list of Object.values(byDay)) {
        // Sin hora primero; luego por inicio.
        list.sort((a, b) => (hasTime(a) - hasTime(b)) || localStamp(a.start).localeCompare(localStamp(b.start)))
    }
    return byDay
}

// ── Componente Alpine ──────────────────────────────────────────────────────

const intlLocale = l => (l === 'ca' ? 'ca-ES' : (l || 'es').replace('_', '-'))

export const dblCalendar = withConfig({ loadUrl: '', locale: '', initialDate: '', initialView: '', weekStartsOn: 1 }, (config, deps = {}) => ({
    locale: intlLocale(config.locale),
    weekStartsOn: config.weekStartsOn,
    view: 'month',
    cursor: null,       // primer día del mes que se muestra
    focused: null,      // día con el foco del teclado
    selectedDay: null,  // día cuya lista está abierta ("+n más")
    events: [],
    byDay: {},          // eventos repartidos por día (se recalcula al cambiar events)
    loading: false,
    error: false,
    cache: {},          // 'start|end' -> eventos
    latest: latestOnly(),   // solo la última petición cuenta: ni su respuesta ni su error llegan si el usuario ya cambió de mes

    init() {
        const today = toISO(new Date())
        const initial = parseISO(config.initialDate || today)
        this.cursor = new Date(initial.getFullYear(), initial.getMonth(), 1)
        this.focused = toISO(initial)
        // En móvil (< sm) la lista es la vista por defecto, salvo que se pida otra.
        const mobile = typeof window !== 'undefined' && window.matchMedia?.('(max-width: 639px)').matches
        this.view = config.initialView || (mobile ? 'list' : 'month')
        this.load()
    },

    // Rango que se pide al servidor: el de la cuadrícula visible.
    get grid() { return buildMonthGrid(this.cursor.getFullYear(), this.cursor.getMonth(), this.weekStartsOn) },
    get today() { return toISO(new Date()) },

    get title() {
        return new Intl.DateTimeFormat(this.locale, { month: 'long', year: 'numeric' }).format(this.cursor)
    },
    get weekdays() {
        // 2023-01-01 fue domingo.
        const fmt = new Intl.DateTimeFormat(this.locale, { weekday: 'short' })
        return Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2023, 0, 1 + ((this.weekStartsOn + i) % 7))))
    },
    // Días del mes con eventos, para la vista lista.
    get listDays() {
        return this.grid.filter(c => c.inMonth && this.byDay[c.date]).map(c => c.date)
    },
    get isEmpty() { return !this.loading && !this.error && this.listDays.length === 0 },

    dayEvents(day) { return this.byDay[day] ?? [] },
    dayNumber(day) { return parseISO(day).getDate() },
    dayLabel(day) {
        return new Intl.DateTimeFormat(this.locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(parseISO(day))
    },
    timeLabel(ev) {
        if (!hasTime(ev)) return ''
        return new Intl.DateTimeFormat(this.locale, { hour: '2-digit', minute: '2-digit' }).format(new Date(ev.start))
    },

    // ── Carga ──
    async load() {
        const grid = this.grid
        const start = grid[0].date
        const end = grid[grid.length - 1].date
        const key = `${start}|${end}`
        this.selectedDay = null
        this.error = false

        if (this.cache[key]) {
            // Este mes ya está: lo que siga en vuelo (otro mes) deja de contar y su aviso de carga se apaga.
            this.latest.cancel()
            this.loading = false
            this.setEvents(this.cache[key])
            return
        }
        this.loading = true
        const outcome = await this.latest.run(async () => {
            // Not JSON, or not events (the login page of an expired session): an error, not an empty month that gets cached.
            return answerShapes.calendarEvents(await this.request(config.loadUrl, { start, end }), config.loadUrl)
        })
        if (outcome.stale) return    // el usuario ya está en otro mes: esta respuesta no pinta nada

        this.loading = false
        if (outcome.error) {
            this.error = true
            this.setEvents([])
        } else {
            this.cache[key] = outcome.value
            this.setEvents(outcome.value)
        }
    },
    setEvents(list) {
        this.events = list
        this.byDay = groupByDay(list)
    },
    currentKey() {
        const g = this.grid
        return `${g[0].date}|${g[g.length - 1].date}`
    },
    // Pide los eventos con la escalera de http.js: axios inyectado, `window.axios` o `fetch`.
    async request(url, params) {
        return (await sendRequest({ url, params }, deps)).data
    },

    // ── Navegación ──
    goTo(date) {
        this.cursor = new Date(date.getFullYear(), date.getMonth(), 1)
        this.load()
    },
    prev() { this.showMonth(new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1)) },
    next() { this.showMonth(new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1)) },
    // Otro mes, con el foco del teclado en su primer día: la cuadrícula siempre tiene un día al que se llega con Tab.
    showMonth(firstDay) {
        this.focused = toISO(firstDay)
        this.goTo(firstDay)
    },
    goToday() { this.focused = this.today; this.goTo(new Date()) },
    setView(view) { this.view = view; this.selectedDay = null },

    // ── Teclado: flechas por los días ──
    moveFocus(delta) {
        const day = addDays(this.focused ?? toISO(this.cursor), delta)
        const d = parseISO(day)
        const changedMonth = d.getMonth() !== this.cursor.getMonth() || d.getFullYear() !== this.cursor.getFullYear()
        this.focused = day
        if (changedMonth) this.goTo(d)
        this.$nextTick(() => this.$root.querySelector(`[data-day="${day}"]`)?.focus())
    },
    onDayKeydown(e) {
        const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key]
        if (delta === undefined) return
        e.preventDefault()
        this.moveFocus(delta)
    },

    // ── Eventos ──
    selectDay(day) { this.focused = day; this.selectedDay = this.selectedDay === day ? null : day },
    // Announces the click to whoever listens above (`dbl-<component>-<what happened>`); the detail is the event itself.
    emit(ev) {
        this.$dispatch('dbl-calendar-event-clicked', ev)
    },
    open(ev) {
        this.emit(ev)
        if (ev.url) window.location.href = ev.url
    },
}))

export function registerCalendar(Alpine, deps = {}) {
    Alpine.data('dblCalendar', config => dblCalendar(config, deps))
}
