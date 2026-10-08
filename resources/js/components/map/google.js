import { resolveBase, plainAttribution } from './bases.js'
import { buildPopup } from './popup.js'

/** Adaptador Google Maps. Misma interfaz que el de MapLibre. Requiere deps.loadGoogleMaps. */
export function createGoogleAdapter({ loadGoogleMaps, MarkerClusterer }, { googleKey, labels = {} } = {}) {
    let gm = null       // google.maps
    let map = null
    let info = null
    let marker = null
    let clickListener = null
    let groups = new Map()   // id -> { markers, clusterer, cluster }

    function clear() {
        for (const g of groups.values()) {
            g.clusterer?.clearMarkers()
            g.markers.forEach((m) => m.setMap(null))
        }
        groups = new Map()
    }

    function show(g, visible) {
        if (g.clusterer) {
            g.clusterer.clearMarkers()
            if (visible) g.clusterer.addMarkers(g.markers)
        } else {
            g.markers.forEach((m) => m.setMap(visible ? map : null))
        }
    }

    function makeMarker(l, f) {
        const [lng, lat] = f.geometry.coordinates
        const m = new gm.Marker({
            position: { lat, lng },
            title: f.properties?.title ?? '',
            label: l.icon ? { text: String(l.icon), color: '#fff', fontSize: '11px' } : undefined,
            icon: { path: gm.SymbolPath.CIRCLE, scale: 11, fillColor: l.color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
        })
        m.addListener('click', () => {
            info.setContent(buildPopup(f.properties, labels.open))
            info.open({ map, anchor: m })
        })
        return m
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
                const markers = l.data.features
                    .filter((f) => f.geometry?.type === 'Point')
                    .map((f) => makeMarker(l, f))
                const g = { markers, clusterer: l.cluster && MarkerClusterer ? new MarkerClusterer({ map }) : null }
                groups.set(l.id, g)
                show(g, l.visible)
            }
        },

        toggleLayer(id, visible) {
            const g = groups.get(id)
            if (g) show(g, visible)
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
