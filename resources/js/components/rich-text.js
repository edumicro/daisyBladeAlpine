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

// Solo se aceptan enlaces http(s) y mailto. El servidor debe volver a comprobarlo al sanear.
const SAFE_URL = /^(https?:\/\/|mailto:)/i

export const dbRichText = (config = {}, deps = {}) => {
    // El editor vive en el cierre, NO en `this`: Alpine envolvería el editor en un Proxy
    // y Tiptap dejaría de funcionar bien.
    let editor = null
    const labels = config.labels ?? {}
    const http = () => deps.axios ?? window.axios

    return {
        buttons: [],      // se construye una vez en init(), cuando el editor ya existe
        tick: 0,          // sube en cada transacción para que Alpine repinte los estados activos
        length: 0,        // caracteres de texto (sin etiquetas)
        uploading: false,
        error: '',

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
                        class: 'db-rich-text-content',
                        role: 'textbox',
                        'aria-multiline': 'true',
                        'aria-label': config.label || labels.editor || '',
                    },
                },
                onTransaction: () => { this.tick++ },
                onUpdate: () => this.sync(),
            })
            this.buttons = this.buildButtons()
            this.sync()
        },

        // Copia el HTML al hidden y actualiza el contador.
        sync() {
            this.$refs.input.value = editor.isEmpty ? '' : editor.getHTML()
            this.length = editor.getText().length
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
                const { data } = await http().post(config.uploadUrl, form)
                editor.chain().focus().setImage({ src: data.url, alt: file.name }).run()
            } catch (e) {
                this.error = labels.uploadFailed ?? ''
            } finally {
                this.uploading = false
            }
        },

        destroy() {
            editor?.destroy()
            editor = null
        },
    }
}

// Registra el componente Alpine con las dependencias ya inyectadas.
export function registerRichText(Alpine, deps) {
    Alpine.data('dbRichText', (config) => dbRichText(config, deps))
}
