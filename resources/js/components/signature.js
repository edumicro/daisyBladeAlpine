'use strict'

// Firma en pantalla. SignaturePad (signature_pad@^5) llega inyectado: este módulo no lo importa.
//
//   registerSignature(Alpine, { SignaturePad })
//
// Alpine: x-data="dblSignature({ required: true })"
// La vista debe tener un <canvas x-ref="canvas"> y un <input type="hidden" x-ref="input">.

import { withConfig } from '../factoryConfig.js'
import { writeValue } from '../modelBinding.js'
import { observeSize } from '../observeSize.js'

const INK = '#111111'        // trazo oscuro...
const PAPER = '#ffffff'      // ...sobre blanco, también en modo oscuro: el PDF es blanco

const dblSignature = withConfig({ name: '', required: false }, (config, deps = {}) => ({
    pad: null,
    empty: true,
    error: false,
    form: null,
    onSubmit: null,
    onReset: null,
    sizeWatch: null,

    init() {
        const SignaturePad = deps.SignaturePad ?? window.SignaturePad
        this.pad = new SignaturePad(this.$refs.canvas, {
            penColor: INK,
            backgroundColor: PAPER,
            minWidth: 1.5,
            maxWidth: 4,
        })
        this.pad.addEventListener('endStroke', () => this.sync(true))

        // The canvas takes its size from the page, which is 0 px while the pad sits in a closed tab or modal: it is sized when it
        // shows and every time its size changes (observeSize.js), not once at the start.
        this.sizeWatch = observeSize(this.$refs.canvas, () => this.resize())

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
        this.sizeWatch?.stop()
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
        // The field announces its change as a native control does (the contract of modelBinding.js).
        writeValue(this.$refs.input, this.empty ? '' : this.pad.toDataURL('image/png'))
        if (!this.empty) this.error = false
        if (notify) this.$dispatch(this.empty ? 'dbl-signature-cleared' : 'dbl-signature-signed', { name: config.name, value: this.$refs.input.value })
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
}))

function registerSignature(Alpine, deps) {
    Alpine.data('dblSignature', (config = {}) => dblSignature(config, deps))
}

export { dblSignature, registerSignature }
