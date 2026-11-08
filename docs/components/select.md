# `<x-dbl::form.select>`: opciones desde una URL

El desplegable normal (`options`) y el buscable de la página (`searchable`) no cambian de comportamiento (el buscable gana teclado y accesibilidad, ver más abajo). Esta página cuenta el
que **pide sus opciones a una URL** (`load-url`) y, opcionalmente, **las vuelve a pedir cuando cambia otro campo** (`depends-on`), y el que **pregunta al servidor mientras se teclea** (`search-url`, al final).

```blade
<form method="POST" action="/direcciones">
    @csrf
    <x-dbl::form.select name="country_code" label="País" :options="$countries" :value="$address->country_code" />

    {{-- Las regiones dependen del país: al cambiarlo se piden otra vez --}}
    <x-dbl::form.select name="region_code" label="Región" placeholder="Sin región"
        load-url="/location/regions" depends-on="country_code"
        :value="$address->region_code" :options="[$address->region_code => $address->region_code]" />
</form>
```

## Props (solo las de este modo)

| Prop | Por defecto | Descripción |
|---|---|---|
| `load-url` | `''` | URL que devuelve las opciones. Con ella el desplegable las pide al cargar, con el cliente HTTP del paquete (el `axios` inyectado, `window.axios` o `fetch`). |
| `depends-on` | `''` | El `name` de **otro control del mismo formulario** (si el select no está en un `<form>`, del documento). Solo vale junto a `load-url`. |
| `value` | `null` | Lo que queda elegido cuando llegan las opciones (un array con `multiple`). |
| `options` | `[]` | Opciones que ya conoce el servidor (por ejemplo la guardada). Salen **antes** de que cargue el JS, o si no carga nunca, y el JS las sustituye por las que lleguen. |
| `name`, `label`, `hint`, `placeholder`, `multiple`, `required`, `disabled`, `size`, `class`, `wrapper-class` | | Igual que en el desplegable normal. `placeholder` no se pinta con `multiple`. `class` y el resto de atributos (`x-model`, `data-*`...) van al `<select>` (a la caja de texto en el modo `searchable`, salvo `x-model`); `wrapper-class` a la caja que lo rodea. En el modo `searchable`, el `x-model` es **la clave elegida**, como en un `<select>` nativo (`form.country_id` recibe un id, no «España»): vale `''` mientras no hay nada elegido (también mientras se teclea), pasa a la clave al elegir, y si el modelo cambia desde fuera la caja enseña la etiqueta de esa clave (con `''` o `null`, queda vacía). Un modelo que **arranca vacío** (`''` o `null`) no pisa el `value` que escribe la vista (el formulario de edición típico): se queda el `value` y el modelo lo recibe; si el modelo arranca con una clave, gana el modelo. Lo mismo vale para el select con `load-url`. El select **nativo** (sin `searchable` ni `load-url`) sigue el comportamiento de Alpine: el `x-model` manda sobre el `value` también cuando está vacío. Teclear después de elegir lo vacía y vacía el `<input type="hidden">`. `placeholder`, `autocomplete`, `@input`, `@focus`... van a la caja de texto |

## Respuesta de la URL

JSON con una lista de `{value, label}` (también vale `{"data": [...]}`):

```json
[{"value": "PT-30", "label": "Madeira"}, {"value": "PT-20", "label": "Azores"}]
```

Una respuesta que no es JSON (por ejemplo la página de login a la que redirige una sesión caducada) cuenta como error: mensaje con **Reintentar**, no una lista vacía.

## Con `depends-on`

1. Al cargar y **cada vez que cambia el valor del otro control**, pide `load-url` añadiendo su valor
   como parámetro con **el nombre de ese control**: `/location/regions?country_code=PT`.
2. Si lo elegido ya no está entre las opciones nuevas, el select se **vacía y lanza `change`**
   (así se entera un tercer desplegable que dependa de este).
3. Si el otro control está **vacío**, no pide nada: el select queda vacío y **deshabilitado**
   (un control deshabilitado no se envía con el formulario).
4. Si dos respuestas se cruzan (el usuario cambia deprisa), solo cuenta la de la última petición.
5. Si la petición falla, no se toca lo elegido (las opciones que traía el servidor siguen ahí si es la primera carga). Bajo el select sale un mensaje traducido con un botón **Reintentar** (el detalle técnico va a la consola), y el select no se queda cargando: `aria-busy` vuelve a `false`.

