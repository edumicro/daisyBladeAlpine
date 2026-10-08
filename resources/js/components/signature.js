'use strict'

// Firma en pantalla. SignaturePad (signature_pad@^5) llega inyectado: este módulo no lo importa.
//
//   registerSignature(Alpine, { SignaturePad })
//
// Alpine: x-data="dbSignature({ required: true })"
// La vista debe tener un <canvas x-ref="canvas"> y un <input type="hidden" x-ref="input">.

const INK = '#111111'        // trazo oscuro...
const PAPER = '#ffffff'      // ...sobre blanco, también en modo oscuro: el PDF es blanco

const dbSignature = (config = {}, deps = {}) => ({
    pad: null,
    empty: true,
    error: false,
    form: null,
    onSubmit: null,
    onReset: null,
    onResize: null,

    init() {
        const SignaturePad = deps.SignaturePad ?? window.SignaturePad
        this.pad = new SignaturePad(this.$refs.canvas, {
            penColor: INK,
            backgroundColor: PAPER,
            minWidth: 1.5,
            maxWidth: 4,
        })
        this.pad.addEventListener('endStroke', () => this.sync(true))

        this.resize()
        this.onResize = () => this.resize()
        window.addEventListener('resize', this.onResize)

        this.form = this.$root.closest('form')
        if (this.form) {
            this.onSubmit = (e) => {
                if (config.required && this.empty) {
                    e.preventDefault()
                    this.error = true
                    this.$refs.canvas.scrollIntoView({ block: 'center' })
                }
            }
            this.onReset = () => this.clear()
            this.form.addEventListener('submit', this.onSubmit)
            this.form.addEventListener('reset', this.onReset)
        }
    },

    destroy() {
        window.removeEventListener('resize', this.onResize)
        this.form?.removeEventListener('submit', this.onSubmit)
        this.form?.removeEventListener('reset', this.onReset)
        this.pad?.off()
    },

    // Ajusta el lienzo a su tamaño en pantalla multiplicado por devicePixelRatio (nítido en retina)
    // y vuelve a pintar los trazos que había.
    resize() {
        const canvas = this.$refs.canvas
        const strokes = this.pad.toData()
        const ratio = Math.max(window.devicePixelRatio || 1, 1)
        canvas.width = canvas.offsetWidth * ratio
        canvas.height = canvas.offsetHeight * ratio
        canvas.getContext('2d').scale(ratio, ratio)
        this.pad.clear()                 // pinta el fondo blanco
        this.pad.fromData(strokes)
    },

    // Actualiza el campo oculto: PNG en data URL, o vacío si no hay firma.
    sync(notify = false) {
        this.empty = this.pad.isEmpty()
        this.$refs.input.value = this.empty ? '' : this.pad.toDataURL('image/png')
        if (!this.empty) this.error = false
        if (notify) this.$dispatch(this.empty ? 'cleared' : 'signed', { value: this.$refs.input.value })
    },

    undo() {
        const strokes = this.pad.toData()
        strokes.pop()
        this.pad.fromData(strokes)
        this.sync(true)
    },

    clear() {
        this.pad.clear()
        this.sync(true)
    },
})

function registerSignature(Alpine, deps) {
    Alpine.data('dbSignature', (config = {}) => dbSignature(config, deps))
}

export { dbSignature, registerSignature }
