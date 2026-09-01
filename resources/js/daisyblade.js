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

// ── Tipo 3: Alpine + Axios ────────────────────────────────────────────────

const dbSelectRemote = (config = {}) => ({
    options: [],
    loading: false,
    search: '',
    open: false,
    value: config.value ?? null,
    async init() { if (config.optionsUrl) await this.fetch('') },
    async fetch(q = '') {
        this.loading = true
        try {
            const { data } = await axios.get(config.optionsUrl, { params: { search: q, ...(config.params ?? {}) } })
            this.options = Array.isArray(data) ? data : (data.data ?? [])
        } finally { this.loading = false }
    },
    filtered() {
        if (!this.search) return this.options
        const q = this.search.toLowerCase()
        return this.options.filter(o => String(o.label ?? o.name ?? o).toLowerCase().includes(q))
    },
    select(opt) { this.value = opt.value ?? opt.id ?? opt; this.open = false },
    labelFor(val) { return this.options.find(o => (o.value ?? o.id) == val)?.label ?? val ?? '' },
})

const dbResourceDetails = (config = {}) => ({
    resource: null, loading: true, error: null,
    get displayData() {
        if (!this.resource) return {}
        if (config.fields?.length) return Object.fromEntries(config.fields.map(k => [k, this.resource[k]]))
        return this.resource
    },
    async init() { await this.load() },
    async load() {
        this.loading = true; this.error = null
        try {
            const { data } = await axios.get(config.loadUrl)
            this.resource = data.data ?? data
        } catch (e) {
            this.error = e.response?.data?.message ?? 'Error loading resource'
        } finally { this.loading = false }
    },
    refresh() { return this.load() },
})

const dbTabsLazy = (config = {}) => ({
    active: config.active ?? 0,
    tabs: config.tabs ?? [],
    contents: {},
    loading: {},
    async init() { if (this.tabs[this.active]?.url) await this._load(this.active) },
    async select(index) {
        this.active = index
        if (!(index in this.contents) && this.tabs[index]?.url) await this._load(index)
    },
    async _load(index) {
        this.loading = { ...this.loading, [index]: true }
        try {
            const { data } = await axios.get(this.tabs[index].url)
            this.contents = { ...this.contents, [index]: typeof data === 'string' ? data : JSON.stringify(data) }
        } finally {
            this.loading = { ...this.loading, [index]: false }
        }
    },
    isActive(i) { return this.active === i },
    isLoading(i) { return !!this.loading[i] },
})

const dbSpreadsheetImport = (config = {}) => ({
    file: null, step: 'upload',
    headers: [], preview: [], totalRows: 0,
    progress: 0, loading: false, error: null, result: null,
    async handleFile(event) { this.file = event.target.files[0]; if (this.file) await this._parsePreview() },
    async handleDrop(event) { this.file = event.dataTransfer.files[0]; if (this.file) await this._parsePreview() },
    async _parsePreview() {
        this.step = 'preview'
        this.totalRows = 0; this.headers = []; this.preview = []
    },
    async upload() {
        this.loading = true; this.error = null; this.progress = 0; this.step = 'importing'
        const form = new FormData()
        form.append('file', this.file)
        if (config.chunkSize) form.append('chunk_size', config.chunkSize)
        try {
            const { data } = await axios.post(config.uploadUrl, form, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: e => { this.progress = e.total ? Math.round(e.loaded * 100 / e.total) : 50 },
            })
            this.result = data; this.step = 'done'
        } catch (e) {
            this.error = e.response?.data?.message ?? 'Upload failed'; this.step = 'error'
        } finally { this.loading = false }
    },
})

