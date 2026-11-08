# `<x-dbl::form.select>`: opciones desde una URL

El select tiene cuatro orígenes de datos:

| Modo | Dónde están las opciones | Cuándo |
|---|---|---|
| normal (`options`) | en la página | listas cortas |
| `searchable` | en la página; se filtran en el navegador | decenas o pocos cientos |
| `options-url` | en una URL; se pide la lista **entera** una vez (y otra por cada cambio de `depends-on`) | listas medianas que no quieres meter en el HTML |
| `search-url` | en el servidor; se le pregunta **según lo que se teclea** | listas que no caben en la página: 20.000 medicamentos, un fichero de clientes, un callejero |

`options-url` y `search-url` a la vez es un error del programador (lanza una excepción con un mensaje claro), y `search-url` no admite `multiple`.
Esta página cuenta primero `options-url` (y su `depends-on`) y después, desde [Búsqueda en el servidor](#búsqueda-en-el-servidor-search-url), `search-url`.
**Para listas grandes usa `search-url`.**

```blade
<form method="POST" action="/direcciones">
    @csrf
    <x-dbl::form.select name="country_code" label="País" :options="$countries" :value="$address->country_code" />

    {{-- Las regiones dependen del país: al cambiarlo se piden otra vez --}}
    <x-dbl::form.select name="region_code" label="Región" placeholder="Sin región"
        options-url="/location/regions" depends-on="country_code"
        :value="$address->region_code" :options="[$address->region_code => $address->region_code]" />
</form>
```

## Props (solo las de este modo)

| Prop | Por defecto | Descripción |
|---|---|---|
| `options-url` | `''` | URL que devuelve las opciones. Con ella el desplegable las pide al cargar, con el cliente HTTP del paquete (el `axios` inyectado, `window.axios` o `fetch`). |
| `depends-on` | `''` | El `name` de **otro control del mismo formulario** (si el select no está en un `<form>`, del documento). Solo vale junto a `options-url`. |
| `value` | `null` | Lo que queda elegido cuando llegan las opciones (un array con `multiple`). |
| `options` | `[]` | Opciones que ya conoce el servidor (por ejemplo la guardada). Salen **antes** de que cargue el JS, o si no carga nunca, y el JS las sustituye por las que lleguen. |
| `name`, `label`, `placeholder`, `multiple`, `required`, `disabled`, `size`, `class` | | Igual que en el desplegable normal. `placeholder` no se pinta con `multiple`. |

## Respuesta de la URL

JSON con una lista de `{value, label}` (también vale `{"data": [...]}`):

```json
[{"value": "PT-30", "label": "Madeira"}, {"value": "PT-20", "label": "Azores"}]
```

Una respuesta que no es JSON (por ejemplo la página de login a la que redirige una sesión caducada) cuenta como error: mensaje con **Reintentar**, no una lista vacía.

## Con `depends-on`

1. Al cargar y **cada vez que el otro control lanza `change`**, pide `options-url` añadiendo su valor
   como parámetro con **el nombre de ese control**: `/location/regions?country_code=PT`.
2. Si lo elegido ya no está entre las opciones nuevas, el select se **vacía y lanza `change`**
   (así se entera un tercer desplegable que dependa de este).
3. Si el otro control está **vacío**, no pide nada: el select queda vacío y **deshabilitado**
   (un control deshabilitado no se envía con el formulario).
4. Si dos respuestas se cruzan (el usuario cambia deprisa), solo cuenta la de la última petición.
5. Si la petición falla, no se toca lo elegido (las opciones que traía el servidor siguen ahí si es la primera carga). Bajo el select sale un mensaje traducido con un botón **Reintentar** (el detalle técnico va a la consola), y el select no se queda cargando: `aria-busy` vuelve a `false`.

El otro control puede ser cualquier cosa que lance `change` y tenga `name` (un `<select>`, un `<input>`).
Su valor es el que el formulario enviaría: con un grupo de `form.radio` es el del radio **marcado** (sin ninguno marcado está vacío y el select espera); con un `form.checkbox` o `form.toggle` es `1` si está marcado y `0` si no.
Un selector `searchable` o con `search-url` guarda su valor en un `<input type="hidden">` y lanza `input` y `change` sobre él cuando cambia lo elegido, así que sí sirve como origen de otro select con `depends-on` (con `search-url`; el `options-url` lo lee igual).

## Valor y envío

- `value` queda marcado en cuanto llegan las opciones.
- El control se envía con el formulario con su `name` (`name[]` con `multiple`).
- Sin `<option value="">` (sin `placeholder`) un select de un solo valor no puede estar vacío: usa `placeholder` en los campos opcionales.

## JavaScript

La factoría `dbSelectRemote` está en `resources/js/daisyblade.js` y se registra sola. Su ámbito Alpine (`x-data`) es el contenedor del select y no el `<select>`, para que el mensaje de error quede dentro; la factoría encuentra el `<select>` dentro del contenedor. Su parte pura
(`toTexts`, `dependencyParams`, `stillOffered`) se prueba con `node --test tests/js/select-remote.test.mjs`.

## Búsqueda en el servidor (`search-url`)

```blade
<x-dbl::form.select name="medicine_id" label="Medicamento" search-url="/medicines/search"
    :value="old('medicine_id', $dose->medicine_id)" :text="old('medicine_text', $dose->medicine?->name)"
    hint="Escribe al menos dos letras" />
```

Es el mismo widget que `searchable` (un input, un hidden con el id y una lista) con otro origen: mientras la persona teclea, espera `debounce` ms y pregunta a `search-url`.

| Prop | Por defecto | Descripción |
|---|---|---|
| `search-url` | `''` | A dónde preguntar. Implica modo buscable. |
| `query-param` | `q` | Nombre del parámetro con lo tecleado. |
| `params` | `[]` | Array fijo que va en toda petición: `:params="['country_code' => 'ES']"`. |
| `depends-on` | `''` | El `name` de otro control del mismo formulario (o del documento si no hay `<form>`), **igual que con `options-url`**: su valor va como parámetro con el nombre del control y, cuando cambia, se vacía lo elegido y se vuelve a preguntar. Con el otro control vacío el campo queda deshabilitado. |
| `limit` | `20` | Va al servidor como `?limit=20`. |
| `min-chars` | `2` | Por debajo no pregunta nada; dice «Escribe 2 o más caracteres». |
| `debounce` | `250` | Milisegundos desde la última tecla hasta preguntar. |
| `value` y `text` | | **Lo ya elegido**, el id y lo que se ve. Cuando el formulario vuelve con un error de validación, pásalos (`old()`) o la persona pierde lo que había elegido. |
| `allow-free-text` | `false` | Vale enviar lo tecleado sin elegir nada de la lista («o escribe uno nuevo»): el campo envía el texto en vez del id. |
| `on-select`, `process-results`, `option-label`, `on-error` | | Los puntos de engarce, más abajo. |
| `name`, `label`, `hint`, `placeholder`, `required`, `disabled`, `size`, `class`, `container-class` | | Como los demás controles. `hint` es un texto de ayuda bajo el campo. |

### La petición

`GET {search-url}?q=ibu&limit=20&country_code=ES&lab=7` (`lab` sería el `depends-on`). Va por el cliente HTTP del paquete (`resources/js/http.js`: el `axios` inyectado, `window.axios` o `fetch`). Si dos peticiones se cruzan solo vale la de la última, y la que va en vuelo se cancela al teclear otra letra.

### La respuesta

La de select2 (el estándar de hecho):

```json
{"results": [{"id": 1, "text": "Ibuprofeno 600 mg", "lab": "Cinfa"}]}
```

También se leen las formas de `options-url`: un array pelado de `{id, text}` o de `{value, label}`, un mapa `{"valor": "etiqueta"}` y `{"data": [...]}`. Las filas pueden traer más campos (`lab`, una foto): se conserva la fila entera y llega a `on-select` y al evento. Un mapa pierde el orden si sus claves son números (JavaScript los ordena); si importa el orden, devuelve un array.

Un endpoint en Laravel:

```php
Route::get('/medicines/search', function (Request $request) {
    $request->validate(['q' => 'required|string|min:2', 'limit' => 'integer|min:1|max:50']);

    return response()->json(['results' => Medicine::query()
        ->where('name', 'like', $request->q.'%')
        ->orderBy('name')
        ->limit($request->integer('limit', 20))
        ->get(['id', 'name as text', 'laboratory']),
    ]);
});
```

### Puntos de engarce

Las cuatro son expresiones JavaScript (como un `x-on:click` de Alpine) y se evalúan en el ámbito del componente.

**`option-label`**: el nombre del campo que hace de etiqueta cuando no es `text`. El caso trivial, sin escribir una función.

```blade
<x-dbl::form.select name="client_id" search-url="https://crm.example/api/clients" option-label="nombre" />
```

**`process-results`**: se evalúa con lo que ha devuelto el servidor, **antes de pintarlo**, y lo que devuelva es lo que se pinta. Recibe la respuesta tal cual vino en `data` (no el array ya normalizado, porque si la forma es rara estaría vacío) y devuelve filas (`{id, text, ...}`, o cualquiera de las formas de arriba). Sirve para componer la etiqueta con varios campos, quedarse con unas filas, ordenarlas o leer un cuerpo que no se parece a nada. Si hay `process-results`, manda sobre `option-label`.

```blade
<x-dbl::form.select name="person_id" search-url="https://rrhh.example/find"
    process-results="data.payload.hits.map(h => ({ id: h.code, text: h.name + ' ' + h.surname, hit: h }))" />
```

**`on-select`**: se evalúa cuando se elige una opción, con `row` (la fila entera), `value` y `name`.

```blade
<x-dbl::form.select name="medicine_id" search-url="/medicines/search"
    on-select="document.getElementById('laboratory').value = row.laboratory" />
```

Además, al elegir se lanza el evento `select-change` (el mismo nombre que usa `searchable`) con `{ name, value, row }`; sube por el documento: `@select-change.window="..."`. Un `change` sobre el hidden avisa a un select que dependa de este.

**`on-error`**: se evalúa cuando la búsqueda falla, con `status` y `data` (el cuerpo de la respuesta, como `error.response.status` y `error.response.data` de axios) y `error`. **Es además del mensaje del componente, no en su lugar**: sigue saliendo el texto «No se ha podido buscar» con el botón «Reintentar» (el detalle va a la consola). Si `on-error` devuelve `false`, el componente se calla y deja que pintes tú lo que quieras.

```blade
<x-dbl::form.select name="medicine_id" search-url="/medicines/search"
    on-error="status === 401 ? (location.href = '/login', false) : true" />
```

### Qué pasa cuando algo sale mal

- Un 500, un 404, un JSON ilegible, un fallo de red, una respuesta que es una página HTML (sesión caducada) o un `process-results` que lanza **no salen como excepción**: son un fallo de búsqueda (mensaje, `on-error`, «Reintentar»), y «buscando…» se apaga siempre.
- **Lo ya elegido no se pierde**: `value` y el texto siguen ahí y el formulario sigue enviándolos. El campo sigue usable: se puede teclear otra vez o pulsar «Reintentar» sin recargar.
- Una petición cancelada (cada tecla cancela la anterior) **no es un error**, y una respuesta vieja que llega tarde se descarta en silencio.
- Los estados se dicen con palabras: «Escribe 2 o más caracteres», «Buscando…», «No hay nada con eso» (traducidos: es, ca, en).

### Teclado, ratón y accesibilidad

El input es un `role="combobox"` con `aria-expanded`, `aria-controls` y `aria-activedescendant`; la lista es un `role="listbox"` y cada opción un `role="option"`. **↓ y ↑** mueven (↓ abre la lista), **Enter** elige la resaltada (sin ninguna resaltada, Enter envía el formulario como siempre), **Esc** cierra, **Tab** sale. El ratón por encima resalta sin quitarle el foco al teclado, clic en una opción la elige y clic fuera cierra. Si lo tecleado no se eligió (y no hay `allow-free-text`), al cerrar el campo vuelve a lo último que se eligió, o se vacía.
Todo esto lo tiene también el modo `searchable`, porque comparten widget.

### Sin JavaScript

El input lleva el `name` y lo tecleado se envía; el hidden no lleva `name` hasta que el JS arranca y se lo pasa (entonces se envía el id). Por eso el servidor debe esperar un id con JS y un texto sin él, o validarlo en las dos formas.

### Cuándo usar cada modo

- Pocas opciones que caben en la página: normal, o `searchable` si son muchas para leerlas.
- Una lista de cientos o unos pocos miles que quieres cargar de golpe y filtrar sin más peticiones: `options-url`.
- Una lista que no cabe en la página (decenas de miles de filas): `search-url`. Cada búsqueda trae solo `limit` filas.

### JavaScript

La factoría `dbCombobox` está en `resources/js/daisyblade.js` y se registra sola. Su parte pura (`searchParams`, `toRows`, `readResults`, `filterRows`, `latestRequest`, `errorInfo`) se prueba con `node --test tests/js/combobox.test.mjs`.
