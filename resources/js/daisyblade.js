'use strict'

import { request, joinUrl, expectJson, rejectHtmlPage } from './http.js'

// Every server call of this file goes through request() (see http.js): the client injected with
// registerDaisyBlade(), else window.axios, else fetch.
let injectedDeps = {}
const http = options => request(options, injectedDeps)

// Texts for the user come translated from Blade, inside `config.messages` (e.g. `{ loadFailed: 'No se pudo…' }`).
// `fallback` (English) is for views that do not pass them, such as a copy published before 2.4.
const messageOf = (config, key, fallback) => config.messages?.[key] ?? fallback

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
        if (!config.dependsOn) return null
        const controls = [...(this.field.form ?? document).querySelectorAll(`[name="${config.dependsOn}"]`)]
        const sent = controls.filter(control => !['radio', 'checkbox'].includes(control.type) || control.checked)
        // When several controls send the name (hidden 0 + checked checkbox), the last one wins, as in a submitted form.
        return sent[sent.length - 1]?.value ?? ''
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
            // A user's `x-model` on the <select>: Alpine applied it once, before any <option> existed, and never
            // applies it again when the options arrive. So the model is read here and becomes what is chosen
            // (when it holds a value, it wins over the `value` prop, as on any input with `x-model`).
            // An EMPTY model (null, '' or an empty list) is "nothing typed yet", not a choice: an edit form that
            // starts its model empty keeps the `value` the view wrote, as it did in 2.4.0. And then the model
            // receives that value, so a form that sends the model does not send an empty one.
            const model = this.field._x_model?.get()
            const modelIsEmpty = model === undefined || model === null || model === '' || (Array.isArray(model) && model.length === 0)
            if (!modelIsEmpty) this.selected = toTexts(model)
            else if (this.field._x_model && this.selected.length) this.field._x_model.set(this.field.multiple ? [...this.selected] : this.selected[0])
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
            rejectHtmlPage(data)    // an HTML page (the login page of an expired session) is a failure, not "Import complete"; plain text is still a success
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
}
