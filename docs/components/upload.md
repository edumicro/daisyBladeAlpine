# `<x-dbl::form.upload>`

Subida de ficheros: arrastrar y soltar más botón, vista previa, progreso por fichero, cancelar y reintentar, lista de existentes con borrado y reordenación.

```blade
<x-dbl::form.upload
    name="attachments"
    label="Documentos"
    action="{{ route('files.store') }}"
    multiple
    accept="image/*,.pdf"
    :max-size="5"
    :max-files="10"
    :existing="$files"
    delete-url="/files/{id}"
    reorder-url="/files/order"
/>

{{-- Hacer una foto con el móvil (acta firmada, ticket) --}}
<x-dbl::form.upload name="ticket" action="/files" accept="image/*" capture="environment" />
```

## Props

| Prop | Descripción |
|---|---|
| `name` | Los ids subidos se envían como `name[]` (inputs ocultos). |
| `label`, `description`, `required` | Etiqueta, ayuda y asterisco. |
| `action` | URL POST de subida. |
| `multiple` | Varios ficheros. Sin él, un fichero nuevo sustituye al anterior. |
| `accept` | Extensiones, tipos o comodines: `image/*,.pdf`. Se valida también en el cliente. |
| `max-size` | MB por fichero. |
| `max-files` | Número máximo de ficheros. |
| `capture` | `environment` (cámara trasera) o `user` (frontal) en móviles. |
| `existing` | `[{id, name, url, thumb, size}]` ya guardados. |
| `delete-url` | Plantilla con `{id}`; se llama con `DELETE`. Sin ella solo se quita de la lista. |
| `reorder-url` | Opcional; `POST {ids: [...]}` con el orden nuevo. |

## Contrato JSON con el servidor

**Subida** — `POST {action}`, `multipart/form-data`, campo `file`.

Respuesta 2xx:

```json
{ "id": 12, "name": "acta.pdf", "url": "/storage/acta.pdf", "thumb": "/storage/acta_t.jpg", "size": 48213 }
```

`thumb` es opcional; `size` en bytes. `id` es lo que viaja en `name[]`.

**Errores** — un 422 de Laravel se muestra en el fichero que falló (primer mensaje de `errors`, o `message`):

```json
{ "message": "The file field must be a PDF.", "errors": { "file": ["El fichero debe ser un PDF."] } }
```

**Borrado** — `DELETE {delete-url con {id}}`; cualquier 2xx vale. Si falla, el fichero se queda con el error.

**Orden** — `POST {reorder-url}` con `{"ids": [3, 1, 2]}`; solo ids ya subidos.

## Eventos

Se emiten con `$dispatch` y burbujean:

- `uploaded` — `detail: {id, name, url, file}` (`file` es la respuesta completa del servidor).
- `removed` — `detail: {id, name}`.

```blade
<div x-on:uploaded="console.log($event.detail.id)"> <x-dbl::form.upload … /> </div>
```

## JavaScript

`resources/js/components/upload.js` exporta `dbUpload` y `registerUpload(Alpine, deps)`. axios se inyecta:

```js
import axios from 'axios'
import { registerUpload } from './components/upload.js'
registerUpload(Alpine, { axios })   // sin deps, usa window.axios
```

## Accesibilidad

El input de fichero está asociado a su `label`; el estado (subido, error, movido…) se anuncia en una región `aria-live="polite"`; reordenar y borrar son botones con `aria-label`, operables con teclado. Los textos están en `resources/lang/{es,ca,en}/upload.php`.
