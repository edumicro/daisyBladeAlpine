// Componente <x-dbl::display.map>: factory Alpine `dbMap` + registerMap(Alpine, deps).
//
// Las librerías NO se importan aquí: la app las inyecta en registerMap, así el paquete no
// arrastra maplibre-gl ni Google en el bundle de quien no use el mapa.
//
//   import maplibregl from 'maplibre-gl'
//   registerMap(Alpine, { maplibregl })                       // solo MapLibre
//   registerMap(Alpine, { maplibregl, loadGoogleMaps, MarkerClusterer })  // + Google

import { createMapLibreAdapter } from './map/maplibre.js'
import { createGoogleAdapter } from './map/google.js'
import { findFeature, boundsOf } from './map/features.js'

let deps = {}

const emptyCollection = { type: 'FeatureCollection', features: [] }

export const dbMap = (config = {}) => {
    // El adaptador y los datos crudos viven en la clausura, fuera del proxy reactivo de Alpine
    // (envolver objetos de MapLibre/Google en un Proxy los rompe).
    let adapter = null
    let raw = {}   // id -> FeatureCollection completa
    let resolveReady
    const ready = new Promise((r) => { resolveReady = r })   // capas cargadas (o fallo de init)

    return {
        layerState: [],
        categories: [],
        pointList: [],
        lat: null,
        lng: null,
        query: '',
        results: [],
        searched: false,
        error: '',

        async init() {
            const layers = config.layers ?? []
            this.layerState = layers.map((l) => ({
                id: l.id, label: l.label ?? l.id, color: l.color ?? '#2563eb', icon: l.icon ?? '',
                cluster: !!l.cluster, url: l.url, visible: true, loading: true,
            }))
            if (config.pickerValue) [this.lat, this.lng] = config.pickerValue

            try {
                adapter = this.makeAdapter()
                await adapter.init(this.$refs.canvas, { base: config.base, center: config.center, zoom: config.zoom })
                if (config.picker) {
                    await adapter.setPicker(true, (lat, lng) => this.setPicked(lat, lng), config.pickerValue)
                }
            } catch (e) {
                this.error = e.message
                resolveReady(false)
                return
            }
            await Promise.all(this.layerState.map((l) => this.load(l)))
            this.applyFilters()
            if (config.fit) this.fitOnce()
            resolveReady(true)
        },

        /**
         * Centra el mapa en un punto y abre su popup. La feature lleva `id` o `properties.id`.
         * Devuelve true si la encontró.
         */
        async focus(layerId, featureId) {
            if (!(await ready) || !adapter) return false
            const feature = findFeature(raw[layerId], featureId)
            if (!feature) return false
            await adapter.focus(layerId, feature)
            return true
        },

        /** Encuadra una sola vez los puntos de las capas visibles; sin puntos conserva center/zoom. */
        fitOnce() {
            const visible = this.layerState.filter((l) => l.visible).flatMap((l) => raw[l.id]?.features ?? [])
            const bounds = boundsOf(visible)
            if (bounds) adapter?.fit(bounds, 17)
        },

        /** Manejador del evento de ventana `dbl-map-focus`: { map, layer, id }. */
        onFocusEvent(detail = {}) {
            if (detail.map && detail.map !== config.name) return
            if (!detail.map && !raw[detail.layer] && !this.layerState.some((l) => l.id === detail.layer)) return
            this.focus(detail.layer, detail.id)
        },

        makeAdapter() {
            const opts = { googleKey: config.googleKey, labels: config.labels }
            if (config.driver === 'google') return createGoogleAdapter(deps, opts)
            if (!deps.maplibregl) throw new Error('dbMap: falta { maplibregl } en registerMap()')
            return createMapLibreAdapter(deps, opts)
        },

        async load(l) {
            try {
                const res = await fetch(l.url, { headers: { Accept: 'application/geo+json, application/json' }, credentials: 'same-origin' })
                if (!res.ok) throw new Error(res.status)
                raw[l.id] = await res.json()
            } catch {
                raw[l.id] = emptyCollection
                this.error = `${config.labels.layer_failed}: ${l.label}`
            }
            l.loading = false
            this.collectCategories(raw[l.id])
        },

        collectCategories(fc) {
            for (const f of fc.features ?? []) {
                const c = f.properties?.category
                if (c && !this.categories.some((x) => x.value === c)) this.categories.push({ value: String(c), checked: true })
            }
        },

        /** Reenvía al adaptador las capas con los puntos de las categorías marcadas. */
        applyFilters() {
            const on = new Set(this.categories.filter((c) => c.checked).map((c) => c.value))
            const list = []
            const layers = this.layerState.map((l) => {
                const features = (raw[l.id]?.features ?? []).filter((f) => !f.properties?.category || on.has(String(f.properties.category)))
                if (l.visible) {
                    for (const f of features.slice(0, 200)) {
                        list.push({ key: `${l.id}-${list.length}`, title: f.properties?.title ?? '', subtitle: f.properties?.subtitle ?? '', url: f.properties?.url ?? '' })
                    }
                }
                return { id: l.id, color: l.color, icon: l.icon, cluster: l.cluster, visible: l.visible, data: { type: 'FeatureCollection', features } }
            })
            this.pointList = list.slice(0, 200)
            adapter?.setLayers(layers)
        },

        toggleLayer(l) {
            adapter?.toggleLayer(l.id, l.visible)
            this.applyFilters()
        },

        setPicked(lat, lng) {
            this.lat = +lat.toFixed(6)
            this.lng = +lng.toFixed(6)
            this.$dispatch('map-picked', { lat: this.lat, lng: this.lng })
        },

        async search() {
            const q = this.query.trim()
            if (q.length < 2) return
            this.error = ''
            try {
                const res = await fetch(`${config.geocodeUrl}${config.geocodeUrl.includes('?') ? '&' : '?'}q=${encodeURIComponent(q)}`,
                    { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                if (!res.ok) throw new Error(res.status)
                this.results = (await res.json()).data ?? []
            } catch {
                this.results = []
                this.error = config.labels.search_failed
            }
            this.searched = true
        },

        pick(r) {
            this.results = []
            this.searched = false
            adapter.flyTo(r.lat, r.lng)
            adapter.setPicker(true, (lat, lng) => this.setPicked(lat, lng), [r.lat, r.lng])
            this.setPicked(r.lat, r.lng)
        },

        destroy() { adapter?.destroy(); adapter = null },
    }
}

export function registerMap(Alpine, injected = {}) {
    deps = injected
    Alpine.data('dbMap', dbMap)
}
