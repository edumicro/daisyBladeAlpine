import { resolveBase } from './bases.js'
import { buildPopup } from './popup.js'

// Fuente de glifos para el número de los clusters. Es el servidor de demostración de MapLibre:
// en producción conviene alojar las fuentes propias (ver docs/components/map.md).
const GLYPHS = 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf'
const FONT = ['Open Sans Regular']

/** Adaptador MapLibre GL JS. Interfaz común: init, setLayers, toggleLayer, setPicker, flyTo, destroy. */
export function createMapLibreAdapter({ maplibregl }, { labels = {} } = {}) {
    let map = null
    let marker = null
    let onPick = null
    let clickHandler = null
    let known = []      // ids de capas añadidas
    let ready = null

    const sid = (id) => `db-${id}`
    const layerIds = (id) => [`${sid(id)}-clusters`, `${sid(id)}-count`, `${sid(id)}-points`, `${sid(id)}-icon`]

    function drop(id) {
        layerIds(id).forEach((l) => map.getLayer(l) && map.removeLayer(l))
        if (map.getSource(sid(id))) map.removeSource(sid(id))
    }

    function add(l) {
        const s = sid(l.id)
        map.addSource(s, {
            type: 'geojson', data: l.data,
            cluster: !!l.cluster, clusterRadius: 50, clusterMaxZoom: 14,
        })
        const vis = l.visible ? 'visible' : 'none'
        const filterPoint = ['!', ['has', 'point_count']]

        if (l.cluster) {
            map.addLayer({
                id: `${s}-clusters`, type: 'circle', source: s, filter: ['has', 'point_count'],
                layout: { visibility: vis },
                paint: {
                    'circle-color': l.color,
                    'circle-radius': ['step', ['get', 'point_count'], 16, 10, 20, 50, 26],
                    'circle-stroke-width': 3, 'circle-stroke-color': '#fff', 'circle-opacity': 0.9,
                },
            })
            map.addLayer({
                id: `${s}-count`, type: 'symbol', source: s, filter: ['has', 'point_count'],
                layout: { visibility: vis, 'text-field': '{point_count_abbreviated}', 'text-font': FONT, 'text-size': 13, 'text-allow-overlap': true },
                paint: { 'text-color': '#fff' },
            })
        }
        map.addLayer({
            id: `${s}-points`, type: 'circle', source: s, filter: filterPoint,
            layout: { visibility: vis },
            paint: { 'circle-color': l.color, 'circle-radius': 9, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff' },
        })
        if (l.icon) {
            map.addLayer({
                id: `${s}-icon`, type: 'symbol', source: s, filter: filterPoint,
                layout: { visibility: vis, 'text-field': String(l.icon), 'text-font': FONT, 'text-size': 11, 'text-allow-overlap': true },
                paint: { 'text-color': '#fff' },
            })
        }
        known.push(l.id)
    }

    function bindInteractions(id) {
        const points = `${sid(id)}-points`
        map.on('click', points, (e) => {
            const f = e.features[0]
            new maplibregl.Popup({ closeButton: true, offset: 12 })
                .setLngLat(f.geometry.coordinates.slice())
                .setDOMContent(buildPopup(f.properties, labels.open))
                .addTo(map)
        })
        const clusters = `${sid(id)}-clusters`
        if (map.getLayer(clusters)) {
            map.on('click', clusters, async (e) => {
                const f = e.features[0]
                const zoom = await map.getSource(sid(id)).getClusterExpansionZoom(f.properties.cluster_id)
                map.easeTo({ center: f.geometry.coordinates, zoom })
            })
        }
        for (const l of [points, clusters]) {
            if (!map.getLayer(l)) continue
            map.on('mouseenter', l, () => (map.getCanvas().style.cursor = 'pointer'))
            map.on('mouseleave', l, () => (map.getCanvas().style.cursor = ''))
        }
    }

    function place(lat, lng) {
        if (!marker) {
            marker = new maplibregl.Marker({ draggable: true }).setLngLat([lng, lat]).addTo(map)
            marker.on('dragend', () => { const p = marker.getLngLat(); onPick?.(p.lat, p.lng) })
        } else {
            marker.setLngLat([lng, lat])
        }
    }

    const whenReady = (fn) => ready.then(() => map && fn())

    return {
        init(container, { base, center, zoom }) {
            const b = resolveBase(base)
            map = new maplibregl.Map({
                container,
                center: [center[1], center[0]],
                zoom,
                attributionControl: { compact: true },
                style: {
                    version: 8,
                    glyphs: GLYPHS,
                    sources: { base: { type: 'raster', tiles: [b.tiles], tileSize: 256, maxzoom: b.maxzoom, attribution: b.attribution } },
                    layers: [{ id: 'base', type: 'raster', source: 'base' }],
                },
            })
            map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
            ready = new Promise((resolve) => map.once('load', resolve))
            return ready
        },

        /** layers: [{id, color, icon, cluster, visible, data (FeatureCollection)}] */
        setLayers(layers) {
            return whenReady(() => {
                const ids = layers.map((l) => l.id)
                known.filter((id) => !ids.includes(id)).forEach(drop)
                known = known.filter((id) => ids.includes(id))
                for (const l of layers) {
                    const src = map.getSource(sid(l.id))
                    if (src) { src.setData(l.data); continue }
                    add(l)
                    bindInteractions(l.id)
                }
            })
        },

        toggleLayer(id, visible) {
            return whenReady(() => layerIds(id).forEach((l) =>
                map.getLayer(l) && map.setLayoutProperty(l, 'visibility', visible ? 'visible' : 'none')))
        },

        /** Activa/desactiva el modo selector. position: [lat, lng] | null. */
        setPicker(enabled, cb, position = null) {
            return whenReady(() => {
                onPick = cb
                if (!enabled) { marker?.remove(); marker = null; clickHandler && map.off('click', clickHandler); clickHandler = null; return }

                if (!clickHandler) {
                    clickHandler = (e) => {
                        const layers = known.flatMap(layerIds).filter((l) => map.getLayer(l))
                        if (map.queryRenderedFeatures(e.point, { layers }).length) return // el clic era de un punto
                        place(e.lngLat.lat, e.lngLat.lng)
                        onPick?.(e.lngLat.lat, e.lngLat.lng)
                    }
                    map.on('click', clickHandler)
                }
                if (position) place(position[0], position[1])
            })
        },

        flyTo(lat, lng, zoom = 16) {
            return whenReady(() => map.flyTo({ center: [lng, lat], zoom }))
        },

        destroy() { map?.remove(); map = marker = null },
    }
}
