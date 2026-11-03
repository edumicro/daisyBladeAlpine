'use strict'

// ── Protocolo de respuesta de los eventos de edición: funciones puras ──────
// El componente emite `node-move` y `edge-create` con `accept` y `reject` en el detalle.
// Quien escucha decide (abrir su diálogo, preguntar a su servidor...) y responde cuando quiera.

/**
 * Par accept/reject de una sola respuesta: la primera llamada gana y las demás no hacen nada.
 * @param {{onAccept: function(Object=), onReject: function(string=)}} handlers
 * @returns {{accept: function(Object=), reject: function(string=), readonly settled: boolean}}
 */
export function responder({ onAccept, onReject }) {
    let settled = false
    return {
        accept(data) { if (!settled) { settled = true; onAccept(data) } },
        reject(message) { if (!settled) { settled = true; onReject(message) } },
        get settled() { return settled },
    }
}

/** Flecha provisional (discontinua) que se pinta mientras el padre decide. */
export const provisionalEdge = (from, to, seq) => ({
    id: null, key: `__provisional_${seq}`, from, to, label: '', variant: null, muted: false, flat: null, provisional: true,
})

/**
 * La flecha ya fijada: la provisional más lo que el padre devolvió en `accept(edge)`.
 * `from` y `to` no se pueden cambiar desde la respuesta. Sin `id`, se genera uno estable.
 * @param {Object} prov flecha provisional
 * @param {Object} [extra] { id?, label?, variant?, muted?, flat? }
 * @param {string[]} usedKeys claves ya en uso, para no repetir
 */
export function finalizeEdge(prov, extra = {}, usedKeys = []) {
    const data = extra && typeof extra === 'object' ? extra : {}
    const id = data.id ?? `${prov.from}>${prov.to}#${Date.now().toString(36)}`
    let key = String(id)
    while (usedKeys.includes(key)) key += '_'
    return {
        id: data.id ?? key, key, from: prov.from, to: prov.to,
        label: data.label ? String(data.label) : '',
        variant: data.variant ?? null, muted: !!data.muted, flat: data.flat ?? null, provisional: false,
    }
}

/** ¿La posición actual sigue siendo la que se emitió? (si no, ya hubo otro movimiento y no se revierte) */
export const samePos = (a, b) => !!a && !!b && Math.round(a.x) === Math.round(b.x) && Math.round(a.y) === Math.round(b.y)
