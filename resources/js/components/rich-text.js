'use strict'

/**
 * Editor de texto enriquecido (Tiptap 3).
 *
 * Tiptap NO se importa aquí: lo inyecta la aplicación (ver docs/components/rich-text.md).
 * Así el paquete no fija versiones ni obliga a instalar extensiones que no se usan, y un botón
 * de la barra solo aparece si su extensión viene en `deps`.
 *
 *   deps = { Editor, StarterKit, Link?, Image?, Table?, TableRow?, TableHeader?, TableCell?,
 *            Placeholder?, axios? }
 */

import { request, answerShapes } from '../http.js'
import { withConfig } from '../factoryConfig.js'
import { announceValue, modelBinding, isEmptyValue } from '../modelBinding.js'

// Solo se aceptan enlaces http(s) y mailto. El servidor debe volver a comprobarlo al sanear.
const SAFE_URL = /^(https?:\/\/|mailto:)/i

export const dblRichText = withConfig({
    name: '', label: '', placeholder: '', maxLength: 0, minimal: false, required: false, uploadUrl: '', labels: {},
}, (config, deps = {}) => {
    // El editor vive en el cierre, NO en `this`: Alpine envolvería el editor en un Proxy
    // y Tiptap dejaría de funcionar bien.
    let editor = null
    const labels = config.labels

    return {
        buttons: [],      // se construye una vez en init(), cuando el editor ya existe
        tick: 0,          // sube en cada transacción para que Alpine repinte los estados activos
        length: 0,        // caracteres de texto (sin etiquetas)
        uploading: false,
        error: '',
        model: '',        // the model of the caller (`x-model` through the `x-modelable` of the view): the HTML.
        blocked: '',      // why the form was not sent ('' = it was, or nothing was tried): `required` or `maxLength`
        owningForm: null,   // not `form`: the `x-model` of the caller is read in this scope, and `form.body` must not find our element
        onSubmit: null,

        init() {
            const extensions = [
                // En Tiptap 3 StarterKit ya trae Link; se desactiva para que solo exista si
                // el consumidor lo inyecta (y así el botón y la extensión van siempre juntos).
                deps.StarterKit.configure({ link: false }),
            ]
            if (deps.Link) extensions.push(deps.Link.configure({ openOnClick: false }))
            if (deps.Image) extensions.push(deps.Image)
            if (deps.Table) {
                extensions.push(deps.Table, deps.TableRow, deps.TableHeader, deps.TableCell)
            }
            if (deps.Placeholder && config.placeholder) {
                extensions.push(deps.Placeholder.configure({ placeholder: config.placeholder }))
            }

            editor = new deps.Editor({
                element: this.$refs.editor,
                extensions,
                content: this.$refs.input.value,   // HTML inicial, escrito por Blade en el hidden
                editorProps: {
                    attributes: {
                        class: 'dbl-rich-text-content',
                        role: 'textbox',
                        'aria-multiline': 'true',
                        'aria-label': config.label || labels.editor || '',
                    },
                },
                onTransaction: () => { this.tick++ },
                onUpdate: () => { this.sync(); this.announce() },
            })
            this.buttons = this.buildButtons()
            this.sync()
            this.guardTheForm()
            // The model of the caller (x-modelable) exists once the whole component has started.
            this.$nextTick(() => this.followTheModel())
        },

        // The rule of modelBinding.js for a control with a model: at the start the model wins if it holds something, and if it is
        // empty the HTML of the view is shown and the model receives it; later, a change of the model from outside is always shown.
        followTheModel() {
            const binding = modelBinding({ get: () => this.model, set: html => { this.model = html } })
            const shown = binding.reconcile(this.$refs.input.value)
            this.showHtml(shown)
            binding.observe(this.$watch.bind(this), html => {
                if ((html ?? '') !== this.$refs.input.value) this.showHtml(html)   // the user's own typing comes back as the model: not a change
            })
        },

        // Puts HTML in the editor (and in the field) without announcing it as something the user typed.
        showHtml(html) {
            const text = isEmptyValue(html) ? '' : String(html)
            if (text === (editor.isEmpty ? '' : editor.getHTML())) return
            editor.commands.setContent(text, { emitUpdate: false })
            this.sync()
        },

        // `required` and `max-length` stop the submit of the form, like `required` and `maxlength` of a native field (a hidden
        // input cannot do it by itself, and the editor is a <div>). The same pattern as the signature.
        guardTheForm() {
            this.owningForm = this.$root.closest('form')
            this.onSubmit = event => {
                this.blocked = this.reasonToBlock()
                if (!this.blocked) return
                event.preventDefault()
                editor.commands.focus()
            }
            this.owningForm?.addEventListener('submit', this.onSubmit)
        },

        // The message of what stops the form, or '' when nothing does.
        reasonToBlock() {
            if (config.required && editor.isEmpty) return labels.required ?? ''
            if (this.overLimit) return (labels.tooLong ?? '').replace(':max', config.maxLength)
            return ''
        },

        // Copia el HTML al hidden y actualiza el contador.
        sync() {
            this.$refs.input.value = editor.isEmpty ? '' : editor.getHTML()
            this.length = editor.getText().length
            if (this.blocked && !this.reasonToBlock()) this.blocked = ''   // what stopped the form is solved
        },

        // Tells whoever listens above that the content changed, with the name of the field and its HTML.
        announce() {
            this.model = this.$refs.input.value   // what the user typed goes to the model of the caller
            announceValue(this.$refs.input)   // the field says it changed, as a native control does (modelBinding.js)
            this.$dispatch('dbl-rich-text-changed', { name: config.name, value: this.$refs.input.value })
        },

        // Puts the cursor in the editor. The label of the component calls it when it is pressed (it cannot use `for`:
        // the editor is a <div>, which is not a labelable element).
        focus() {
            editor?.commands.focus()
        },

        get overLimit() {
            return config.maxLength > 0 && this.length > config.maxLength
        },

        // ── Barra de herramientas ─────────────────────────────────────────────
        // Cada botón: { id, label, text, run(), active() }. `minimal` deja solo lo básico.
        buildButtons() {
            const c = () => editor.chain().focus()
            const has = (name) => editor.isActive(name)
            const all = []
            const add = (id, text, run, active = () => false) =>
                all.push({ id, text, label: labels[id] ?? id, run, active })

            add('paragraph', '¶', () => c().setParagraph().run(), () => has('paragraph'))
            add('h2', 'H2', () => c().toggleHeading({ level: 2 }).run(), () => has('heading', { level: 2 }))
            add('h3', 'H3', () => c().toggleHeading({ level: 3 }).run(), () => has('heading', { level: 3 }))
            add('bold', 'B', () => c().toggleBold().run(), () => has('bold'))
            add('italic', 'I', () => c().toggleItalic().run(), () => has('italic'))
            add('bulletList', '•', () => c().toggleBulletList().run(), () => has('bulletList'))
            add('orderedList', '1.', () => c().toggleOrderedList().run(), () => has('orderedList'))
            add('blockquote', '❝', () => c().toggleBlockquote().run(), () => has('blockquote'))
            if (deps.Link) add('link', '🔗', () => this.setLink(), () => has('link'))
            if (deps.Image && config.uploadUrl) add('image', '🖼', () => this.$refs.file.click())
            if (deps.Table) {
                add('table', '▦', () =>
                    c().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())
            }
            add('undo', '↶', () => c().undo().run())
            add('redo', '↷', () => c().redo().run())

            const minimal = ['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']
            return config.minimal ? all.filter(b => minimal.includes(b.id)) : all
        },

        // `tick` fuerza la dependencia reactiva; sin ella aria-pressed no se actualizaría.
        isActive(button) {
            this.tick // eslint-disable-line no-unused-expressions
            return button.active()
        },

        setLink() {
            const current = editor.getAttributes('link').href ?? ''
            const url = window.prompt(labels.linkPrompt ?? 'URL', current)
            if (url === null) return                                   // cancelado
            const chain = editor.chain().focus().extendMarkRange('link')
            if (url.trim() === '') return void chain.unsetLink().run() // vacío = quitar enlace
            if (!SAFE_URL.test(url.trim())) {
                this.error = labels.linkInvalid ?? ''
                return
            }
            this.error = ''
            chain.setLink({ href: url.trim() }).run()
        },

        // ── Subida de imágenes: POST multipart `file`, respuesta { url } ────────
        async upload(event) {
            const file = event.target.files[0]
            event.target.value = ''
            if (!file) return
            const form = new FormData()
            form.append('file', file)
            this.uploading = true
            this.error = ''
            try {
                // Escalera de http.js: axios inyectado, `window.axios` o `fetch`.
                const { data } = await request({ method: 'post', url: config.uploadUrl, data: form }, deps)
                // An HTML answer (the login page of an expired session) must not insert an image without `src`.
                const image = answerShapes.uploadedImage(data, config.uploadUrl)
                editor.chain().focus().setImage({ src: image.url, alt: file.name }).run()
            } catch (e) {
                this.error = labels.uploadFailed ?? ''
            } finally {
                this.uploading = false
            }
        },

        destroy() {
            this.owningForm?.removeEventListener('submit', this.onSubmit)
            editor?.destroy()
            editor = null
        },
    }
})

// Registra el componente Alpine con las dependencias ya inyectadas.
export function registerRichText(Alpine, deps) {
    Alpine.data('dblRichText', (config) => dblRichText(config, deps))
}
