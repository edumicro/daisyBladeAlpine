# `<x-dbl::form.rich-text>`

Editor de texto enriquecido basado en **Tiptap 3**. Tiptap **no viene incluido** en el paquete: lo
instala e inyecta la aplicación, así que decides tú qué extensiones (y qué botones) hay.

## Uso

```blade
<x-dbl::form.rich-text
    name="body"
    label="Contenido"
    :value="old('body', $post->body)"
    placeholder="Escribe aquí…"
    upload-url="{{ route('uploads.image') }}"
    :max-length="5000"
    description="Se admiten títulos, listas, enlaces, imágenes y tablas."
    :required="true"
/>
```

| Prop | Descripción |
|---|---|
| `name` | Nombre del `<input type="hidden">` que viaja en el formulario |
| `label`, `description`, `required` | Como en el resto de campos |
| `value` | HTML inicial |
| `placeholder` | Texto con el editor vacío (requiere `Placeholder`) |
| `upload-url` | Endpoint de subida de imágenes (requiere `Image`) |
| `max-length` | Máximo en **caracteres de texto** (sin etiquetas). Muestra un contador que se pone rojo al pasarse; **no bloquea** la escritura: valida también en el servidor |
| `minimal` | Barra reducida: negrita, cursiva, listas, enlace, deshacer y rehacer |

## Instalación de paquetes

```bash
npm install @tiptap/core@^3 @tiptap/pm @tiptap/starter-kit
# Opcionales: cada una activa su botón
npm install @tiptap/extension-link @tiptap/extension-image @tiptap/extension-table @tiptap/extension-placeholder
```

En Tiptap 3 las extensiones de tabla se exportan desde `@tiptap/extension-table`
(`Table`, `TableRow`, `TableHeader`, `TableCell`).

## Registro en `app.js`

```js
import './daisyblade.js'
import axios from 'axios'
import Alpine from 'alpinejs'
import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table'
import Placeholder from '@tiptap/extension-placeholder'
import { registerRichText } from './daisyblade/components/rich-text.js'

window.axios = axios
window.Alpine = Alpine

registerRichText(Alpine, {
    Editor, StarterKit, Link, Image, Table, TableRow, TableHeader, TableCell, Placeholder,
    // axios,   // opcional: si no se pasa, se usa window.axios
})

Alpine.start()   // registerRichText ANTES de start()
```

Llama a `registerRichText` antes de `Alpine.start()`. Los botones de enlace, imagen y tabla solo
aparecen si su extensión está en el objeto; la imagen además necesita `upload-url`.

## Subida de imágenes

El botón de imagen hace `POST` multipart a `upload-url` con el campo `file` y espera
`{ "url": "https://…/imagen.jpg" }`. Valida el fichero en el servidor (tipo MIME, tamaño) y
devuelve una URL pública.

## Accesibilidad

La barra tiene `role="toolbar"`, cada botón es un `<button>` con `aria-label` y los activos
llevan `aria-pressed="true"`. Se navega con Tab y Enter/Espacio; el área editable es un
`role="textbox"` multilínea. Los colores usan tokens de daisyUI, así que respeta el modo oscuro.

## Seguridad: saneado en el servidor

**El HTML que envía el navegador no es de fiar.** Cualquiera puede saltarse el editor y enviar
`<script>` o `onerror=` directamente. El cliente solo limita lo cómodo; **el saneado lo hace
siempre el servidor**, antes de guardar (y no basta con escapar al mostrar si luego se imprime
con `{!! !!}`).

Recomendado: [`symfony/html-sanitizer`](https://symfony.com/doc/current/html_sanitizer.html) con
una lista blanca que coincida con la barra:

```bash
composer require symfony/html-sanitizer
```

```php
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

$config = (new HtmlSanitizerConfig())
    ->allowElement('p')
    ->allowElement('h2')->allowElement('h3')
    ->allowElement('strong')->allowElement('em')
    ->allowElement('ul')->allowElement('ol')->allowElement('li')
    ->allowElement('blockquote')->allowElement('br')
    ->allowElement('a', ['href'])
    ->allowElement('img', ['src', 'alt'])
    ->allowElement('table')->allowElement('thead')->allowElement('tbody')
    ->allowElement('tr')->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan'])
    ->allowLinkSchemes(['http', 'https', 'mailto'])
    ->allowMediaSchemes(['http', 'https'])
    ->forceHttpsUrls(false);

$clean = (new HtmlSanitizer($config))->sanitize($request->input('body'));
```

Valida también `max-length` sobre el texto (`strip_tags($clean)`) y comprueba que `upload-url`
exige autenticación y limita los tipos de fichero.
