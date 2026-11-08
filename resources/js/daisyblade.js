'use strict'

import { request, joinUrl, isCanceled } from './http.js'

// Every server call of this file goes through request() (see http.js): the client injected with
// registerDaisyBlade(), else window.axios, else fetch.
let injectedDeps = {}
const http = options => request(options, injectedDeps)

// Texts for the user come translated from Blade, inside `config.messages` (e.g. `{ loadFailed: 'No se pudo…' }`).
// `fallback` (English) is for views that do not pass them, such as a copy published before 2.4.
const messageOf = (config, key, fallback) => config.messages?.[key] ?? fallback

// What a component that loads JSON (select options, table rows, a resource) does with the body of the answer.
// When the session has expired the server redirects to the login page; axios and fetch follow the redirect and hand
// over its HTML as a normal 200 answer, which read as data looks like an empty list. So a text body is an error: the
// component shows its "could not load" message with Retry. An empty body (a 204) is not text: it still means "nothing".
const expectJson = data => {
    if (typeof data === 'string' && data !== '') throw new Error('Expected JSON but the server sent text (an expired session redirected to the login page?)')
    return data
}

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

// Pure helpers of dbSelectRemote (tested with `node --test tests/js/select-remote.test.mjs`).

/** Whatever we were given (nothing, one value, a list) -> list of texts, without the empty ones. */
const toTexts = value => [].concat(value ?? []).map(String).filter(text => text !== '')

/** Query-string params that carry the value of the control we depend on, under that control's name. */
const dependencyParams = (name, value) => (name ? { [name]: value } : {})

/** The selected values that the options still offer. */
const stillOffered = (selected, options) => selected.filter(text => options.some(option => String(option.value) === text))

/**
 * The value a form would SEND for the control called `name`, looked up in `scope` (a <form>, or the whole document).
 * A group of radios counts only through the checked one ('' if none is), and an unchecked checkbox sends nothing.
 * `form.checkbox` and `form.toggle` write a hidden `0` before the checkbox, so when it is unchecked the value is that 0.
 * When several controls send the name (hidden 0 + checked checkbox), the last one wins, as in a submitted form.
 */
const controlValue = (name, scope) => {
    const controls = [...(scope ?? document).querySelectorAll(`[name="${name}"]`)]
    const sent = controls.filter(control => !['radio', 'checkbox'].includes(control.type) || control.checked)
    return sent[sent.length - 1]?.value ?? ''
}

/**
 * Select whose options come from a URL (`<x-dbl::form.select options-url="...">`).
 * With `dependsOn` (the `name` of another control) it asks again each time that control changes,
 * sending its value as a param named like the control: `?country_code=ES`.
 */