Se sigue el **valor** del otro control, no su evento: si tiene modelo (`x-model`), el select se entera de cualquier cambio del modelo (el usuario, tu código o un
formulario de edición que rellena país y región al cargar) y pide una sola vez; si no tiene modelo, se entera por el `change` del usuario. Un valor que no cambia no recarga.
El otro control puede ser cualquier cosa que tenga `name` (un `<select>`, un `<input>`).
Su valor es el que el formulario enviaría: con un grupo de `form.radio` es el del radio **marcado** (sin ninguno marcado está vacío y el select espera); con un `form.checkbox` o `form.toggle` es `1` si está marcado y `0` si no.
Vale como origen cualquier control del paquete, también uno que dibuja su propia interfaz (un selector `searchable`, `form.kv-editor`, `form.list-editor`, `form.upload`, `form.signature`, `form.rich-text`): todos escriben su valor en un campo con `name` y lanzan en él `input` y `change` (`resources/js/modelBinding.js`). Un padre que envía una **lista** (`name="country[]"`: un `multiple`, un grupo de casillas) se encuentra con `depends-on="country"` y su valor viaja como `?country[]=ES&country[]=FR`.

## La regla del modelo (`x-model`)

El select con `load-url`, el `searchable` y el contador del `textarea` siguen **la misma regla**, escrita una sola vez en
`resources/js/modelBinding.js`:

1. Al arrancar manda el **modelo** si tiene valor. Si está vacío (`null`, `''`, `[]`), se enseña el `value` de la vista y **el modelo lo recibe**.
2. Un valor que **no está entre las opciones** no se borra ni se cambia: el select lo conserva (con la etiqueta que escribió el servidor, o con el propio valor). Solo lo vacía el cambio del control del que depende (`depends-on`).
3. Un cambio del modelo desde fuera siempre se refleja, **cuando ocurra**: el control observa su modelo mientras viva (`observe()`), no solo al arrancar (una ficha que se carga por `fetch` rellena el modelo segundos después de pintar la página).
4. Lo que el usuario elige se escribe en el modelo y se anuncia con `dbl-select-changed`.

## Evento

Los tres modos (nativo, buscable y con `load-url`) anuncian lo elegido igual: `dbl-select-changed`, con
`detail: {name, value}` (`value` es una lista con `multiple`). Sube por el DOM hasta tu elemento:
`<div x-on:dbl-select-changed="…">`.

## Valor y envío

- `value` queda marcado en cuanto llegan las opciones.
- **Con `x-model`** (o `wire:model`) en el `<select>`, lo que tenga el modelo es lo que queda elegido cuando llegan las opciones, y se vuelve a leer cada vez que se piden otra vez. Si además escribes `value` y el modelo está vacío, manda el modelo, como en cualquier campo con `x-model`.
- El control se envía con el formulario con su `name` (`name[]` con `multiple`).
- Sin `<option value="">` (sin `placeholder`) un select de un solo valor no puede estar vacío: usa `placeholder` en los campos opcionales.

## JavaScript

La factoría `dblSelectRemote` está en `resources/js/daisyblade.js` y se registra sola. Su ámbito Alpine (`x-data`) es un elemento **junto al `<select>`**, que guarda el mensaje de error y el botón «Reintentar»; el `<select>` queda **fuera** de ese ámbito a propósito, para que lo que escribes en él (`::disabled="loading"`, `x-model="form.city"`...) lea tus datos y no el `loading`, `error`, `options`, `selected`, `disabled` o `waiting` del componente. Como el `<select>` no ve las `options` del componente, la factoría escribe las `<option>` ella misma. La factoría encuentra el `<select>` en el padre de su elemento. Su parte pura
(`toTexts`, `dependencyParams`, `stillOffered`) se prueba con `node --test tests/js/select-remote.test.mjs`.

## Buscar en el servidor: `search-url`

Con `load-url` el servidor devuelve la lista **entera** y el navegador la guarda; con `searchable` el navegador filtra opciones que **ya están en la página**. Ninguno vale para 20.000 medicamentos, un fichero de clientes o un callejero: no caben en la página ni en una sola respuesta. Con **`search-url`** el cuadro de texto (el mismo del modo `searchable`, con su lista, su teclado y su estilo) **le pregunta al servidor qué mostrar mientras la persona escribe**, y solo trae `limit` filas cada vez.

