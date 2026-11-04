import { request, isCanceled, answerShapes } from '../http.js'
import { withConfig } from '../factoryConfig.js'
import { announceWhenItChanges } from '../modelBinding.js'

/**
 * dblUpload — subida de ficheros para <x-dbl::form.upload>.
 *
 * Cada fichero es un "item" con un estado: 'done' | 'uploading' | 'error'.
 * Los existentes nacen 'done'; los nuevos pasan por validar -> subir -> done/error.
 * Contrato del servidor: ver docs/components/upload.md.
 */
export const dblUpload = withConfig({ uploadUrl: '', existing: [], multiple: false, accept: '', maxFiles: 0, maxSize: 0, deleteUrl: '', reorderUrl: '', messages: {} }, (config, deps = {}) => ({
    items: [],
    dragging: false,
    status: '',          // texto para el aria-live
    seq: 0,

    // El cliente de axios que se inyectó, o el global (puede ser undefined). El componente ya no lo usa
    // —sus peticiones van por request(), que añade fetch/XMLHttpRequest si no hay axios—; se conserva el
    // nombre por si una plantilla de la aplicación lo lee.
    get http() { return deps.axios ?? globalThis.window?.axios },

    init() {
        this.items = config.existing.map(f => this.makeItem({ ...f, status: 'done' }))
        // The ids are the value of the field. There is one hidden input per id, and none when the list is empty, so the field that
        // announces the change is a fixed one, disabled (a form never sends it) and with the same name (modelBinding.js).
        announceWhenItChanges(this, () => this.ids.join(','), () => this.$refs.field)
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
        const files = Array.from(fileList)
        // One decision, taken here: a field of many files adds each one to the list; a field of one takes the first.
        if (config.multiple) files.forEach(file => this.addChecked(file, this.validate(file, this.acceptedCount)))
        else this.replaceWith(files[0])
    },

    // Cuántos ficheros hay ya que cuenten para el máximo (los fallidos no).
    get acceptedCount() { return this.items.filter(i => i.status !== 'error').length },

    // Un solo fichero: el nuevo sustituye al anterior, pero SOLO si llega y vale. Si lo que se suelta no es un fichero (un texto, un
    // enlace) o se rechaza (tipo, tamaño), el guardado se queda: el formulario sigue enviando su id.
    replaceWith(file) {
        if (!file) return
        const error = this.validate(file, 0)   // el que hay se va a sustituir: no cuenta para el máximo
        const toDiscard = error ? this.items.filter(i => i.status === 'error') : [...this.items]
        toDiscard.forEach(i => this.discard(i))
        this.addChecked(file, error)
    },

    // Añade el fichero a la lista: con su error si no vale, o empezando a subirse.
    addChecked(file, error) {
        if (error) {
            this.announce(error)
            this.items.push(this.makeItem({ name: file.name, size: file.size, status: 'error', error }))
            return
        }
        this.items.push(this.makeItem({
            name: file.name, size: file.size, file, status: 'uploading',
            preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        }))
        this.send(this.items[this.items.length - 1])
    },

    // Devuelve un mensaje de error, o '' si el fichero vale. `accepted` es cuántos ficheros cuentan ya para `maxFiles`.
    validate(file, accepted) {
        if (config.maxFiles && accepted >= config.maxFiles)
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
            // Escalera de http.js: axios inyectado, `window.axios` o, si no hay, XMLHttpRequest (fetch no da el progreso).
            const { data: answer } = await request({
                method: 'post',
                url: config.uploadUrl,
                data: body,
                signal: controller.signal,
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: e => { item.progress = e.total ? Math.round(e.loaded * 100 / e.total) : 50 },
            }, deps)
            // An HTML answer (the login page) or one without the `id` (a 204, `{}`) is a failure: the form sends the ids of the files.
            const data = answerShapes.uploadedFile(answer, config.uploadUrl)
            Object.assign(item, { id: data.id, name: data.name ?? item.name, url: data.url,
                                  thumb: data.thumb ?? null, size: data.size ?? item.size,
                                  status: 'done', progress: 100, file: null, cancel: null })
            this.announce(this.t('uploaded', { name: item.name }))
            this.$dispatch('dbl-upload-uploaded', { id: item.id, name: item.name, url: item.url, file: data })
        } catch (e) {
            if (isCanceled(e, deps.axios ?? globalThis.window?.axios)) {
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
            try { await request({ method: 'delete', url: config.deleteUrl.replace('{id}', encodeURIComponent(item.id)), answer: 'json-or-empty' }, deps) }
            catch (e) { item.error = e.response?.data?.message || this.t('delete_failed'); this.announce(item.error); return }
        }
        this.discard(item)
        this.announce(this.t('removed', { name: item.name }))
        this.$dispatch('dbl-upload-removed', { id: item.id, name: item.name })
    },

    async move(item, delta) {
        const from = this.items.indexOf(item), to = from + delta
        if (to < 0 || to >= this.items.length) return
        this.items.splice(to, 0, this.items.splice(from, 1)[0])
        this.announce(this.t('moved', { name: item.name, position: to + 1 }))
        if (!config.reorderUrl) return
        try { await request({ method: 'post', url: config.reorderUrl, data: { ids: this.ids }, answer: 'json-or-empty' }, deps) }
        catch (e) { this.announce(this.t('reorder_failed')) }
    },

    // Ids de los ficheros ya subidos, en orden: lo que van a recoger los inputs name[].
    get ids() { return this.items.filter(i => i.status === 'done' && i.id !== null).map(i => i.id) },

    isImage(item) { return !!(item.preview || item.thumb || /\.(png|jpe?g|gif|webp|svg)$/i.test(item.name)) },
    isPdf(item) { return /\.pdf$/i.test(item.name) },
}))

export function registerUpload(Alpine, deps = {}) {
    Alpine.data('dblUpload', config => dblUpload(config, deps))
}
