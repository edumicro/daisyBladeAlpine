'use strict'

import { request, joinUrl, expectJson } from './http.js'

// Every server call of this file goes through request() (see http.js): the client injected with
// registerDaisyBlade(), else window.axios, else fetch.
let injectedDeps = {}
const http = options => request(options, injectedDeps)

// Texts for the user come translated from Blade, inside `config.messages` (e.g. `{ loadFailed: 'No se pudo…' }`).
// `fallback` (English) is for views that do not pass them, such as a copy published before 2.4.
const messageOf = (config, key, fallback) => config.messages?.[key] ?? fallback

// ── localStorage: the `dbl_` keys ───────────────────────────────────────────

/**
 * Reads a localStorage key of the 3.x line (`dbl_sidebar`).
 * The 2.x line wrote the same keys with the prefix `db_` instead of `dbl_`. If only the old key exists,
 * its value is moved to the new key once (and the old key removed), so nobody loses what was saved.
 * `storage` is a parameter so the function can be tested without a browser.
 */
const readStored = (storage, key) => {
    const current = storage.getItem(key)
    if (current !== null) return current
    const legacyKey = key.replace(/^dbl_/, 'db_')
    const legacy = storage.getItem(legacyKey)
    if (legacy === null) return null
    storage.setItem(key, legacy)
    storage.removeItem(legacyKey)
    return legacy
}

// ── Tipo 2: local Alpine state, no server calls ────────────────────────────

// Every view starts its component the same way: `x-data="dblName(@js($config))"`. The factory gets
// the configuration as one object; nothing from PHP is glued into JavaScript text.

// actions.modal: closed at the start; it opens with `dbl-modal-open` (see the view). It takes no configuration.
const dblModal = () => ({
    open: false,
    show() { this.open = true },
    hide() { this.open = false },
    toggle() { this.open = !this.open },
})

// The container of the toasts that come from the order `dbl-toast-show` ({ message, type?, duration? }).
const dblToast = (config = {}) => {
    let sequence = 0    // ids of the toasts: Date.now() repeats when two arrive in the same millisecond, and removing one removed both

    return {
        toasts: [],
        // The order of the window: the duration of the prop is the default of the toasts that do not bring theirs.
        show(detail = {}) {
            this.add(detail.message, detail.type ?? 'info', detail.duration ?? config.duration ?? 4000)
        },
        add(message, type = 'info', duration = 4000) {
            const id = ++sequence
            this.toasts.push({ id, message, type })
            if (duration > 0) setTimeout(() => this.remove(id), duration)
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id)
        },
    }
}

// One toast that is already in the page (`<x-dbl::feedback.toast message="...">`); it can close itself.
const dblToastSingle = (config = {}) => ({
    visible: true,
    init() {
        if (config.autoClose) setTimeout(() => { this.visible = false }, config.duration ?? 4000)
    },
})

// navigation.tabs: tabs whose panels are already in the page. `active` is the index of the open one.
const dblTabs = (config = {}) => ({
    active: config.active ?? 0,
    select(index) { this.active = index },
    isActive(index) { return this.active === index },
})

// navigation.steps: the steps with Back / Next. `current` counts from 1.
const dblSteps = (config = {}) => ({
    current: config.current ?? 1,
    total: config.total ?? 0,
    go(step) {
        this.current = step
        this.$dispatch('dbl-steps-step-changed', { step: this.current })
    },
    prev() { if (this.current > 1) this.go(this.current - 1) },
    next() { if (this.current < this.total) this.go(this.current + 1) },
})

// display.carousel: one slide visible at a time.
const dblCarousel = (config = {}) => ({
    active: 0,
    total: config.total ?? 0,
    go(index) { this.active = index },
    prev() { this.active = (this.active - 1 + this.total) % this.total },
    next() { this.active = (this.active + 1) % this.total },
})

// display.text-rotate: changes the visible text every `duration` ms when `autoRotate` is on.
const dblTextRotate = (config = {}) => {
    let timer = null    // out of the reactive object: Alpine does not need to watch it

    return {
        current: 0,
        texts: config.texts ?? [],
        init() {
            if (config.autoRotate && this.texts.length > 1) timer = setInterval(() => this.next(), config.duration ?? 3000)
        },
        next() { this.current = (this.current + 1) % this.texts.length },
        destroy() { clearInterval(timer) },
    }
}

