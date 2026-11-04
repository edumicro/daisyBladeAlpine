# Contrato con el servidor

Todos los componentes de DaisyBlade que hablan con tu servidor (tabla, select remoto, pestañas perezosas, detalle de recurso,
calendario, mapa, subida, editor de texto, formulario automático, asistente e importador) lo hacen con **una sola función**,
`request()` de `resources/js/http.js`, y bajo **las mismas reglas**. Las reglas viven en ese fichero y solo allí.

## Reglas comunes

1. **Siempre se pide JSON.** Cada petición lleva `Accept: application/json` y, en el mismo dominio, `X-Requested-With: XMLHttpRequest`
   (y el token CSRF si no es un GET). Con esas cabeceras Laravel responde por sí solo con un `401`, un `419` o un `422` **en JSON**
   en vez de redirigir a la página de login.
2. **El contrato mira lo que llega, no de dónde viene.** Una respuesta cumple si su `Content-Type` es el que el componente pide
   (`application/json`, o `+json`, con o sin `charset`; en las pestañas perezosas, `text/html`) **y** su forma es la esperada.
   No se compara ninguna URL: da igual un `axios.defaults.baseURL`, un `<base href>` o una redirección que acaba en JSON válido.
3. **Lo demás es un error de contrato.** Si la sesión caduca y Laravel redirige al login, la petición recibe una página HTML:
   no es JSON y falla por su tipo (una página HTML completa tampoco vale como fragmento de una pestaña). Un texto plano como `OK`
   o un JSON con otro `Content-Type` también fallan.
4. **Un cuerpo vacío (un `204`) solo vale donde el componente lo declara** (la tabla del final dice cuáles). En el resto es un error.
5. **Cada componente comprueba la forma** de su respuesta en un solo sitio, la función de su nombre en `answerShapes` (`http.js`).
6. **Qué ve cada cual.** El usuario ve el mensaje traducido del componente («No se han podido cargar los datos», con Reintentar).
   El desarrollador ve en la consola un aviso que nombra el endpoint:
   `DaisyBlade: the endpoint /api/users did not answer JSON. See docs/contrato.md`.
   El error tiene `name: 'ContractError'`, `code: 'ERR_CONTRACT'` y `reason`: `not-json`, `empty`, `not-html` o
   `shape` (`isContractError(error)` lo reconoce).
7. Los errores `4xx` y `5xx` no son de contrato: llegan como siempre con `error.response.status` y `error.response.data`
   (un `422` con `{ message, errors }` se muestra en el formulario o en el fichero que falló).

Un endpoint de **otro dominio** sigue las mismas reglas, pero sin `X-Requested-With` ni cookies (el servidor ajeno los rechazaría
en el preflight de CORS).

## La respuesta de cada componente

| Componente | Petición | Respuesta que espera | ¿Vale un `204`? |
|---|---|---|---|
| `form.select` con `load-url` | `GET load-url` (+ `?<depends-on>=valor`) | `[{ "value": …, "label": … }]` o `{ "data": [ … ] }` (`answerShapes.selectOptions`) | No |
| `display.data-table` | `GET load-url?page&search&sort&direction&…` | `{ "data": [fila, …], "meta": { … } }`, lo que da un `paginate()` de Laravel como recurso (`tableRows`) | No |
| `display.resource-details` | `GET load-url` | el recurso `{ … }` o `{ "data": { … } }` (`resource`) | No |
| `sections.tabs` con `load-url` | `GET load-url` pidiendo `text/html` | **un fragmento HTML** (`Content-Type: text/html`). Es la única respuesta que no es JSON; una página completa (`<!doctype>`, `<html>`) es un error | No |
| `display.calendar` | `GET load-url?start=YYYY-MM-DD&end=YYYY-MM-DD` | `[{ "id", "title", "start", … }]` o `{ "data": [ … ] }` (`calendarEvents`) | No |
| `display.map`, capas | `GET load_url` de cada capa | un `FeatureCollection` de GeoJSON, `{ "features": [ … ] }` (`geoJsonLayer`) | No |
| `display.map`, buscador | `GET geocode-url?q=texto` | `{ "data": [{ "lat", "lng", "label" }] }` (`geocoderResults`) | No |
| `form.upload`, subida | `POST upload-url` (multipart, campo `file`) | `{ "id": …, "name", "url", "thumb", "size" }`; el `id` es obligatorio (`uploadedFile`) | No |
| `form.upload`, borrado | `DELETE delete-url` | cualquier JSON | **Sí** |
| `form.upload`, orden | `POST reorder-url` con `{ "ids": [ … ] }` | cualquier JSON | **Sí** |
| `form.rich-text`, imagen | `POST upload-url` (multipart, campo `file`) | `{ "url": "https://…/imagen.jpg" }` (`uploadedImage`) | No |
| `sections.auto-form` | `POST submit-url` con los valores | un objeto JSON; `{ "redirect": "/ruta" }` navega (`formAnswer`) | **Sí** |
| `sections.wizard` | `POST submit-url` con los datos de todos los pasos | igual que el formulario automático | **Sí** |
| `import.spreadsheet` | `POST upload-url` (multipart, campo `file`) | un objeto JSON, p. ej. `{ "imported": 120 }` (`importResult`) | No |

### El importador también exige JSON

Antes el importador aceptaba cualquier `200`, incluso un texto o un `back()`. Ya no: un controlador que responde `OK` o
redirige con `back()` **no cumple el contrato** y el componente enseña el error de subida. Responde, por ejemplo:

```php
return response()->json(['imported' => $count]);
```

## Cómo se comporta un controlador de Laravel con estas cabeceras

```php
// Sesión caducada: con Accept: application/json el middleware auth responde 401 en JSON, no redirige.
// Validación: $request->validate([...]) responde 422 con { message, errors }.
// Un recurso: return UserResource::collection(User::paginate());      // { data: [...], links, meta }
// Un 204: return response()->noContent();                              // solo donde la tabla de arriba dice «Sí»
```

## Si pones tu propio cliente

`registerDaisyBlade(Alpine, { axios })` (y los `registerXxx` de los componentes opcionales) aceptan un `axios` propio. Las reglas
valen igual: se le pasan `Accept` y `X-Requested-With` en la configuración de cada llamada, y se lee la cabecera `Content-Type`
de su respuesta. Si tu cliente no devuelve cabeceras, decide la forma del cuerpo (un objeto o una lista; un texto no es JSON).
