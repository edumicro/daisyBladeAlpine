// Bases raster compartidas por los dos drivers. Las plantillas usan {z}/{x}/{y}.

const IGN = '© <a href="https://www.ign.es" target="_blank" rel="noopener">Instituto Geográfico Nacional de España</a> (CC BY 4.0 scne.es)'

const wmts = (service, layer, format) =>
    `https://www.ign.es/wmts/${service}?service=WMTS&request=GetTile&version=1.0.0&layer=${layer}` +
    `&style=default&tilematrixset=GoogleMapsCompatible&tilematrix={z}&tilerow={y}&tilecol={x}&format=${format}`

export const BASES = {
    osm: {
        tiles: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        maxzoom: 19,
    },
    'ign-base': { tiles: wmts('ign-base', 'IGNBaseTodo', 'image/png'), attribution: IGN, maxzoom: 19 },
    'ign-pnoa': { tiles: wmts('pnoa-ma', 'OI.OrthoimageCoverage', 'image/jpeg'), attribution: IGN, maxzoom: 19 },
}

/** `base` es una clave conocida o una URL de plantilla XYZ propia. */
export function resolveBase(base) {
    if (BASES[base]) return BASES[base]
    if (/^https?:\/\/.*\{z\}.*\{x\}.*\{y\}/.test(base)) return { tiles: base, attribution: '', maxzoom: 19 }
    return BASES.osm
}

/** Texto plano de una atribución con enlaces (para Google, donde se pinta con textContent). */
export const plainAttribution = (html) => html.replace(/<[^>]+>/g, '')