// form.repeater: a list of rows that the user can add and remove.
// config: { name, rows, emptyRow, min, max }. Announces `dbl-repeater-changed` { name, rows } after every change.
const dblRepeater = (config = {}) => ({
    rows: config.rows ?? [],
    get canRemove() { return !config.min || this.rows.length > config.min },
    get canAdd() { return !config.max || this.rows.length < config.max },
    add() {
        // A copy of the empty row: every new row starts from the defaults of the fields, and none shares an object with another.
        this.rows.push(JSON.parse(JSON.stringify(config.emptyRow ?? {})))
        this.notify()
    },
    remove(index) {
        if (!this.canRemove) return
        this.rows.splice(index, 1)
        this.notify()
    },
    notify() { this.$dispatch('dbl-repeater-changed', { name: config.name, rows: this.rows }) },
})

// form.select with `searchable`: a text box that filters the options (flat { value: label } or grouped { group: { value: label } }).
// config: { name, search (the text to show at the start), options }. Announces `dbl-select-changed` { name, value }.
const dblSelectSearch = (config = {}) => ({
    search: config.search ?? '',
    open: false,
    choosing: false,   // true while choose() writes the label in the text box

    options: config.options ?? {},
    get isGrouped() {
        const first = Object.values(this.options)[0]
        return typeof first === 'object' && first !== null
    },
    // The options whose label or value contains what was typed (the groups that keep none disappear).
    get filtered() {
        if (!this.search) return this.options
        const q = this.search.toLowerCase()
        const matching = items => Object.fromEntries(
            Object.entries(items).filter(([key, label]) => String(label).toLowerCase().includes(q) || String(key).toLowerCase().includes(q)),
        )
        if (!this.isGrouped) return matching(this.options)
        const result = {}
        for (const [group, items] of Object.entries(this.options)) {
            const found = matching(items)
            if (Object.keys(found).length > 0) result[group] = found
        }
        return result
    },
    // One flat list for x-for: a header for each group, then its options.
    get flatList() {
        if (!this.isGrouped) return Object.entries(this.filtered).map(([key, label]) => ({ type: 'option', key, label: String(label) }))
        const list = []
        Object.entries(this.filtered).forEach(([group, items], index) => {
            list.push({ type: 'group', label: group, first: index === 0 })
            Object.entries(items).forEach(([key, label]) => list.push({ type: 'option', key, label: String(label) }))
        })
        return list
    },
    get isEmpty() { return this.flatList.filter(item => item.type === 'option').length === 0 },
    // The text box of this mode. It takes the attributes of the caller (x-model, @input...), so the component finds it by its type.
    get textBox() { return this.$root?.querySelector('input[type="text"]') ?? null },
    // Writes `search` in the text box whenever it changes (the view calls it from an x-effect on the box around the text box).
    showSearch() {
        const box = this.textBox
        if (box && box.value !== this.search) box.value = this.search
    },
    // The user typed in the text box (called by the `input` that rises to the box around it).
    typed(text) {
        this.search = text
        if (this.choosing) return
        // What is typed no longer is the option that was chosen: the form must not keep sending its key.
        if (this.$refs.value && this.$refs.value.value !== '') {
            this.$refs.value.value = ''
            this.$dispatch('dbl-select-changed', { name: config.name ?? '', value: '' })
        }
    },
    // The user chose an option: show its label, write its value in the hidden input (x-ref="value") and announce it.
    choose(item) {
        this.search = item.label
        this.open = false
        // The text box now shows the label. Telling it with an `input` makes the `x-model` or the `@input` of the caller see
        // that text, not the half-word typed before choosing (`choosing` stops typed() from emptying the hidden input).
        const box = this.textBox
        if (box) {
            this.choosing = true
            box.value = item.label
            box.dispatchEvent(new Event('input', { bubbles: true }))
            this.choosing = false
        }
        if (this.$refs.value) this.$refs.value.value = item.key
        this.$dispatch('dbl-select-changed', { name: config.name ?? '', value: item.key })
    },
})

const dblNavbar = (config = {}) => ({
    open: false,
    toggle() { this.open = !this.open },
    close() { this.open = false },
})

// navigation.sidebar: open from 1024px up, and it remembers the last choice of the user in localStorage.
// It takes no configuration: the view passes none.
const dblSidebar = () => ({
    open: window.innerWidth >= 1024,
    init() {
        const saved = readStored(localStorage, 'dbl_sidebar')
        if (saved !== null) this.open = saved === 'true'
    },
    toggle() {
        this.open = !this.open
        localStorage.setItem('dbl_sidebar', this.open)
    },
    close() { this.open = false },
})

const dblSidebarTree = (config = {}) => ({
    expanded: {},
    toggle(key) { this.expanded[key] = !this.expanded[key] },
    isOpen(key) { return !!this.expanded[key] },
})

