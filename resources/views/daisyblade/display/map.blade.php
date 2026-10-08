{{--
    Mapa interactivo (MapLibre GL o Google Maps) con capas GeoJSON, filtros y selector de punto.
    Necesita el módulo JS `components/map.js` registrado (ver docs/components/map.md).

    @prop driver      — maplibre | google
    @prop base        — osm | ign-base | ign-pnoa | URL de plantilla XYZ propia ({z}/{x}/{y})
    @prop center      — [lat, lng]
    @prop zoom        — nivel de zoom inicial
    @prop height      — altura CSS (px, rem, em, vh o %)
    @prop layers      — [ ['id'=>'ent','label'=>'Entidades','url'=>'/api/ent.geojson','cluster'=>true,'color'=>'#2563eb','icon'=>''] ]
    @prop filters     — true (capas + categorías) | false | ['layers'=>bool,'categories'=>bool]
    @prop picker      — marcador arrastrable; escribe <picker-name>_lat y <picker-name>_lng
    @prop pickerName  — prefijo de los inputs ocultos
    @prop pickerValue — [lat, lng] inicial del marcador (edición)
    @prop geocodeUrl  — endpoint del buscador de direcciones (devuelve {data:[{label,lat,lng}]})
    @prop googleKey   — clave de navegador de Google Maps (driver=google)

    Evento: `map-picked` (detail: {lat, lng}) al colocar o arrastrar el marcador.
--}}
@props([
    'driver'      => config('daisyblade.map.driver', 'maplibre'),
    'base'        => config('daisyblade.map.base', 'osm'),
    'center'      => config('daisyblade.map.center', [39.4699, -0.3763]),
    'zoom'        => config('daisyblade.map.zoom', 6),
    'height'      => config('daisyblade.map.height', '24rem'),
    'layers'      => [],
    'filters'     => true,
    'picker'      => false,
    'pickerName'  => 'location',
    'pickerValue' => null,
    'geocodeUrl'  => '',
    'googleKey'   => config('daisyblade.map.google_key', ''),
])

@php
    $filters = is_array($filters) ? $filters : ['layers' => (bool) $filters, 'categories' => (bool) $filters];
    $height = preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', (string) $height) ? $height : '24rem';
    $hasValue = is_array($pickerValue) && count($pickerValue) === 2 && is_numeric($pickerValue[0]) && is_numeric($pickerValue[1]);

    $config = [
        'driver'      => in_array($driver, ['maplibre', 'google'], true) ? $driver : 'maplibre',
        'base'        => (string) $base,
        'center'      => array_map('floatval', array_values($center)),
        'zoom'        => (float) $zoom,
        'layers'      => array_values($layers),
        'filters'     => ['layers' => (bool) ($filters['layers'] ?? true), 'categories' => (bool) ($filters['categories'] ?? true)],
        'picker'      => (bool) $picker,
        'pickerValue' => $hasValue ? [(float) $pickerValue[0], (float) $pickerValue[1]] : null,
        'geocodeUrl'  => (string) $geocodeUrl,
        'googleKey'   => (string) $googleKey,
        'labels'      => [
            'search_failed' => __('daisyblade::map.search_failed'),
            'layer_failed'  => __('daisyblade::map.layer_failed'),
            'open'          => __('daisyblade::map.open'),
        ],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'w-full space-y-2']) }}
     x-data="dbMap(JSON.parse($el.dataset.config))"
     data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}">

    @if($config['picker'] && $config['geocodeUrl'])
        <form class="join w-full" role="search" x-on:submit.prevent="search()">
            <input type="search" class="input input-bordered join-item w-full" x-model="query"
                   placeholder="{{ __('daisyblade::map.search_placeholder') }}"
                   aria-label="{{ __('daisyblade::map.search') }}">
            <button type="submit" class="btn btn-primary join-item" aria-label="{{ __('daisyblade::map.search') }}">
                <x-heroicon-o-magnifying-glass class="size-5" />
            </button>
        </form>
        <ul class="menu menu-sm rounded-box border border-base-300 bg-base-100" x-show="results.length" x-cloak>
            <template x-for="r in results" :key="r.label + r.lat">
                <li><button type="button" x-on:click="pick(r)" x-text="r.label"></button></li>
            </template>
        </ul>
        <p class="text-sm" x-show="results.length === 0 && searched" x-cloak>{{ __('daisyblade::map.no_results') }}</p>
    @endif

    @if($config['filters']['layers'] || $config['filters']['categories'])
        <details class="collapse collapse-arrow border border-base-300 bg-base-100"
                 x-show="layerState.length > 1 || categories.length" x-cloak>
            <summary class="collapse-title text-sm font-medium">{{ __('daisyblade::map.filters') }}</summary>
            <div class="collapse-content flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                @if($config['filters']['layers'])
                    <fieldset class="flex flex-wrap gap-x-4 gap-y-2" x-show="layerState.length > 1">
                        <legend class="sr-only">{{ __('daisyblade::map.layers') }}</legend>
                        <template x-for="l in layerState" :key="l.id">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" class="checkbox checkbox-sm" x-model="l.visible"
                                       x-on:change="toggleLayer(l)">
                                <span class="inline-block size-3 rounded-full" :style="`background:${l.color}`"></span>
                                <span x-text="l.label"></span>
                                <span class="loading loading-spinner loading-xs" x-show="l.loading" x-cloak></span>
                            </label>
                        </template>
                    </fieldset>
                @endif
                @if($config['filters']['categories'])
                    <fieldset class="flex flex-wrap gap-x-4 gap-y-2" x-show="categories.length" x-cloak>
                        <legend class="sr-only">{{ __('daisyblade::map.categories') }}</legend>
                        <template x-for="c in categories" :key="c.value">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" class="checkbox checkbox-sm" x-model="c.checked"
                                       x-on:change="applyFilters()">
                                <span x-text="c.value"></span>
                            </label>
                        </template>
                    </fieldset>
                @endif
            </div>
        </details>
    @endif

    <div x-ref="canvas" role="region" aria-label="{{ __('daisyblade::map.map') }}"
         class="w-full overflow-hidden rounded-box border border-base-300 bg-base-200"
         style="height: {{ $height }}"></div>

    @if($config['picker'])
        <p class="text-sm text-base-content/70">
            {{ __('daisyblade::map.picker_hint') }}
            <span x-show="lat !== null" x-cloak class="font-mono">
                (<span x-text="lat"></span>, <span x-text="lng"></span>)
            </span>
        </p>
        <input type="hidden" name="{{ $pickerName }}_lat" x-model="lat">
        <input type="hidden" name="{{ $pickerName }}_lng" x-model="lng">
    @endif

    <p class="text-sm text-error" role="alert" x-show="error" x-text="error" x-cloak></p>

    {{-- Alternativa para lectores de pantalla: los puntos visibles como lista de enlaces. --}}
    <ul class="sr-only" aria-label="{{ __('daisyblade::map.points_list') }}">
        <template x-for="p in pointList" :key="p.key">
            <li>
                <a x-show="p.url" :href="p.url" x-text="p.title"></a>
                <span x-show="!p.url" x-text="p.title"></span>
                <span x-text="p.subtitle"></span>
            </li>
        </template>
    </ul>
</div>
