# `<x-dbl::display.map>`

Mapa con MapLibre GL JS (por defecto) o Google Maps, bases raster OSM / IGN / PNOA, capas GeoJSON con clustering, filtros y selector de punto con buscador de direcciones.

## Instalación

```bash
npm install maplibre-gl@^6
# solo si usas driver="google":
npm install @googlemaps/js-api-loader@^2 @googlemaps/markerclusterer
```

Registra el módulo (las librerías se **inyectan**; el paquete no las importa):

```js
import Alpine from 'alpinejs'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { registerMap } from '../../vendor/edumicro/daisyblade/resources/js/components/map.js'

registerMap(Alpine, { maplibregl })
```

Con Google:

```js
import { setOptions, importLibrary } from '@googlemaps/js-api-loader'
import { MarkerClusterer } from '@googlemaps/markerclusterer'

const loadGoogleMaps = async (key) => {
    setOptions({ key, v: 'weekly' })
    await importLibrary('maps')
    await importLibrary('marker')
    return google.maps
}

registerMap(Alpine, { maplibregl, loadGoogleMaps, MarkerClusterer })
```

(`registerMap` no es parte de `daisyblade.js`: se llama aparte, antes de `Alpine.start()`.)

## Props

| Prop | Por defecto | Descripción |
|---|---|---|
| `driver` | `maplibre` | `maplibre` o `google` |
| `base` | `osm` | `osm`, `ign-base`, `ign-pnoa` o URL XYZ propia con `{z}/{x}/{y}` |
| `center` | `[39.4699, -0.3763]` | `[lat, lng]` |
| `zoom` | `6` | Zoom inicial |
| `height` | `24rem` | Altura CSS (`px`, `rem`, `em`, `vh`, `%`) |
| `layers` | `[]` | `[{id, label, url, cluster, color, icon}]`; `url` devuelve un GeoJSON de puntos; `icon` es 1-2 caracteres dibujados sobre el punto |
| `filters` | `true` | `true`/`false` o `['layers' => bool, 'categories' => bool]`. Casillas por capa y por `properties.category` |
| `picker` | `false` | Marcador arrastrable; clic para colocarlo |
| `picker-name` | `location` | Prefijo de los inputs ocultos `<name>_lat` y `<name>_lng` |
| `picker-value` | `null` | `[lat, lng]` inicial (edición) |
| `geocode-url` | `''` | Endpoint del buscador (solo con `picker`) |
| `fit` | `false` | Al cargar por primera vez las capas visibles, encuadra todos sus puntos (margen de 40 px y `maxZoom` 17, para no acercarse demasiado con un solo punto). Sin puntos se queda en `center`/`zoom`. No vuelve a encuadrar al activar/desactivar capas ni con `focus()` |
| `name` | `''` | Identificador del mapa para el evento `dbl-map-focus` |
| `google-key` | `config('daisyblade.map.google_key')` | Clave de navegador de Google Maps |

Los valores por defecto salen de `daisyblade.map`.

### Popups

Se construyen con DOM y `textContent` (nada de HTML inyectado) a partir de `properties.title`, `subtitle`, `category`, `image` y `url`. `image` y `url` solo admiten `http(s)` o rutas relativas.

### Color y número por punto

Cada feature puede traer, en `properties`:

- `color`: hex válido (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`). Sustituye al color de la capa en ese marcador; si el formato no es válido se usa el de la capa.
- `number`: entero o texto de 1 a 3 caracteres (`7`, `12`, `A3`). Se pinta dentro del marcador. Un entero de más de 3 cifras se ignora.

```json
{"type":"Feature","id":42,"geometry":{"type":"Point","coordinates":[-0.37,39.47]},
 "properties":{"title":"Sede","color":"#dc2626","number":3}}
```

En MapLibre los puntos con número son marcadores DOM (el número va en `textContent`), no una capa `symbol`, para no depender de glyphs remotos. En Google es el `label` del marker. **Decisión:** los puntos con número no se agrupan en clústeres (el número perdería su sentido dentro de un grupo); siguen respondiendo al filtro de capas y categorías.

### Abrir una ficha desde fuera

Cada feature se identifica por `id` o `properties.id`. Desde dentro del componente Alpine:

```blade
<div x-data="{ map: null }">
    <x-dbl::display.map name="portal" :layers="[...]" x-init="map = $data" />
    <button x-on:click="map.focus('ent', 42)">Ver</button>
</div>
```


Desde cualquier sitio de la página, p. ej. una lista lateral, por evento (recomendado):

```blade
<li x-data x-on:click="window.dispatchEvent(new CustomEvent('dbl-map-focus', { detail: { map: 'portal', layer: 'ent', id: 42 } }))">
```

`focus(layerId, featureId)` espera a que las capas estén cargadas, centra el mapa (zoom 16), abre el popup y devuelve `true` si encontró el punto. Si el evento no trae `map`, lo atienden los mapas que tengan esa capa.

## Eventos

- `dbl-map-focus` (entrada, en `window`) — `detail: {map, layer, id}`; ver arriba.
- `map-picked` — `detail: {lat, lng}` al colocar o arrastrar el marcador (y al elegir un resultado del buscador). Burbujea desde el elemento raíz: `x-on:map-picked="..."`.

## Ejemplo

```blade
<form method="POST" action="{{ route('entidades.store') }}">
    @csrf
    <x-dbl::display.map
        base="ign-base"
        :center="[39.42, -0.38]" :zoom="13"
        :layers="[['id' => 'ent', 'label' => 'Entidades', 'url' => route('api.entidades'), 'cluster' => true, 'color' => '#2563eb']]"
        picker picker-name="sede" :picker-value="[$e->lat, $e->lng]"
        :geocode-url="route('geocode')" />
    {{-- se envían sede_lat y sede_lng --}}
</form>
```

## Bases e IGN

- OSM: `https://tile.openstreetmap.org/{z}/{x}/{y}.png`.
- IGN Base (`IGNBaseTodo`) y PNOA (`OI.OrthoimageCoverage`) por WMTS KVP en `GoogleMapsCompatible` (`https://www.ign.es/wmts/ign-base` y `/wmts/pnoa-ma`).
- Atribución obligatoria del IGN: «© Instituto Geográfico Nacional de España» (CC BY 4.0 scne.es). El componente la muestra siempre.

## Notas

- El número de los clusters de MapLibre usa fuentes (glifos) del servidor de demostración `demotiles.maplibre.org`. Para producción, aloja las tuyas y cambia `GLYPHS` en `resources/js/components/map/maplibre.js`.
- Los adaptadores (`map/maplibre.js`, `map/google.js`) comparten interfaz: `init`, `setLayers`, `toggleLayer`, `setPicker`, `flyTo`, `focus`, `fit`, `destroy`.
- Accesibilidad: controles con `aria-label` y lista de puntos visibles (`sr-only`) para lectores de pantalla.

## Geocodificador (PHP)

`Edumicro\DaisyBlade\Geo\Geocoder` (`search(string, int = 5): list<GeoResult>`, `reverse(float, float): ?GeoResult`) se resuelve con `GeocoderManager` (`Illuminate\Support\Manager`). Se inyecta como `Geocoder`.

Configuración en `daisyblade.geo`:

| `driver` | Notas |
|---|---|
| `cartociudad` (defecto) | Gratis, sin clave, solo España. Quita el envoltorio JSONP `callback(...)`; resuelve coordenadas con `findJsonp` |
| `nominatim` | `geo.nominatim.user_agent` obligatorio por política de uso; **máximo 1 petición por segundo** (se respeta esperando `min_interval` desde la última, vía caché) |
| `google` | `geo.google.key` (clave de servidor) |

Un driver desconocido lanza `InvalidArgumentException`.

### Ruta del buscador (opcional)

El paquete **no** registra rutas. Añade la tuya, con el middleware que quieras (y límite de peticiones):

```php
use Edumicro\DaisyBlade\Http\Controllers\GeocodeController;

Route::get('/geocode', GeocodeController::class)->middleware(['auth', 'throttle:30,1'])->name('geocode');
```

`GET /geocode?q=calle mayor 1` y `GET /geocode?lat=..&lng=..` devuelven `{"data": [{"label", "lat", "lng"}]}` (502 si el proveedor falla, 422 si falta `q`).