const dbSelectRemote = (config = {}) => ({
    options: [],
    loading: false,
    // `search`, `open` and `value` are left over from a searchable mode that no template uses (see the note above
    // `filtered()`). `selected` is what the component really uses.
    search: '',
    open: false,
    value: config.value ?? null,
    selected: toTexts(config.value),    // what is chosen now, as texts
    disabled: config.disabled ?? false, // the `disabled` prop
    waiting: false,                     // true while the control we depend on is empty
    error: '',                          // message when the options could not be loaded ('' = all fine)
    lastRequest: 0,                     // number of the newest request: older answers are ignored
    disabledByUs: false,                // true while the <select> is disabled because of `waiting` or `disabled`
    starting: null,                     // the first load of init() while it runs (null the rest of the time)

    // Alpine calls init() by itself when the component starts: load the options the first time.
    // A view published with 2.3 also has `x-init="init()"`, so Alpine calls it a second time while the first call is
    // still loading: that second call waits for the first one instead of asking the server again.
    async init() {
        if (this.starting) return this.starting
        this.starting = this.reload()
        try { await this.starting } finally { this.starting = null }
    },

    // The <select> itself. In the current view the Alpine scope is its container (so the error message can sit next
    // to it) and the container holds no other <select>. A view published with 2.3 has the scope on the <select>.
    get field() { return this.$root.matches?.('select') ? this.$root : this.$root.querySelector('select') },

    // Value of the control we depend on (null if we depend on none), looked up by name
    // in our form or, if we are not in a form, in the whole page.
    // It is the value the form would SEND for that name: a group of radios counts only through the checked one
    // ('' if none is), and an unchecked checkbox sends nothing. `form.checkbox` and `form.toggle` write a hidden
    // `0` before the checkbox, so when the checkbox is unchecked the value is that 0.
    dependencyValue() {
        return config.dependsOn ? controlValue(config.dependsOn, this.field.form) : null
    },

    // Every control of the page fires `change`: reload only for the one we depend on.
    onDependencyChange(event) {
        if (event.target.name === config.dependsOn && event.target.form === this.field.form) this.reload()
    },

    // Keep the <select> in step with our state. The container runs this from its `x-effect`, so it runs again
    // whenever `waiting`, `disabled` or `loading` change.
    // The <select> can also carry a `:disabled` of the user (it is passed through as an attribute). So we disable
    // it only when WE need it, and enable it again only if WE were the ones who disabled it: a select that the
    // user's `:disabled` keeps locked must not be unlocked just because the options finished loading.
    syncField() {
        const mustBeDisabled = this.waiting || this.disabled
        if (mustBeDisabled) {
            this.field.disabled = true
            this.disabledByUs = true
        } else if (this.disabledByUs) {
            this.field.disabled = false
            this.disabledByUs = false
        }
        // setAttribute and not the `ariaBusy` property: it works in every browser (the property is newer).
        this.field.setAttribute('aria-busy', String(this.loading))
    },

    // Remember what the user chose, to know later whether the new options still offer it.
    remember() { this.selected = toTexts([...this.field.selectedOptions].map(option => option.value)) },

    // The "Retry" button of the error message.
    retry() { return this.reload() },

    // Load the options and drop from the selection whatever they no longer offer.
    async reload() {
        this.waiting = this.dependencyValue() === ''   // empty control: ask for nothing
        if (!await this.fetch('')) return              // a newer request took over
        const kept = stillOffered(this.selected, this.options)
        if (kept.length === this.selected.length) return   // everything chosen is still offered
        this.selected = kept
        await this.$nextTick()                         // wait until the new options are in the page
        if (!kept.length) this.field.value = ''        // nothing chosen: leave the select empty
        // Tell the others (a third select that depends on this one listens to this event).
        this.field.dispatchEvent(new Event('change', { bubbles: true }))
    },

    // Ask the server for the options. Returns false if there is no usable answer: it failed, or a newer request made it old.
    async fetch(q = '') {
        // The number of THIS request (a count, not the request() function of http.js): if a newer one starts, ours is old.
        const requestNumber = ++this.lastRequest
        this.loading = true
        this.error = ''
        try {
            const params = { ...(q ? { search: q } : {}), ...(config.params ?? {}), ...dependencyParams(config.dependsOn, this.dependencyValue()) }
            // `url` is what the Blade passes; `optionsUrl` is the old name.
            const { data } = this.waiting ? { data: [] } : await http({ url: config.url ?? config.optionsUrl, params })
            expectJson(data)
            if (requestNumber !== this.lastRequest) return false
            this.options = Array.isArray(data) ? data : (data.data ?? [])
        } catch (error) {
            // No answer: show no options (better than the ones of the previous value) and leave what was chosen as it is.
            // The user sees a translated message with a retry button; the technical detail goes to the console.
            if (requestNumber === this.lastRequest) {
                this.options = []
                this.error = messageOf(config, 'loadFailed', 'Could not load the options')
            }
            console.error('dbSelectRemote: could not load the options', error)
            return false
        } finally {
            // Only the newest request decides when the loading is over: an old one finishing late must not end it early.
            if (requestNumber === this.lastRequest) this.loading = false
        }
        // The options rendered by the server (the saved value) were only for before the JS: now they come from `options`.
        this.field.querySelectorAll('option[data-initial]').forEach(option => option.remove())
        return true
    },
    // Left over from a searchable mode that no template uses: `search`, `open`, `value`, `filtered()`, `select()`,
    // `labelFor()` and the `q` parameter of fetch(). They stay in 2.x because someone's own view could call them.
    // 3.0: remove them, and rename fetch() to loadOptions() (it shares its name with the global `fetch` of http.js).
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
            const { data } = await http({ url: config.loadUrl })
            expectJson(data)
            this.resource = data.data ?? data
        } catch (e) {
            this.error = e.response?.data?.message ?? messageOf(config, 'loadFailed', 'Error loading resource')
            console.error('dbResourceDetails: could not load the resource', e)
        } finally { this.loading = false }
    },
    refresh() { return this.load() },
})

