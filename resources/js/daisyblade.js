'use strict'

// ── Tipo 2: local Alpine state, no server calls ────────────────────────────

const dbModal = (config = {}) => ({
    open: config.open ?? false,
    show() { this.open = true },
    hide() { this.open = false },
    toggle() { this.open = !this.open },
})

const dbToast = (config = {}) => ({
    toasts: [],
    add(message, type = 'info', duration = 4000) {
        const id = Date.now()
        this.toasts.push({ id, message, type })
        if (duration > 0) setTimeout(() => this.remove(id), duration)
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id)
    },
})

const dbTabs = (config = {}) => ({
    active: config.default ?? '',
    select(tab) { this.active = tab },
    isActive(tab) { return this.active === tab },
})

const dbNavbar = (config = {}) => ({
    open: false,
    toggle() { this.open = !this.open },
    close() { this.open = false },
})

const dbSidebar = (config = {}) => ({
    open: config.open ?? window.innerWidth >= 1024,
    init() {
        if (config.persistent ?? true) {
            const saved = localStorage.getItem('db_sidebar')
            if (saved !== null) this.open = saved === 'true'
        }
    },
    toggle() {
        this.open = !this.open
        if (config.persistent ?? true) localStorage.setItem('db_sidebar', this.open)
    },
    close() { this.open = false },
})

const dbSidebarTree = (config = {}) => ({
    expanded: {},
    toggle(key) { this.expanded[key] = !this.expanded[key] },
    isOpen(key) { return !!this.expanded[key] },
})

// ── Tipo 3: Alpine + Axios (phase 4-6) ────────────────────────────────────

const dbDataTable = (config = {}) => ({
    rows: [], meta: {}, loading: true,
    page: 1, search: '', sort: null, direction: 'asc',
    async init() { await this.load() },
    async load() {
        this.loading = true
        const { data } = await axios.get(config.loadUrl, {
            params: { page: this.page, search: this.search, sort: this.sort, direction: this.direction, ...config.params },
        })
        this.rows      = data.data ?? []
        this.meta      = data.meta ?? {}
        this.loading   = false
    },
    sortBy(col) {
        if (this.sort === col) this.direction = this.direction === 'asc' ? 'desc' : 'asc'
        else { this.sort = col; this.direction = 'asc' }
        this.page = 1; this.load()
    },
    goTo(p) { this.page = p; this.load() },
})

const dbAutoForm = (config = {}) => ({
    values: config.values ?? {},
    errors: {},
    loading: false,
    async submit() {
        this.loading = true; this.errors = {}
        try {
            const { data } = await axios({ method: config.method ?? 'POST', url: config.action, data: this.values })
            if (data.redirect) window.location.href = data.redirect
        } catch (e) {
            if (e.response?.status === 422) this.errors = e.response.data.errors ?? {}
        } finally { this.loading = false }
    },
    hasError(field) { return !!this.errors[field] },
    firstError(field) { return this.errors[field]?.[0] ?? '' },
})

const dbWizard = (config = {}) => {
    const key = `db_wizard_${config.userId ?? 'guest'}_${config.formId}_v${config.schemaVersion ?? 1}`
    return {
        step: 0, data: {}, errors: {}, loading: false,
        init() {
            const saved = localStorage.getItem(key)
            if (saved) { try { const p = JSON.parse(saved); this.step = p.step ?? 0; this.data = p.data ?? {} } catch {} }
        },
        save() { localStorage.setItem(key, JSON.stringify({ step: this.step, data: this.data })) },
        clear() { localStorage.removeItem(key) },
        next() { this.step++; this.save() },
        prev() { if (this.step > 0) { this.step--; this.save() } },
        async submit() {
            this.loading = true; this.errors = {}
            try {
                const { data } = await axios.post(config.action, this.data)
                if (data.success) { this.clear(); window.location.href = data.redirect }
                else this.errors = data.errors ?? {}
            } catch (e) {
                if (e.response?.status === 422) this.errors = e.response.data.errors ?? {}
            } finally { this.loading = false }
        },
    }
}

// ── Registration ───────────────────────────────────────────────────────────

const _all = { dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree, dbDataTable, dbAutoForm, dbWizard }

function _register(Alpine) {
    for (const [name, fn] of Object.entries(_all)) Alpine.data(name, fn)
}

if (typeof window !== 'undefined') {
    Object.assign(window, _all)

    // Alpine already started (script loaded late)
    if (window.Alpine) _register(window.Alpine)

    // Standard path: register before Alpine starts
    document.addEventListener('alpine:init', () => _register(window.Alpine))
}

// ESM export for Vite / import
export { dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree, dbDataTable, dbAutoForm, dbWizard }
