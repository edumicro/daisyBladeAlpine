# `<x-dbl::form.select>`: opciones desde una URL

El desplegable normal (`options`) y el buscable (`searchable`) no cambian. Esta página cuenta solo el
que **pide sus opciones a una URL** (`options-url`) y, opcionalmente, **las vuelve a pedir cuando cambia otro campo** (`depends-on`).

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
Un selector `searchable` guarda su valor en un `<input type="hidden">` que no lanza `change`, así que no sirve como origen.

## Valor y envío

- `value` queda marcado en cuanto llegan las opciones.
- El control se envía con el formulario con su `name` (`name[]` con `multiple`).
- Sin `<option value="">` (sin `placeholder`) un select de un solo valor no puede estar vacío: usa `placeholder` en los campos opcionales.

## JavaScript

La factoría `dbSelectRemote` está en `resources/js/daisyblade.js` y se registra sola. Su ámbito Alpine (`x-data`) es el contenedor del select y no el `<select>`, para que el mensaje de error quede dentro; la factoría encuentra el `<select>` dentro del contenedor. Su parte pura
(`toTexts`, `dependencyParams`, `stillOffered`) se prueba con `node --test tests/js/select-remote.test.mjs`.