const dbTabsLazy = (config = {}) => ({
    active: config.active ?? 0,
    tabs: config.tabs ?? [],
    contents: {},
    loading: {},
    errors: {},        // tab index -> message, for the tabs whose content could not be loaded
    async init() { if (this.tabs[this.active]?.url) await this._load(this.active) },
    async select(index) {
        this.active = index
        // Not yet loaded and not being loaded now (a click on a tab whose request is still running asks nothing new).
        if (!(index in this.contents) && !this.loading[index] && this.tabs[index]?.url) await this._load(index)
    },
    async _load(index) {
        this.loading = { ...this.loading, [index]: true }
        this.errors = { ...this.errors, [index]: '' }
        try {
            const { data } = await http({ url: this.tabs[index].url })
            this.contents = { ...this.contents, [index]: typeof data === 'string' ? data : JSON.stringify(data) }
        } catch (error) {
            // A translated message and a retry button in the tab; the technical detail goes to the console.
            this.errors = { ...this.errors, [index]: messageOf(config, 'loadFailed', 'Could not load the content') }
            console.error('dbTabsLazy: could not load the tab', error)
        } finally {
            this.loading = { ...this.loading, [index]: false }
        }
    },
    retry(index) { return this._load(index) },
    isActive(i) { return this.active === i },
    isLoading(i) { return !!this.loading[i] },
    hasError(i) { return !!this.errors[i] },
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
            const { data } = await http({
                method: 'post',
                url: config.uploadUrl,
                data: form,
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: e => { this.progress = e.total ? Math.round(e.loaded * 100 / e.total) : 50 },
            })
            this.result = data; this.step = 'done'
        } catch (e) {
            this.error = e.response?.data?.message ?? messageOf(config, 'uploadFailed', 'Upload failed'); this.step = 'error'
        } finally { this.loading = false }
    },
})

