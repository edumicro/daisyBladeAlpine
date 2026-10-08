/**
 * dbUpload — subida de ficheros para <x-dbl::form.upload>.
 *
 * Cada fichero es un "item" con un estado: 'done' | 'uploading' | 'error'.
 * Los existentes nacen 'done'; los nuevos pasan por validar -> subir -> done/error.
 * Contrato del servidor: ver docs/components/upload.md.
 */
export const dbUpload = (config = {}, deps = {}) => ({
    items: [],
    dragging: false,
    status: '',          // texto para el aria-live
    seq: 0,

    // axios se inyecta; si no, el global de la página.
    get http() { return deps.axios ?? window.axios },

    init() {
        this.items = (config.existing ?? []).map(f => this.makeItem({ ...f, status: 'done' }))
    },

    makeItem(data) {
        return { key: ++this.seq, id: null, name: '', url: null, thumb: null, size: 0,
                 status: 'done', progress: 0, error: '', file: null, preview: null,
                 cancel: null, ...data }
    },

    // ── Texto ────────────────────────────────────────────────────────────
    t(key, vars = {}) {
        let s = config.messages?.[key] ?? key
        for (const [k, v] of Object.entries(vars)) s = s.replace(':' + k, v)
        return s
    },
    announce(msg) { this.status = msg },

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B'
        if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB'
        return (bytes / 1048576).toFixed(1) + ' MB'
    },

    // ── Entrada de ficheros ──────────────────────────────────────────────
    onPick(event) { this.add(event.target.files); event.target.value = '' },
    onDrop(event) { this.dragging = false; this.add(event.dataTransfer.files) },

    add(fileList) {
        let files = Array.from(fileList)
        if (!config.multiple) {
            // Un solo fichero: el nuevo sustituye al anterior.
            [...this.items].forEach(i => this.discard(i))
            files = files.slice(0, 1)
        }
        for (const file of files) {
            const error = this.validate(file)
            if (error) { this.announce(error); this.items.push(this.makeItem({ name: file.name, size: file.size, status: 'error', error })); continue }
            const item = this.makeItem({
                name: file.name, size: file.size, file, status: 'uploading',
                preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            })
            this.items.push(item)
            this.send(this.items[this.items.length - 1])
        }
    },

    // Devuelve un mensaje de error, o '' si el fichero vale.
    validate(file) {
        if (config.maxFiles && this.items.filter(i => i.status !== 'error').length >= config.maxFiles)
            return this.t('too_many', { max: config.maxFiles })
        if (config.maxSize && file.size > config.maxSize * 1048576)
            return this.t('too_big', { name: file.name, max: config.maxSize })
        if (!this.accepts(file)) return this.t('bad_type', { name: file.name })
        return ''
    },

    // `accept` admite extensiones (.pdf), tipos (application/pdf) y comodines (image/*).
    accepts(file) {
        if (!config.accept) return true
        const name = file.name.toLowerCase()
        return config.accept.split(',').map(a => a.trim().toLowerCase()).filter(Boolean).some(a =>
            a.startsWith('.') ? name.endsWith(a)
            : a.endsWith('/*') ? file.type.startsWith(a.slice(0, -1))
            : file.type === a)
    },

    // ── Subida ───────────────────────────────────────────────────────────
    async send(item) {
        const body = new FormData()
        body.append('file', item.file)
        const controller = new AbortController()
        Object.assign(item, { status: 'uploading', progress: 0, error: '', cancel: () => controller.abort() })
        try {
            const { data } = await this.http.post(config.action, body, {
                signal: controller.signal,
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: e => { item.progress = e.total ? Math.round(e.loaded * 100 / e.total) : 50 },
            })
            Object.assign(item, { id: data.id, name: data.name ?? item.name, url: data.url,
                                  thumb: data.thumb ?? null, size: data.size ?? item.size,
                                  status: 'done', progress: 100, file: null, cancel: null })
            this.announce(this.t('uploaded', { name: item.name }))
            this.$dispatch('uploaded', { id: item.id, name: item.name, url: item.url, file: data })
        } catch (e) {
            if (this.http.isCancel?.(e) || e.code === 'ERR_CANCELED') {
                item.error = this.t('cancelled')
            } else {
                // 422 de Laravel: { message, errors: { file: ['...'] } }
                const errors = e.response?.data?.errors
                item.error = (errors && Object.values(errors).flat()[0]) || e.response?.data?.message || this.t('failed')
            }
            Object.assign(item, { status: 'error', cancel: null })
            this.announce(item.error)
        }
    },

    cancel(item) { item.cancel?.() },
    retry(item) { if (item.file) this.send(item) },

    // ── Quitar, borrar y ordenar ─────────────────────────────────────────
    // Quita de la lista y libera la URL de la vista previa.
    discard(item) {
        item.cancel?.()
        if (item.preview) URL.revokeObjectURL(item.preview)
        this.items = this.items.filter(i => i !== item)
    },

    // Fallido o sin terminar: solo se descarta. Subido: se borra en el servidor, con confirmación.
    async remove(item) {
        if (item.id === null) return this.discard(item)
        if (!window.confirm(this.t('confirm_delete', { name: item.name }))) return
        if (config.deleteUrl) {
            try { await this.http.delete(config.deleteUrl.replace('{id}', encodeURIComponent(item.id))) }
            catch (e) { item.error = e.response?.data?.message || this.t('delete_failed'); this.announce(item.error); return }
        }
        this.discard(item)
        this.announce(this.t('removed', { name: item.name }))
        this.$dispatch('removed', { id: item.id, name: item.name })
    },

    async move(item, delta) {
        const from = this.items.indexOf(item), to = from + delta
        if (to < 0 || to >= this.items.length) return
        this.items.splice(to, 0, this.items.splice(from, 1)[0])
        this.announce(this.t('moved', { name: item.name, position: to + 1 }))
        if (!config.reorderUrl) return
        try { await this.http.post(config.reorderUrl, { ids: this.ids }) }
        catch (e) { this.announce(this.t('reorder_failed')) }
    },

    // Ids de los ficheros ya subidos, en orden: lo que van a recoger los inputs name[].
    get ids() { return this.items.filter(i => i.status === 'done' && i.id !== null).map(i => i.id) },

    isImage(item) { return !!(item.preview || item.thumb || /\.(png|jpe?g|gif|webp|svg)$/i.test(item.name)) },
    isPdf(item) { return /\.pdf$/i.test(item.name) },
})

export function registerUpload(Alpine, deps = {}) {
    Alpine.data('dbUpload', config => dbUpload(config, deps))
}
