import { resolveBase, plainAttribution } from './bases.js'
import { buildPopup } from './popup.js'
import { isPoint, pointColor, formatNumber } from './features.js'

/** Adaptador Google Maps. Misma interfaz que el de MapLibre. Requiere deps.loadGoogleMaps. */
export function createGoogleAdapter({ loadGoogleMaps, MarkerClusterer }, { googleKey, labels = {} } = {}) {
    let gm = null       // google.maps
    let map = null
    let info = null
    let marker = null
    let clickListener = null
    let groups = new Map()   // id -> { markers (agrupables), loose (con número), byFeature, clusterer }

    function clear() {
        for (const g of groups.values()) {
            g.clusterer?.clearMarkers()
            g.markers.forEach((m) => m.setMap(null))
            g.loose.forEach((m) => m.setMap(null))
        }
        groups = new Map()
    }

    function show(g, visible) {
        g.loose.forEach((m) => m.setMap(visible ? map : null))
        if (g.clusterer) {
            g.clusterer.clearMarkers()
            if (visible) g.clusterer.addMarkers(g.markers)
        } else {
            g.markers.forEach((m) => m.setMap(visible ? map : null))
        }
    }

    function makeMarker(l, f) {
        const [lng, lat] = f.geometry.coordinates
        const number = formatNumber(f.properties?.number)
        const text = number ?? (l.icon ? String(l.icon) : null)
        const m = new gm.Marker({
            position: { lat, lng },
            title: f.properties?.title ?? '',
            label: text ? { text, color: '#fff', fontSize: '11px', fontWeight: number ? '600' : undefined } : undefined,
            icon: { path: gm.SymbolPath.CIRCLE, scale: 11, fillColor: pointColor(f.properties, l.color), fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
        })
        m.addListener('click', () => {
            info.setContent(buildPopup(f.properties, labels.open))
            info.open({ map, anchor: m })
        })
        return { m, numbered: number !== null }
    }

    return {
        async init(container, { base, center, zoom }) {
            if (!googleKey) throw new Error('Google Maps: falta google-key')
            gm = await loadGoogleMaps(googleKey)
            const b = resolveBase(base)

            map = new gm.Map(container, {
                center: { lat: center[0], lng: center[1] },
                zoom,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            })
            // Base raster propia en lugar de la de Google: mismas teselas que con MapLibre.
            const tiles = new gm.ImageMapType({
                name: 'base', tileSize: new gm.Size(256, 256), maxZoom: b.maxzoom,
                getTileUrl: (c, z) => b.tiles.replace('{z}', z).replace('{x}', c.x).replace('{y}', c.y),
            })
            map.mapTypes.set('base', tiles)
            map.setMapTypeId('base')

            if (b.attribution) {
                const a = document.createElement('div')
                a.className = 'bg-base-100/80 px-1 text-xs'
                a.textContent = plainAttribution(b.attribution)
                map.controls[gm.ControlPosition.BOTTOM_RIGHT].push(a)
            }
            info = new gm.InfoWindow()
        },

        setLayers(layers) {
            clear()
            for (const l of layers) {
                const markers = []
                const loose = []   // con número: nunca entran en el clusterer
                const byFeature = new Map()
                for (const f of l.data.features.filter(isPoint)) {
                    const { m, numbered } = makeMarker(l, f)
                    ;(numbered ? loose : markers).push(m)
                    byFeature.set(f, m)
                }
                const g = { markers, loose, byFeature, clusterer: l.cluster && MarkerClusterer ? new MarkerClusterer({ map }) : null }
                groups.set(l.id, g)
                show(g, l.visible)
            }
        },

        toggleLayer(id, visible) {
            const g = groups.get(id)
            if (g) show(g, visible)
        },

        /** Centra el mapa en la feature y abre su popup. */
        focus(layerId, feature, zoom = 16) {
            const [lng, lat] = feature.geometry.coordinates
            const m = groups.get(layerId)?.byFeature.get(feature)
            map.panTo({ lat, lng })
            map.setZoom(zoom)
            info.setContent(buildPopup(feature.properties, labels.open))
            if (m && m.getMap()) info.open({ map, anchor: m })
            else { info.setPosition({ lat, lng }); info.open({ map }) }
        },

        setPicker(enabled, onPick, position = null) {
            clickListener?.remove()
            clickListener = null
            if (!enabled) { marker?.setMap(null); marker = null; return }

            const place = (lat, lng) => {
                if (!marker) {
                    marker = new gm.Marker({ map, draggable: true })
                    marker.addListener('dragend', () => {
                        const p = marker.getPosition()
                        onPick?.(p.lat(), p.lng())
                    })
                }
                marker.setPosition({ lat, lng })
            }
            clickListener = map.addListener('click', (e) => {
                place(e.latLng.lat(), e.latLng.lng())
                onPick?.(e.latLng.lat(), e.latLng.lng())
            })
            if (position) place(position[0], position[1])
        },

        flyTo(lat, lng, zoom = 16) {
            map.panTo({ lat, lng })
            map.setZoom(zoom)
        },

        destroy() { clear(); clickListener?.remove(); marker?.setMap(null); map = marker = info = null },
    }
}