const dbDataTable = (config = {}) => ({
    rows: [], meta: {}, loading: true,
    error: '',          // message when the rows could not be loaded ('' = all fine)
    lastRequest: 0,     // number of the newest load(): an older answer arriving late is ignored
    page: 1, search: '', sort: null, direction: 'asc',
    // Reactive state, not just the closed-over config: the filter listeners in the Blade template
    // (applyFilters / clearFilters below) change `params`, which must exist in the Alpine data scope.
    // Reading config.params directly meant those handlers threw a ReferenceError and the filter
    // bar silently did nothing.
    params: { ...(config.params ?? {}) },
    filterKeys: [],     // the params that came from a filter bar, so that changing or clearing the filters removes them
    async init() { await this.load() },
    async load() {
        // The number of THIS load (a count, not the request() function of http.js): if a newer one starts, ours is old.
        const requestNumber = ++this.lastRequest
        this.loading = true
        this.error = ''
        try {
            const { data } = await http({ url: config.loadUrl, params: this.queryState() })
            expectJson(data)
            if (requestNumber !== this.lastRequest) return    // a newer load() took over: this answer is old
            this.rows = data.data ?? []
            this.meta = data.meta ?? {}
        } catch (error) {
            if (requestNumber !== this.lastRequest) return
            // Empty the table (rows of another search or page would mislead) and offer a retry; the detail goes to the console.
            this.rows = []
            this.meta = {}
            this.error = messageOf(config, 'loadFailed', 'Could not load the data')
            console.error('dbDataTable: could not load the rows', error)
        } finally {
            // Whatever happened, the newest load() ends the loading: the spinner never stays on forever.
            if (requestNumber === this.lastRequest) this.loading = false
        }
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
        const filled = Object.entries(this.queryState()).filter(([, value]) => value !== null && value !== undefined && value !== '')
        // Same joiner and same list format as the request of load(), so the export gets what the table asked for.
        return joinUrl(base, Object.fromEntries(filled))
    },
    // A filter bar (display.filters or form.filter) says which filters are on: they replace the ones it
    // sent before, and the table goes back to page 1. '', null and false mean "no filter".
    applyFilters(filters = {}) {
        this.dropFilters()
        const active = Object.entries(filters ?? {}).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false)
        Object.assign(this.params, Object.fromEntries(active))
        this.filterKeys = active.map(([key]) => key)
        this.page = 1
        return this.load()
    },
    // The filter bar was cleared.
    clearFilters() {
        this.dropFilters()
        this.page = 1
        return this.load()
    },
    // Remove the params the filters added. If one had the name of a fixed param (the `params` prop),
    // that fixed value comes back instead of disappearing.
    dropFilters() {
        const fixed = config.params ?? {}
        for (const key of this.filterKeys) {
            if (key in fixed) this.params[key] = fixed[key]
            else delete this.params[key]
        }
        this.filterKeys = []
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
            const { data } = await http({ method: config.method ?? 'POST', url: config.action, data: this.values })
            this.success = true
            if (data.redirect) window.location.href = data.redirect
            else if (config.onSuccess) config.onSuccess(data)
        } catch (e) {
            if (e.response?.status === 422) {
                this.errors = e.response.data.errors ?? {}
            } else {
                this.globalError = e.response?.data?.message ?? messageOf(config, 'failed', e.message ?? 'An error occurred')
                console.error('dbAutoForm: the form could not be sent', e)
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
                const { data } = await http({ method: config.method ?? 'POST', url: config.action, data: this.data })
                this.clear()
                if (data.redirect) window.location.href = data.redirect
                else if (config.onSuccess) config.onSuccess(data)
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors ?? {}
                } else {
                    this.globalError = e.response?.data?.message ?? messageOf(config, 'failed', e.message ?? 'An error occurred')
                    console.error('dbWizard: the form could not be sent', e)
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


// ── Combobox: the widget of `form.select` with `searchable` and with `search-url` ──────────────────────────────

// Pure helpers (tested with `node --test tests/js/combobox.test.mjs`).

/** Query-string params of one search: the fixed ones, the control we depend on, the text typed and the limit. */
const searchParams = ({ queryParam = 'q', query = '', limit = null, params = {}, dependsOn = '', dependencyValue = null, page = 1, pageParam = 'page' } = {}) => ({
    ...params,
    ...dependencyParams(dependsOn, dependencyValue),
    [queryParam || 'q']: query,
    ...(limit ? { limit } : {}),
    ...(page > 1 ? { [pageParam || 'page']: page } : {}),   // the first page carries no `page`
})

/**
 * What the server answered -> list of `{ id, text, row }` (`row` is the whole row, with its extra fields).
 * The vocabulary of a <select>: a map `{"12": "Paracetamol"}` (what `pluck('name', 'id')` gives), rows `{id, text}`,
 * rows `{value, label}`, and the select2 wrapper `{"results": [...]}`. A map keeps only the entries whose value is
 * a text or a number. A body that is not an object or an array (empty, a number) is not an answer: it throws.
 * Anything that really needs flattening goes through `process-results`.
 */
const toRows = (data, { optionLabel = '' } = {}) => {
    if (data === null || typeof data !== 'object') throw new Error('The answer is not a list of results')
    const list = Array.isArray(data) ? data : (Array.isArray(data.results) ? data.results : null)
    if (!list) {
        return Object.entries(data)
            .filter(([, text]) => typeof text === 'string' || typeof text === 'number')
            .map(([id, text]) => ({ id, text: String(text), row: { id, text } }))
    }
    return list.flatMap(row => {
        if (row === null || typeof row !== 'object') return []
        const id = row.id ?? row.value
        if (id === null || id === undefined) return []
        return [{ id: String(id), text: String((optionLabel ? row[optionLabel] : undefined) ?? row.text ?? row.label ?? row.name ?? id), row }]
    })
}

/**
 * Can there be more rows after this page? The server's word first (`pagination: { more: true|false }`); if it says
 * nothing, by counting: a page as long as `limit` may have a next one, a shorter (or empty) one is the last.
 * The price of counting is one empty request when the total is an exact multiple of `limit`.
 */
const hasMore = (body, count, limit) => {
    const said = body?.pagination?.more
    if (typeof said === 'boolean') return said
    return !!limit && count >= limit
}

/**
 * From the body of the answer to the rows to paint. `processResults(body)` (the `process-results` prop) gets the body
 * exactly as it came and returns the rows (`{id, text, ...}`, or any shape `toRows` reads); when there is one,
 * it decides and `optionLabel` is not used. `optionLabel` (the `option-label` prop) names the field that is the text.
 */
const readResults = (body, { processResults = null, optionLabel = '' } = {}) =>
    processResults ? toRows(processResults(body)) : toRows(body, { optionLabel })

/** What `select-error` gets to know about a failure: the status and the body of the answer, as in axios (`error.response`). */
const errorInfo = error => ({ status: error?.response?.status ?? null, data: error?.response?.data ?? null, message: error?.message ?? '' })

/** The `options` of the searchable select (`{k: v}` or `{group: {k: v}}`) -> list of `{ id, text, row, group }`. */
const localRows = options => Object.entries(options ?? {}).flatMap(([key, value]) => {
    if (value !== null && typeof value === 'object') {
        return Object.entries(value).map(([id, text]) => ({ id, text: String(text), row: { id, text: String(text) }, group: key }))
    }
    return [{ id: key, text: String(value), row: { id: key, text: String(value) }, group: '' }]
})

/** The rows whose text or id contain what was typed (browser-side filter of the local mode). */
const filterRows = (rows, query) => {
    const q = String(query ?? '').trim().toLowerCase()
    return q ? rows.filter(row => row.text.toLowerCase().includes(q) || row.id.toLowerCase().includes(q)) : rows
}

/**
 * Tells which request is the newest, so an old answer is thrown away. `begin()` starts a request (and aborts the one
 * in flight); `isCurrent(number)` says whether it is still the newest; `cancel()` makes every one of them old.
 */
const latestRequest = () => {
    let count = 0
    let controller = null
    return {
        begin() {
            controller?.abort()
            controller = new AbortController()
            return { number: ++count, signal: controller.signal }
        },
        isCurrent: number => number === count,
        cancel() {
            controller?.abort()
            controller = null
            count++
        },
    }
}

/**
 * The input + hidden + list of `<x-dbl::form.select searchable>` and `<x-dbl::form.select search-url="...">`.
 * Local mode filters `config.options` in the browser; remote mode asks `config.searchUrl` what to show while the
 * person types (debounced, one request at a time). Same keyboard, same ARIA, same look.
 */
const dbCombobox = (config = {}) => {
    const remote = !!config.searchUrl
    const minChars = remote ? (config.minChars ?? 2) : 0
    const tracker = latestRequest()   // outside the reactive object: an AbortController must not be wrapped in a Proxy
    let timer = null
    const startValue = String(config.value ?? '')
    const startText = config.text ?? ''
    const allRows = remote ? [] : localRows(config.options)

    return {
        query: startText,
        value: startValue,                // what the hidden sends
        committed: startValue !== '' ? { id: startValue, text: startText } : null,   // the last real choice
        announced: startValue,            // the value the others (x-model, a dependent select) know about
        touched: false,                   // the person has typed
        open: false,
        active: -1,                       // index of the highlighted option (-1: none)
        results: [],                      // remote: what the server answered
        searched: false,                  // remote: `results` answers the current query
        page: 1,                          // remote: the last page that arrived
        more: false,                      // remote: there may be a next page (shows the "more" button)
        loadingMore: false,
        failedMore: false,                // the last failure was the next page (Retry asks for that page again)
        loading: false,
        error: '',
        waiting: false,                   // remote: the control we depend on is empty
        disabled: config.disabled ?? false,
        disabledByUs: false,

        init() {
            this.waiting = !!config.dependsOn && this.dependencyValue() === ''
            this.syncHidden()
        },

        get field() { return this.$refs.field },
        get rows() { return remote ? this.results : filterRows(allRows, this.query) },
        // What the list shows: the options, and for grouped options a header before each group.
        get items() {
            const items = []
            let group = ''
            this.rows.forEach((row, index) => {
                if (row.group && row.group !== group) items.push({ key: `g:${row.group}`, header: true, text: row.group })
                group = row.group ?? ''
                items.push({ key: `o:${row.id}`, header: false, row, index })
            })
            return items
        },
        get statusText() {
            const messages = config.messages ?? {}
            if (!remote) return this.rows.length ? '' : (messages.noResults ?? 'No results')
            if (this.query.trim().length < minChars) return messages.minChars ?? `Type ${minChars} or more characters`
            if (this.loading && !this.rows.length) return messages.searching ?? 'Searching…'
            if (this.error) return this.error
            if (this.searched && !this.rows.length) return messages.noResults ?? 'Nothing matches that'
            return ''
        },
        get listOpen() { return this.open && !this.waiting && !this.disabled && (this.rows.length > 0 || this.statusText !== '') },
        optionId(index) { return `${config.id}-opt-${index}` },

        // Keep the input and the hidden in step with our state (the container runs this from its `x-effect`).
        // Same care as dbSelectRemote: disable only when WE need it, and enable only what WE disabled.
        syncField() {
            const mustBeDisabled = this.waiting || this.disabled
            if (mustBeDisabled) {
                this.field.disabled = true
                this.$refs.hidden.disabled = true
                this.disabledByUs = true
            } else if (this.disabledByUs) {
                this.field.disabled = false
                this.$refs.hidden.disabled = false
                this.disabledByUs = false
            }
            this.field.setAttribute('aria-expanded', String(this.listOpen))
            this.field.setAttribute('aria-busy', String(this.loading))
            if (this.listOpen && this.active >= 0 && (this.rows[this.active] || (this.more && this.active === this.rows.length))) this.field.setAttribute('aria-activedescendant', this.optionId(this.active))
            else this.field.removeAttribute('aria-activedescendant')
        },

        // Forget the results (and the pagination): another query, a short one, or another value of the control we depend on.
        resetResults() {
            this.results = []
            this.searched = false
            this.page = 1
            this.more = false
            this.error = ''
            this.failedMore = false
            this.loading = false
            this.loadingMore = false
        },

        syncHidden() { this.$refs.hidden.value = this.value },

        // Tell the others the value changed: x-model and a select that depends on this one listen to these.
        announce() {
            if (this.value === this.announced) return
            this.announced = this.value
            this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }))
            this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }))
        },

        dependencyValue() {
            return config.dependsOn ? controlValue(config.dependsOn, this.field.form) : null
        },

        // Every control of the page fires `change`: react only to the one we depend on.
        onDependencyChange(event) {
            if (event.target.name !== config.dependsOn || event.target.form !== this.field.form) return
            tracker.cancel()
            clearTimeout(timer)
            this.waiting = this.dependencyValue() === ''
            this.committed = null
            this.value = ''
            this.query = ''
            this.field.value = ''
            this.resetResults()
            this.syncHidden()
            this.announce()
            if (this.open && !this.waiting && minChars === 0) this.search()
        },

        // ── The person ──

        onFocus(event) {
            if (event.target !== this.field || this.disabled || this.waiting) return
            if (remote && this.value !== '') return   // something is chosen: wait until they type or press ↓
            this.open = true
            if (remote && minChars === 0 && !this.searched) this.search()
        },

        onInput(event) {
            if (event.target !== this.field) return
            this.touched = true
            this.query = this.field.value
            this.value = ''   // typing un-chooses: the id travels only for a real choice (free text travels in the `_text` field)
            this.syncHidden()
            this.open = true
            this.active = -1
            if (!remote) return
            clearTimeout(timer)
            this.searched = false
            if (this.loadingMore) { tracker.cancel(); this.loadingMore = false }   // the next page of the OLD text is of no use now
            if (this.query.trim().length < minChars) {
                tracker.cancel()
                this.resetResults()
                return
            }
            timer = setTimeout(() => this.search(), config.debounce ?? 250)
        },

        onKeydown(event) {
            if (event.target !== this.field) return
            // The "more" button is one more stop after the last row.
            const count = this.rows.length + (this.more ? 1 : 0)
            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowUp':
                    event.preventDefault()
                    if (!this.open) {
                        this.open = true
                        if (remote && !this.searched && this.query.trim().length >= minChars) this.search()
                        break
                    }
                    if (!count) break
                    if (event.key === 'ArrowDown') this.active = (this.active + 1) % count
                    else this.active = this.active <= 0 ? count - 1 : this.active - 1
                    this.$nextTick(() => document.getElementById(this.optionId(this.active))?.scrollIntoView({ block: 'nearest' }))
                    break
                case 'Enter':
                    // Only when an option is highlighted: otherwise Enter does what it always does in a form.
                    if (this.open && this.rows[this.active]) {
                        event.preventDefault()
                        this.choose(this.rows[this.active])
                    } else if (this.open && this.more && this.active === this.rows.length) {
                        event.preventDefault()
                        this.loadMore()
                    }
                    break
                case 'Escape':
                    if (this.open) {
                        event.preventDefault()
                        event.stopPropagation()   // do not also close the modal the field is in
                        this.close()
                    }
                    break
                case 'Tab':
                    this.close()   // and let the focus go on
                    break
            }
        },

        onFocusOut(event) {
            if (event.relatedTarget && !this.$root.contains(event.relatedTarget)) this.close()
        },

        hover(index) { if (this.active !== index) this.active = index },

        choose(row) {
            this.committed = { id: row.id, text: row.text }
            this.value = row.id
            this.query = row.text
            this.field.value = row.text
            this.touched = false
            this.open = false
            this.active = -1
            this.searched = false
            this.syncHidden()
            this.announce()
            const detail = { name: config.name ?? '', value: row.id, row: row.row }
            this.$root.dispatchEvent(new CustomEvent('select-change', { detail, bubbles: true, composed: true }))
        },

        // Close the list. What was typed but not chosen goes back to the last choice (or to nothing), unless free text is allowed.
        close() {
            this.open = false
            this.active = -1
            if (!this.touched) { this.announce(); return }
            if (config.allowFreeText) {   // what was typed stays as it is: the id is empty and the text travels in `{name}_text`
                this.committed = null
                this.announce()
                return
            }
            if (this.query.trim() === '') this.committed = null
            this.query = this.committed?.text ?? ''
            this.value = this.committed?.id ?? ''
            this.field.value = this.query
            this.touched = false
            this.syncHidden()
            this.announce()
        },

        // ── The server ──

        retry() { return this.failedMore ? this.loadMore() : this.search() },

        // The search failed. Announce it with the cancelable `select-error` event ({ name, error: { status, data, message } }):
        // if a listener cancels it (preventDefault) the component stays quiet and the consumer paints what they want;
        // otherwise the translated message with "Retry" shows. Nothing here can throw.
        failed(error) {
            let quiet = false
            try {
                const detail = { name: config.name ?? '', error: errorInfo(error) }
                quiet = !this.$root.dispatchEvent(new CustomEvent('select-error', { detail, bubbles: true, composed: true, cancelable: true }))
            } catch (failure) { console.error('dbCombobox: a select-error listener failed', failure) }
            if (!quiet) this.error = messageOf(config, 'searchFailed', 'Could not search')
        },

        // The first page of a search, or the next one (`loadMore`, the "more" button), which is added to the rows already there.
        search() { return this.fetchPage(1) },
        loadMore() { return this.more && !this.loading && !this.loadingMore ? this.fetchPage(this.page + 1) : undefined },

        async fetchPage(page) {
            clearTimeout(timer)
            const query = this.query.trim()
            if (!remote || this.waiting || query.length < minChars) return
            const append = page > 1
            const { number, signal } = tracker.begin()
            this.error = ''
            this.failedMore = false
            if (append) this.loadingMore = true
            else { this.loading = true; this.loadingMore = false; this.more = false }
            try {
                const params = searchParams({
                    queryParam: config.queryParam, query, limit: config.limit, params: config.params ?? {},
                    dependsOn: config.dependsOn, dependencyValue: this.dependencyValue(), page, pageParam: config.pageParam,
                })
                const { data } = await http({ url: config.searchUrl, params, signal })
                expectJson(data)
                if (!tracker.isCurrent(number)) return   // a newer search took over
                const process = config.processResults
                    ? body => window.Alpine.evaluate(this.$root, config.processResults, { scope: { data: body } })
                    : null
                const rows = readResults(data, { processResults: process, optionLabel: config.optionLabel })
                if (append) {
                    const known = new Set(this.results.map(row => row.id))
                    this.active = this.results.length   // the keyboard goes on from the first new row
                    this.results = [...this.results, ...rows.filter(row => !known.has(row.id))]
                } else {
                    this.results = rows
                    this.active = -1
                }
                this.page = page
                this.more = hasMore(data, rows.length, config.limit)
                this.searched = true
            } catch (error) {
                // A canceled request is not a failure (every keystroke cancels the one in flight), and an old answer
                // that fails late says nothing about the current search: both are dropped in silence.
                if (!tracker.isCurrent(number) || isCanceled(error, injectedDeps.axios ?? globalThis.window?.axios)) return
                // `value`, `query` and `committed` (what is already chosen) are never touched. A failed first page leaves no
                // results; a failed next page keeps the rows already there and the "more" button.
                if (append) this.failedMore = true
                else { this.results = []; this.searched = false; this.page = 1 }
                console.error('dbCombobox: could not search', error)
                this.failed(error)
            } finally {
                if (tracker.isCurrent(number)) { this.loading = false; this.loadingMore = false }
            }
        },
    }
}