// ── Tipo 3: Alpine + server calls (through http.js: the injected client, else axios, else fetch) ─────────

// Pure helpers of dblSelectRemote (tested with `node --test tests/js/select-remote.test.mjs`).

/** Whatever we were given (nothing, one value, a list) -> list of texts, without the empty ones. */
const toTexts = value => [].concat(value ?? []).map(String).filter(text => text !== '')

/** Query-string params that carry the value of the control we depend on, under that control's name. */
const dependencyParams = (name, value) => (name ? { [name]: value } : {})

/** The selected values that the options still offer. */
const stillOffered = (selected, options) => selected.filter(text => options.some(option => String(option.value) === text))

/**
 * Select whose options come from a URL (`<x-dbl::form.select load-url="...">`).
 * With `dependsOn` (the `name` of another control) it asks again each time that control changes,
 * sending its value as a param named like the control: `?country_code=ES`.
 */
const dblSelectRemote = (config = {}) => ({
    options: [],
    loading: false,
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

    // The <select> itself. It is NOT inside the element that carries our x-data but right before it, as its sibling
    // (see form/select.blade.php for why), so we look for it in the parent, which holds no other <select>.
    get field() { return this.$root.parentElement.querySelector('select') },

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

    // The window hears the `change` of every control of the page (our <select> is not inside our element, so its `change`
    // does not pass through us). The view listens with `capture`, so this runs before any listener of the user, which
    // may stop the event. Two controls matter to us: our own <select> and the one we depend on.
    onChange(event) {
        if (event.target === this.field) this.remember()
        else this.onDependencyChange(event)
    },

    // Every control of the page fires `change`: reload only for the one we depend on.
    onDependencyChange(event) {
        if (!config.dependsOn) return    // we depend on nothing: a control without a name must not match ''
        if (event.target.name === config.dependsOn && event.target.form === this.field.form) this.reload()
    },

    // Keep the <select> in step with our state. Our element runs this from its `x-effect`, so it runs again
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

    // Remember what the user chose, to know later whether the new options still offer it,
    // and announce it like the other selects do: `dbl-select-changed` with { name, value }.
    remember() {
        const values = [...this.field.selectedOptions].map(option => option.value)
        this.selected = toTexts(values)
        this.$dispatch('dbl-select-changed', { name: config.name ?? '', value: config.multiple ? values : (values[0] ?? '') })
    },

    // The "Retry" button of the error message.
    retry() { return this.reload() },

    // Load the options and drop from the selection whatever they no longer offer.
    async reload() {
        this.waiting = this.dependencyValue() === ''   // empty control: ask for nothing
        if (!await this.loadOptions()) return          // a newer request took over
        const kept = stillOffered(this.selected, this.options)
        if (kept.length === this.selected.length) return   // everything chosen is still offered
        this.selected = kept
        if (!kept.length) this.field.value = ''        // nothing chosen: leave the select empty
        // Tell the others (a third select that depends on this one listens to this event).
        this.field.dispatchEvent(new Event('change', { bubbles: true }))
    },

    // Ask the server for the options. Returns false if there is no usable answer: it failed, or a newer request made it old.
    // (Named loadOptions and not fetch: `fetch` is the global function that http.js falls back to.)
    async loadOptions() {
        // The number of THIS request (a count, not the request() function of http.js): if a newer one starts, ours is old.
        const requestNumber = ++this.lastRequest
        this.loading = true
        this.error = ''
        try {
            const params = { ...(config.params ?? {}), ...dependencyParams(config.dependsOn, this.dependencyValue()) }
            const { data } = this.waiting ? { data: [] } : await http({ url: config.loadUrl, params })
            expectJson(data)
            if (requestNumber !== this.lastRequest) return false
            this.options = Array.isArray(data) ? data : (data.data ?? [])
        } catch (error) {
            // No answer: show no options (better than the ones of the previous value) and leave what was chosen as it is.
            // The user sees a translated message with a retry button; the technical detail goes to the console.
            if (requestNumber === this.lastRequest) {
                this.options = []
                this.showOptions()
                this.error = messageOf(config, 'loadFailed', 'Could not load the options')
            }
            console.error('dblSelectRemote: could not load the options', error)
            return false
        } finally {
            // Only the newest request decides when the loading is over: an old one finishing late must not end it early.
            if (requestNumber === this.lastRequest) this.loading = false
        }
        // The options rendered by the server (the saved value) were only for before the JS: now they come from `options`.
        this.field.querySelectorAll('option[data-initial]').forEach(option => option.remove())
        this.takeModel()
        this.showOptions()
        return true
    },

    // `x-model` on the <select>: Alpine applies the model to a <select> when the MODEL changes, not when <option>s
    // appear. So the first application, which ran before our options existed, found none and chose nothing, and nobody
    // applies it again once they arrive. The model is the truth of an input that has `x-model`, so we take what it holds
    // as what is chosen, and showOptions() marks that one. (With `value` and an empty model the model wins, as on any input.)
    //
    // Alpine has no public way to read the model of an element: `Alpine.evaluate()` needs the TEXT of the expression, and
    // getting it means parsing the attribute by hand (`x-model`, `x-model.number`, `x-model.debounce.500ms`...), which misses
    // a model that `Alpine.bind()` or another library attaches. So this uses `el._x_model`, a `{ get, set }` that the x-model
    // directive leaves on the element whatever its spelling (Alpine's own `x-modelable` reads it too). Checked in Chrome
    // with Alpine 3.14.9. If a future Alpine drops it, `_x_model` is undefined, this does nothing, and the select behaves
    // as it did before this fix.
    takeModel() {
        const model = this.field._x_model?.get()
        if (model !== undefined) this.selected = toTexts(model)
    },

    // Write `options` as <option>s of the <select>, marking the ones in `selected`.
    // They are written by hand and not with a `<template x-for>` because the <select> is outside our x-data (a
    // template inside it could not see `options`). `data-loaded` marks them, to remove them before writing again;
    // the placeholder and the options of the server (`data-initial`) are left alone here.
    showOptions() {
        const field = this.field
        field.querySelectorAll('option[data-loaded]').forEach(option => option.remove())
        for (const { value, label } of this.options) {
            const option = field.ownerDocument.createElement('option')
            option.value = value
            option.textContent = label
            // `defaultSelected` writes the `selected` attribute too, so resetting the form goes back to this choice.
            option.selected = option.defaultSelected = this.selected.includes(String(value))
            option.setAttribute('data-loaded', '')
            field.append(option)
        }
    },
})