const dbDataTable = (config = {}) => ({
    rows: [], meta: {}, loading: true,
    page: 1, search: '', sort: null, direction: 'asc',
    // Reactive state, not just the closed-over config: the filters-updated / filters-cleared
    // listeners in the Blade template do Object.assign(params, ...), which needs `params` to
    // exist in the Alpine data scope. Reading config.params directly meant those handlers
    // threw a ReferenceError and the filter bar silently did nothing.
    params: { ...(config.params ?? {}) },
    async init() { await this.load() },
    async load() {
        this.loading = true
        const { data } = await axios.get(config.loadUrl, {
            params: this.queryState(),
        })
        this.rows      = data.data ?? []
        this.meta      = data.meta ?? {}
        this.loading   = false
    },
    // Everything the table is currently showing: page, search, sort and any filters. The one
    // place that decides what "current state" means, shared by load() and stateUrl().
    queryState() {
        return { page: this.page, search: this.search, sort: this.sort, direction: this.direction, ...this.params }
    },
    // Same state as a query string on top of `base`, for toolbar links. Deliberately format
    // agnostic: this component knows nothing about spreadsheets, CSV or PDF — it hands the
    // current state to a URL and that URL decides what to produce.
    stateUrl(base) {
        const query = new URLSearchParams()
        Object.entries(this.queryState()).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') query.append(key, value)
        })
        return `${base}${base.includes('?') ? '&' : '?'}${query.toString()}`
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
    globalError: '',
    success: false,
    loading: false,
    async submit() {
        this.loading = true; this.errors = {}; this.globalError = ''; this.success = false
        try {
            const { data } = await axios({ method: config.method ?? 'POST', url: config.action, data: this.values })
            this.success = true
            if (data.redirect) window.location.href = data.redirect
            else if (config.onSuccess) config.onSuccess(data)
        } catch (e) {
            if (e.response?.status === 422) {
                this.errors = e.response.data.errors ?? {}
            } else {
                this.globalError = e.response?.data?.message ?? e.message ?? 'An error occurred'
            }
        } finally { this.loading = false }
    },
    hasError(field) { return !!this.errors[field] },
    firstError(field) { return this.errors[field]?.[0] ?? '' },
    reset() { this.errors = {}; this.globalError = ''; this.success = false },
})

const dbWizard = (config = {}) => {
    const key = `db_wizard_${config.userId ?? 'guest'}_${config.formId ?? 'wizard'}_v${config.schemaVersion ?? 1}`
    return {
        step: 0, data: {}, errors: {}, globalError: '', loading: false,
        init() {
            const saved = localStorage.getItem(key)
            if (saved) { try { const p = JSON.parse(saved); this.step = p.step ?? 0; this.data = { ...this.data, ...p.data } } catch {} }
        },
        save() { localStorage.setItem(key, JSON.stringify({ step: this.step, data: this.data })) },
        clear() { localStorage.removeItem(key) },
        next() { this.step++; this.errors = {}; this.save() },
        prev() { if (this.step > 0) { this.step--; this.errors = {}; this.save() } },
        async submit() {
            this.loading = true; this.errors = {}; this.globalError = ''
            try {
                const { data } = await axios({ method: config.method ?? 'POST', url: config.action, data: this.data })
                this.clear()
                if (data.redirect) window.location.href = data.redirect
                else if (config.onSuccess) config.onSuccess(data)
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors ?? {}
                } else {
                    this.globalError = e.response?.data?.message ?? e.message ?? 'An error occurred'
                }
            } finally { this.loading = false }
        },
    }
}

const dbKvEditor = (config = {}) => ({
    rows: (config.rows ?? []).map((r, i) => ({ ...r, id: i })),

    init() {
        if (this.rows.length === 0) this.addRow()
    },

    addRow() {
        this.rows.push({ id: Date.now() + Math.random(), key: '', value: '', type: 'number', dirty: false })
    },

    inferType(val) {
        const s = String(val).trim()
        if (s === 'true' || s === 'false') return 'bool'
        if (s !== '' && !isNaN(Number(s))) return 'number'
        return 'string'
    },

    onValueInput(row) {
        if (!row.dirty) row.type = this.inferType(row.value)
    },

    onTypeChange(row) {
        row.dirty = true
        if (row.type === 'bool' && row.value !== 'true' && row.value !== 'false') row.value = 'false'
    },

    castValue(row) {
        const s = String(row.value).trim()
        switch (row.type) {
            case 'number': return s === '' ? 0 : Number(s)
            case 'bool':   return s === 'true'
            case 'object': try { return JSON.parse(s) } catch { return {} }
            default:       return s
        }
    },

    toJson() {
        const obj = {}
        for (const row of this.rows) {
            const k = String(row.key).trim()
            if (k) obj[k] = this.castValue(row)
        }
        return JSON.stringify(obj)
    },
})

const dbListEditor = (config = {}) => ({
    items: config.items ?? [],
    draft: '',

    add() {
        const v = this.draft.trim()
        if (v && !this.items.includes(v)) this.items.push(v)
        this.draft = ''
    },

    remove(i) { this.items.splice(i, 1) },

    onKeydown(e) {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); this.add() }
        if (e.key === 'Backspace' && this.draft === '' && this.items.length > 0) this.items.pop()
    },

    toJson() { return JSON.stringify(this.items) },
})

// ── Registration ───────────────────────────────────────────────────────────

const _all = {
    dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree,
    dbDataTable, dbAutoForm, dbWizard,
    dbSelectRemote, dbResourceDetails, dbTabsLazy, dbSpreadsheetImport,
    dbKvEditor, dbListEditor,
}

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
export {
    dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree,
    dbDataTable, dbAutoForm, dbWizard,
    dbSelectRemote, dbResourceDetails, dbTabsLazy, dbSpreadsheetImport,
    dbKvEditor, dbListEditor,
}