// ── Registration ───────────────────────────────────────────────────────────

const _all = {
    dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree,
    dbDataTable, dbAutoForm, dbWizard,
    dbSelectRemote, dbResourceDetails, dbTabsLazy, dbSpreadsheetImport,
    dbKvEditor, dbListEditor, dbCombobox,
}

function _register(Alpine) {
    for (const [name, fn] of Object.entries(_all)) Alpine.data(name, fn)
}

/**
 * Optional: hand the components the HTTP client of the app, before `Alpine.start()`.
 *     registerDaisyBlade(Alpine, { axios })
 * Without it they use `window.axios` and, if there is none, `fetch`.
 */
function registerDaisyBlade(Alpine, deps = {}) {
    injectedDeps = deps
    _register(Alpine)
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
    registerDaisyBlade,
    dbModal, dbToast, dbTabs, dbNavbar, dbSidebar, dbSidebarTree,
    dbDataTable, dbAutoForm, dbWizard,
    dbSelectRemote, dbResourceDetails, dbTabsLazy, dbSpreadsheetImport,
    dbKvEditor, dbListEditor,
    toTexts, dependencyParams, stillOffered,
    dbCombobox, searchParams, toRows, localRows, filterRows, latestRequest, readResults, errorInfo, hasMore,
}