const dblResourceDetails = (config = {}) => ({
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
            console.error('dblResourceDetails: could not load the resource', e)
        } finally { this.loading = false }
    },
    refresh() { return this.load() },
})

const dblTabsLazy = (config = {}) => ({
    active: config.active ?? 0,
    tabs: config.tabs ?? [],
    contents: {},
    loading: {},
    errors: {},        // tab index -> message, for the tabs whose content could not be loaded
    async init() { if (this.tabs[this.active]?.loadUrl) await this._load(this.active) },
    async select(index) {
        this.active = index
        // Not yet loaded and not being loaded now (a click on a tab whose request is still running asks nothing new).
        if (!(index in this.contents) && !this.loading[index] && this.tabs[index]?.loadUrl) await this._load(index)
    },
    async _load(index) {
        this.loading = { ...this.loading, [index]: true }
        this.errors = { ...this.errors, [index]: '' }
        try {
            const { data } = await http({ url: this.tabs[index].loadUrl })
            this.contents = { ...this.contents, [index]: typeof data === 'string' ? data : JSON.stringify(data) }
        } catch (error) {
            // A translated message and a retry button in the tab; the technical detail goes to the console.
            this.errors = { ...this.errors, [index]: messageOf(config, 'loadFailed', 'Could not load the content') }
            console.error('dblTabsLazy: could not load the tab', error)
        } finally {
            this.loading = { ...this.loading, [index]: false }
        }
    },
    retry(index) { return this._load(index) },
    isActive(i) { return this.active === i },
    isLoading(i) { return !!this.loading[i] },
    hasError(i) { return !!this.errors[i] },
})

const dblSpreadsheetImport = (config = {}) => ({
    // step: 'upload' (choose the file) → 'confirm' (name and size) → 'importing' → 'done' | 'error'
    file: null, step: 'upload',
    progress: 0, loading: false, error: null, result: null,
    // The browser does not read the file (an .xlsx needs a library to be opened), so there is no preview of its rows.
    handleFile(event) { this.choose(event.target.files[0]) },
    handleDrop(event) { this.choose(event.dataTransfer.files[0]) },
    choose(file) {
        if (!file) return
        this.file = file
        this.step = 'confirm'
    },
    // "12 KB" / "1.4 MB": what the confirmation step shows next to the name.
    get fileSizeText() {
        const bytes = this.file?.size ?? 0
        return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
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
            expectJson(data)    // an HTML answer (the login page of an expired session) is a failure, not "Import complete"
            this.result = data; this.step = 'done'
        } catch (e) {
            this.error = e.response?.data?.message ?? messageOf(config, 'uploadFailed', 'Upload failed'); this.step = 'error'
        } finally { this.loading = false }
    },
})