```blade
<x-dbl::form.select name="medicine_id" label="Medicamento" search-url="/medicines/search"
    :value="old('medicine_id', $prescription->medicine_id)" :text="old('medicine_name', $prescription->medicine?->name)" />
```

Por qué `search-url` y no `load-url`: `load-url` es «de dónde carga el componente su lista» y no pregunta nada al teclear; esta es otra cosa (preguntar con lo escrito). Cada URL dice para qué sirve (convención 2 de la 3). Pedir `load-url` y `search-url` a la vez es un error de programación (`InvalidArgumentException`): no se elige una en silencio. Tampoco vale con `multiple`.

### Props

| Prop | Por defecto | Descripción |
|---|---|---|
| `search-url` | `''` | URL que se pregunta con lo tecleado. Implica el modo buscable. |
| `query-param` | `q` | Nombre del parámetro con el texto: `/medicines/search?q=ibu&limit=20`. |
| `params` | `[]` | Parámetros fijos de toda petición, con las claves en snake_case: `:params="['country_code' => 'ES']"`. Valen también con `load-url`. |
| `depends-on` | `''` | El `name` de otro control del mismo formulario (o del documento si no hay `<form>`), **igual que en el select remoto**: su valor va como parámetro con el nombre del control; cuando cambia, se vacía lo elegido y lo tecleado y las búsquedas siguientes llevan el valor nuevo. Si el otro control está vacío no se pregunta nada y el cuadro queda deshabilitado. |
| `limit` | `20` | Se envía como `?limit=20`. |
| `min-chars` | `2` | Por debajo no se pregunta nada («Escribe al menos 2 caracteres»). |
| `debounce` | `250` | Milisegundos desde la última tecla hasta preguntar. |
| `value`, `text` | `null`, `''` | Lo **ya elegido** (el id y su etiqueta), para cuando el formulario vuelve con un error de validación. Sin `text` se enseña el propio `value`, nunca un cuadro vacío sobre un id escondido. |
| `allow-free-text` | `false` | Vale enviar lo tecleado sin haber elegido nada de la lista («o escribe uno nuevo»). Sin él, un texto que no es ninguna opción impide enviar (el navegador dice «Elige una opción de la lista»). |
| `text-name` | `{name}_text` | Nombre del campo que envía el texto tecleado. |
| `option-label` | `text` | El campo de cada fila que hace de etiqueta, cuando no es `text`. |
| `process-results` | `''` | Expresión JS: ver abajo. |
| `on-select` | `''` | Expresión JS cuando se elige una fila: ver abajo. |
| `on-error` | `''` | Expresión JS cuando la búsqueda falla: ver abajo. |
| `list-class` | `''` | Clases de la lista desplegable. |
| `name`, `label`, `hint`, `placeholder`, `required`, `disabled`, `size`, `class`, `wrapper-class` | | Igual que en los demás modos. `class` y el resto de atributos van al cuadro de texto; `x-model` es **la clave elegida**. |

### Qué se envía: dos campos, siempre

Los dos campos están en el HTML desde que se pinta, **con o sin JavaScript**, y el servidor nunca tiene que adivinar cuál de los dos recibe:

| Campo | Nombre | Contenido |
|---|---|---|
| `<input type="hidden">` | `{name}` | El **id** de lo elegido. Vacío si no se ha elegido nada. Sin JavaScript no cambia: sigue valiendo `value`. |
| `<input type="text">` | `{name}_text` | Lo **tecleado**. |

Así, si falla el JavaScript en un móvil, el formulario sigue enviando lo mismo: el id jamás se confunde con un texto. Con `allow-free-text` el servidor mira `medicine_id` y, si está vacío, usa `medicine_id_text` como nombre nuevo. El campo con nombre del contrato de control (`docs/contrato.md`) es el hidden: es él quien anuncia `input` y `change` y el que lee un `depends-on`.

```php
$data = $request->validate(['medicine_id' => ['nullable', 'exists:medicines,id'], 'medicine_id_text' => ['nullable', 'string', 'max:120']]);
```

### La respuesta del servidor

Solo se lee **una de estas dos formas** (la de select2, y un array pelado), con filas `{id, text}` (también `{value, label}`):

```json
{"results": [{"id": 1, "text": "Ibuprofeno 600 mg", "laboratorio": "Acme"}]}
```

