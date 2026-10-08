# `<x-dbl::form.signature>`

Firma manuscrita en pantalla para tablet o móvil (secretaría y presidencia firmando actas).
Botones y textos grandes; el trazo es siempre oscuro sobre blanco, también en modo oscuro.

```blade
<form method="POST" action="/actas/1/firmar">
    @csrf
    <x-dbl::form.signature name="firma_secretaria" label="Firma de la secretaría"
        signer="Secretaría: Ana López" :required="true" :height="240" />
    <button class="btn btn-primary btn-lg">Guardar</button>
</form>
```

## Props

| Prop | Por defecto | Descripción |
|---|---|---|
| `name` | `''` | Nombre del `<input type="hidden">` |
| `label` | `''` | Título sobre el lienzo |
| `signer` | `''` | Texto bajo la línea ("Secretaría: Ana López") |
| `height` | `220` | Alto del lienzo en px |
| `required` | `false` | Impide enviar el formulario sin firma |
| `description` | `''` | Ayuda visible bajo el título |

## Valor enviado

Tras cada trazo el campo oculto contiene el PNG como data URL (`data:image/png;base64,…`);
vacío si no hay firma. Decódificalo en el servidor y guárdalo como fichero.

## Eventos

`signed` (tras un trazo, `detail.value` = data URL) y `cleared` (al quedar vacía). Burbujean:
`<div x-on:signed="…">`.

## JavaScript

`resources/js/components/signature.js` exporta `dbSignature` y `registerSignature(Alpine, deps)`.
`signature_pad@^5` se inyecta, el módulo no lo importa:

```js
import Alpine from 'alpinejs'
import SignaturePad from 'signature_pad'
import { registerSignature } from '../../vendor/edumicro/daisyblade/resources/js/components/signature.js'

registerSignature(Alpine, { SignaturePad })
```

## Detalles

- El lienzo se escala con `devicePixelRatio` y, al redimensionar, guarda y restaura los trazos (`toData` / `fromData`).
- "Deshacer" quita el último trazo; "Borrar" vacía el lienzo.
- `required` se valida en el `submit` del formulario padre, con un mensaje claro y sin enviar.
- Accesibilidad: descripción para lectores de pantalla y aviso de que firmar requiere dedo o puntero.
- Textos en `resources/lang/{es,ca,en}/signature.php` (`daisyblade::signature.*`).