const dblDataTable = (config = {}) => ({
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
            console.error('dblDataTable: could not load the rows', error)
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
    // The filter panel (form.filters) says which filters are on: they replace the ones it
    // sent before, and the table goes back to page 1. '', null and false mean "no filter".
    applyFilters(filters = {}) {
        this.dropFilters()
        const active = Object.entries(filters ?? {}).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false)
        Object.assign(this.params, Object.fromEntries(active))
        this.filterKeys = active.map(([key]) => key)
        this.page = 1
        return this.load()
    },
    // The filter panel was cleared.
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

const dblAutoForm = (config = {}) => ({
    values: config.values ?? {},
    errors: {},
    globalError: '',
    success: false,
    loading: false,
    async submit() {
        this.loading = true; this.errors = {}; this.globalError = ''; this.success = false
        try {
            const { data } = await http({ method: config.method ?? 'POST', url: config.submitUrl, data: this.values })
            // A text answer is not a success: an expired session redirects to the login page, and an empty `submit-url`
            // posts to the current page. Both come back as a 200 with HTML, and "Saved!" would be a lie.
            expectJson(data)
            this.success = true
            if (data.redirect) window.location.href = data.redirect
            else if (config.onSuccess) config.onSuccess(data)
        } catch (e) {
            if (e.response?.status === 422) {
                this.errors = e.response.data.errors ?? {}
            } else {
                this.globalError = e.response?.data?.message ?? messageOf(config, 'failed', e.message ?? 'An error occurred')
                console.error('dblAutoForm: the form could not be sent', e)
            }
        } finally { this.loading = false }
    },
    hasError(field) { return !!this.errors[field] },
    firstError(field) { return this.errors[field]?.[0] ?? '' },
    reset() { this.errors = {}; this.globalError = ''; this.success = false },
})

const dblWizard = (config = {}) => {
    const key = `dbl_wizard_${config.userId ?? 'guest'}_${config.formId ?? 'wizard'}_v${config.schemaVersion ?? 1}`
    return {
        // The values the Blade gives (`config.values`, those of all the steps) are the starting point of the form...
        step: 0, data: { ...(config.values ?? {}) }, errors: {}, globalError: '', loading: false,
        init() {
            const saved = readStored(localStorage, key)
            // ...and the progress saved for this same wizard goes on top of them: what the user typed is not lost.
            if (saved) { try { const p = JSON.parse(saved); this.step = p.step ?? 0; this.data = { ...this.data, ...p.data } } catch {} }
        },
        save() { localStorage.setItem(key, JSON.stringify({ step: this.step, data: this.data })) },
        clear() { localStorage.removeItem(key) },
        next() { this.step++; this.errors = {}; this.save() },
        prev() { if (this.step > 0) { this.step--; this.errors = {}; this.save() } },
        async submit() {
            this.loading = true; this.errors = {}; this.globalError = ''
            try {
                const { data } = await http({ method: config.method ?? 'POST', url: config.submitUrl, data: this.data })
                expectJson(data)    // a text answer (the login page) is a failure: the saved progress must stay
                this.clear()
                if (data.redirect) window.location.href = data.redirect
                else if (config.onSuccess) config.onSuccess(data)
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors ?? {}
                } else {
                    this.globalError = e.response?.data?.message ?? messageOf(config, 'failed', e.message ?? 'An error occurred')
                    console.error('dblWizard: the form could not be sent', e)
                }
            } finally { this.loading = false }
        },
    }
}

const dblKvEditor = (config = {}) => ({
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

const dblListEditor = (config = {}) => ({
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
    dblModal, dblToast, dblToastSingle, dblTabs, dblSteps, dblCarousel, dblTextRotate, dblRepeater, dblSelectSearch,
    dblNavbar, dblSidebar, dblSidebarTree,
    dblDataTable, dblAutoForm, dblWizard,
    dblSelectRemote, dblResourceDetails, dblTabsLazy, dblSpreadsheetImport,
    dblKvEditor, dblListEditor,
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
    dblModal, dblToast, dblToastSingle, dblTabs, dblSteps, dblCarousel, dblTextRotate, dblRepeater, dblSelectSearch,
    dblNavbar, dblSidebar, dblSidebarTree,
    dblDataTable, dblAutoForm, dblWizard,
    dblSelectRemote, dblResourceDetails, dblTabsLazy, dblSpreadsheetImport,
    dblKvEditor, dblListEditor,
    toTexts, dependencyParams, stillOffered, readStored,
}