Las filas pueden traer más campos (un laboratorio, una foto): se conserva la **fila entera** y llega a `on-select` y al evento. Cualquier otra forma (por ejemplo `{"ok": true, "mensaje": "…"}`, un `{valor: etiqueta}` o un `{data: [...]}`) **no se pinta como filas**: es una respuesta que no se entiende, sale el mensaje de error con **Reintentar**. Para una forma ajena, `process-results`.

```php
Route::get('/medicines/search', function (Request $request) {
    $rows = Medicine::query()
        ->where('name', 'like', '%'.$request->query('q').'%')
        ->limit($request->integer('limit', 20))
        ->get(['id', 'name as text', 'laboratory']);

    return ['results' => $rows];
});
```

### Los tres puntos de engarce

Son expresiones JavaScript que escribes en la etiqueta, como un `x-on:click`: se evalúan con Alpine **en el ámbito de tu página** (ven tus datos y funciones) más unos nombres propios. Viajan como texto dentro de la configuración (`@js($config)`), nunca pegadas en JavaScript por Blade.

**`process-results`**: recibe `response`, el **cuerpo entero** tal como llegó, y devuelve las filas `{id, text, ...}` que se pintan. Para componer la etiqueta, filtrar, ordenar o leer una forma que no es ninguna de las dos. Si lanza una excepción, la búsqueda cuenta como fallida (va por `on-error`). Con `process-results`, `option-label` no se lee.

```blade
<x-dbl::form.select name="drug_id" search-url="/api/cima"
    process-results="response.resultados.map(r => ({ id: r.nregistro, text: r.nombre + ' (' + r.labtitular + ')', ...r }))" />
```

**`option-label`**: el caso trivial, sin función.

```blade
<x-dbl::form.select name="client_id" search-url="/clients/search" option-label="nombre" />
```

**`on-select`**: cuando se elige una fila; `row` es la fila entera.

```blade
<x-dbl::form.select name="medicine_id" search-url="/medicines/search" on-select="form.price = row.price" />
```

**`on-error`**: cuando la búsqueda falla; recibe `error`, `status` y `data` (`error.response.status` y `error.response.data`, como en los demás componentes). Es **además** del mensaje del componente («No se han podido cargar los resultados» con **Reintentar**), no en su lugar; **si devuelve `false`, el componente se calla** y tú pintas lo que quieras.

```blade
<x-dbl::form.select name="medicine_id" search-url="/medicines/search" on-error="status === 401 ? (location = '/login', false) : notify(data.message)" />
```

### Evento

El mismo `dbl-select-changed` de los demás modos, con la fila entera: `detail: { name, value, row }` (`value` es el id; `row` es `null` al vaciar la elección). En los modos sin `search-url` el `detail` sigue siendo `{ name, value }`.

### Estados y fallos

- Siempre en palabras, nunca una caja vacía: «Escribe al menos N caracteres», «Buscando…», «No hay nada con eso».
- Antes de preguntar espera `debounce`; la petición en vuelo se **cancela** al teclear otra vez y una respuesta vieja que llegue tarde se descarta. Una cancelación **no es un error**.
- Cualquier fallo (500, 404, JSON ilegible, `process-results` que lanza) sale como mensaje traducido con **Reintentar**, con el detalle en la consola. **Lo ya elegido no se pierde**: el id y el texto siguen en sus campos y el formulario los envía. El campo sigue usable; el indicador «Buscando…» se apaga siempre.
- Se hace con `resources/js/http.js` (`request`, `joinUrl`, `isCanceled`), con las reglas de `docs/contrato.md`.

### Teclado y accesibilidad (también en el modo `searchable` sin URL)

↓ y ↑ mueven por las opciones (dando la vuelta), Enter elige, Esc cierra, Tab sale. El cuadro es un `role="combobox"` con `aria-expanded`, `aria-controls` y `aria-activedescendant`; la lista es un `role="listbox"` y sus opciones `role="option"`. El ratón resalta sin quitarle el foco al teclado, un clic elige y un clic fuera cierra.

### ¿Cuál uso?

| Si... | Usa |
|---|---|
| Hay pocas opciones (decenas) | `options` (normal) |
| Hay cientos, caben en la página y las conoces al pintar | `searchable` (filtra en el navegador) |
| La lista entera cabe en una respuesta, o depende de otro campo y quieres todas | `load-url` (+ `depends-on`) |
| La lista no cabe: miles de filas, o se busca por varios campos en el servidor | `search-url` |
