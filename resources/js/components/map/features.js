// Funciones puras del mapa (sin DOM ni librerías): validación de color, formato del número
// y búsqueda de una feature por id. Las usan los dos adaptadores y el componente.

const HEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i

/** Devuelve el color si es un hex válido (#rgb, #rgba, #rrggbb, #rrggbbaa); si no, null. */
export function validColor(value) {
    return typeof value === 'string' && HEX.test(value.trim()) ? value.trim() : null
}

/** Color efectivo de una feature: `properties.color` válido o el de la capa. */
export function pointColor(properties, fallback) {
    return validColor(properties?.color) ?? fallback
}

/**
 * Texto a pintar dentro del marcador: entero (también como texto) o texto corto de 1 a 3
 * caracteres. Devuelve null si no hay nada que pintar o no es válido.
 */
export function formatNumber(value) {
    if (typeof value === 'number') {
        const t = String(value)
        return Number.isInteger(value) && t.length <= 3 ? t : null
    }
    if (typeof value !== 'string') return null
    const t = value.trim()
    return t.length >= 1 && t.length <= 3 ? t : null
}

/** Id de una feature: `id` o `properties.id`, como texto; null si no tiene. */
export function featureId(feature) {
    const id = feature?.id ?? feature?.properties?.id
    return id === undefined || id === null || id === '' ? null : String(id)
}

/** Busca en una FeatureCollection la feature con ese id (comparado como texto). */
export function findFeature(collection, id) {
    if (id === undefined || id === null) return null
    const wanted = String(id)
    return (collection?.features ?? []).find((f) => featureId(f) === wanted) ?? null
}

/** ¿Es una feature de tipo punto con coordenadas numéricas? */
export function isPoint(feature) {
    const c = feature?.geometry?.coordinates
    return feature?.geometry?.type === 'Point' && Array.isArray(c) && Number.isFinite(c[0]) && Number.isFinite(c[1])
}

/**
 * Separa los puntos de una capa: los que llevan número válido (`numbered`, nunca se agrupan)
 * y el resto (`plain`). En `plain` el `properties.color` inválido se elimina para que valga el de la capa.
 */
export function splitFeatures(features) {
    const plain = []
    const numbered = []
    for (const f of features ?? []) {
        if (!isPoint(f)) continue
        const props = { ...(f.properties ?? {}) }
        const label = formatNumber(props.number)
        const color = validColor(props.color)
        if (color) props.color = color; else delete props.color
        if (label === null) { delete props.number; plain.push({ ...f, properties: props }) } else numbered.push({ feature: f, label, color })
    }
    return { plain, numbered }
}

/**
 * Caja que envuelve todos los puntos: { west, south, east, north } (grados), o null si no hay
 * ninguno. Con un solo punto la caja es degenerada (west === east); el adaptador limita el zoom.
 */
export function boundsOf(features) {
    let b = null
    for (const f of features ?? []) {
        if (!isPoint(f)) continue
        const [lng, lat] = f.geometry.coordinates
        if (!b) b = { west: lng, south: lat, east: lng, north: lat }
        else {
            b.west = Math.min(b.west, lng); b.east = Math.max(b.east, lng)
            b.south = Math.min(b.south, lat); b.north = Math.max(b.north, lat)
        }
    }
    return b
}
