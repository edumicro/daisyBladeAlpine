# `<x-dbl::form.select>`: opciones desde una URL

El desplegable normal (`options`) y el buscable (`searchable`) no cambian. Esta página cuenta solo el
que **pide sus opciones a una URL** (`load-url`) y, opcionalmente, **las vuelve a pedir cuando cambia otro campo** (`depends-on`).

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
Un selector `searchable` guarda su valor en un `<input type="hidden">` que no lanza `change`, así que no sirve como origen.

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
