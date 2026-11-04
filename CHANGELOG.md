# Cambios

Formato: lo que está sin publicar arriba. Al sacar versión, esa sección pasa a llevar su número
y su fecha, y se etiqueta.

## [3.0.0] — sin publicar

La 3.0 **cambia nombres** para que todo el paquete siga una sola convención. No arrastra los nombres viejos: **no hay alias ni avisos de obsoleto**. Quien no quiera migrar se queda en la línea `2.x`, que se sigue manteniendo. Las secciones `[2.4.1]` y `[2.4.0]` de más abajo son las de la 2.x y también están en esta línea (la 3.0 parte de ella).

### Guía de la 2 a la 3

Doce convenciones. Cada una tiene su párrafo y, después, una tabla por componente con el nombre de antes y el de ahora.

1. **Un solo prefijo, `dbl`.** Las etiquetas Blade ya eran `x-dbl::`. Ahora también lo son los eventos (`dbl-…`), las factorías de JavaScript (`dblSelectRemote`), las clases CSS propias (`dbl-flowchart`), los ids que genera un componente (`dbl-flowchart-<aleatorio>-help`) y las claves de localStorage (`dbl_sidebar`, `dbl_wizard_…`). Al leer, si solo existe la clave vieja `db_…`, su valor se mueve una vez a la nueva y la vieja se borra: nadie pierde la barra lateral ni el progreso de un asistente.
2. **Propiedades de URL.** En PHP camelCase, en la etiqueta kebab-case, y las claves de los arrays de configuración en snake_case (como en Laravel). Cada URL dice para qué sirve: `load-url` (de dónde lee el componente sus datos), `submit-url` (adónde manda lo que el usuario rellenó), `upload-url` (adónde van los ficheros), `geocode-url` (se queda). Las claves de array que eran camelCase pasan a snake_case: `badge_color`, `view_url`, `line_number`.
3. **Valor.** `value` es un solo valor, en todos los controles de formulario que arrancan con uno: input, textarea, select, checkbox, radio, toggle, rich-text, kv-editor, list-editor y repeater (input, checkbox y toggle lo ganan; checkbox y toggle quedan marcados si es verdadero). Dos controles no lo tienen porque arrancan de otra cosa: upload recibe sus ficheros en `existing` y signature empieza vacío (el trazo se dibuja). `values` es solo un mapa nombre → valor: auto-form, fields y los pasos del wizard.
4. **Ayuda.** `hint` en todos los controles de formulario; `description` desaparece de ellos. Se pinta siempre debajo del control. select, radio y repeater ganan `hint`.
5. **Tamaño.** `size` es siempre un token `xs|sm|md|lg|xl` (más los extras con sentido: `full` en modal, `2xl` en avatar, `2xl`, `3xl` y `4xl` en text-rotate). El componente lo traduce a su clase con un `match` de nombres escritos enteros; un valor desconocido da `md`. input, textarea, checkbox, radio y toggle ganan `size`.
6. **Color.** `color` es siempre un token (`primary|secondary|accent|neutral|info|success|warning|error`, más `ghost|link` en los botones y `ghost` en badge), nunca la clase. `background` es el único nombre del fondo, con un token `base-100|base-200|base-300` o uno de los colores. Lo que no esté en la lista va por `class`.
7. **`class` y los demás atributos van al elemento principal del componente**, y todas las vistas aplican `$attributes` (las 27 que no lo hacían, también). El elemento principal es la raíz, como en cualquier componente de Laravel; **en un control de formulario es el propio control** (el `<input>`, el `<textarea>`, el `<select>`, el checkbox, cada radio, el interruptor y, en rich-text, signature y upload, la caja que hace de control), como en el `text-input` de Breeze: quien escribe `<x-dbl::form.input type="number" min="0" x-model="qty">` tiene el `min` y el `x-model` en el `<input>`, sin excepciones. La caja de fuera del control (etiqueta, ayuda, error) recibe sus clases con **`wrapper-class`**. Para dar clases a otra parte interior hay una propiedad `<parte>-class` (`canvas-class`, `table-class`...). `containerClass` desaparece. **Qué pasaba en la 2:** en un control `class` iba al control y `containerClass` a la caja, y el resto de atributos (`min`, `x-model`...) al control; en un componente que no era un control `class` era a veces la raíz, a veces una parte, y `containerClass` la caja, y 27 vistas no aplicaban `$attributes`. **Qué pasa ahora:** en un control, `class` y todos los atributos siguen en el control y `container-class` pasa a `wrapper-class`; en los demás, `class` y los atributos van a la raíz, `container-class` desaparece y la parte a la que iba el `class` viejo tiene su `<parte>-class`. Los editores compuestos (kv-editor, list-editor, repeater) no son un control sino un conjunto: su elemento principal es la raíz.
8. **Eventos.** Lo que un componente anuncia se llama `dbl-<componente>-<lo que pasó, en participio>`; las órdenes que escucha en la ventana, `dbl-<componente>-<verbo>`. Lo que ya ha pasado va en participio (`-changed`, `-clicked`, `-removed`); cuando es el padre quien decide si algo ocurre (`accept`/`reject` en flowchart, las peticiones de crear o borrar de node-graph), el nombre lo dice: `-requested`. Siempre con `$dispatch` desde el componente (sube por el DOM) y el `detail` es siempre un objeto. El select nativo y el remoto ahora también lanzan `dbl-select-changed` `{ name, value }`, y rich-text lanza `dbl-rich-text-changed`. La excepción a `$dispatch` es display.flowchart, que despacha desde su raíz con las mismas opciones porque necesita saber si un oyente llamó a `preventDefault()`.
9. **Un solo componente de filtros:** `x-dbl::form.filters`, con `filters[clave]` y los eventos `dbl-filters-applied` y `dbl-filters-cleared`, los dos con `{ filters }`. `form.filter` y `display.filters` desaparecen; `display.data-table` escucha esos dos eventos y nada más.
10. **La configuración llega al JavaScript de una sola forma:** `x-data="dblComponente(@js($config))"`. Fuera el `'{{ $url }}'` dentro de un texto JS (una comilla lo rompía), el `JSON.parse($el.dataset.config)` y el atributo `data-config`, `Js::from` y los objetos en línea con datos de PHP. Un `x-data` en línea sin datos de PHP puede quedarse.
11. **Limpieza.** Fuera la configuración muerta de `config/daisyblade.php` (`component_map`, `defaults`, `toast`, `sidebar`; quedan `map` y `geo`) y la factoría que nadie usaba. `daisyblade:install` publica también `components/`, y las traducciones tienen la etiqueta `daisyblade-lang`.
12. **El fallo del wizard.** Los `values` de los pasos son el punto de partida y el progreso guardado de ese mismo asistente se aplica encima: antes un `x-init` ponía los valores después de restaurar el progreso y lo pisaba.

Para pasar de la 2 a la 3, por este orden: (1) vuelve a publicar las vistas y el JavaScript (`php artisan daisyblade:install --force`; las vistas de la 2.x no funcionan con el JavaScript de la 3.x ni al revés, y sin `--force` el comando no pisa un fichero JS que ya existe); (2) busca en tus plantillas los nombres de las tablas de abajo (`options-url=`, `action=` en upload, auto-form y wizard, `variant=`, `container-class=`, `description=`, `x-on:select-change`...); las URL obligatorias te avisan solas (ver «Corregido»); (3) cambia los eventos que escuchas y las órdenes que lanzas; (4) pasa de la clase al token en `size` y `color`.

#### Factorías de JavaScript

| 2.x | 3.0 |
|---|---|
| `dbModal`, `dbToast`, `dbNavbar`, `dbSidebar`, `dbSidebarTree` | `dblModal`, `dblToast`, `dblNavbar`, `dblSidebar`, `dblSidebarTree` |
| `dbDataTable`, `dbAutoForm`, `dbWizard`, `dbSelectRemote`, `dbResourceDetails`, `dbTabsLazy`, `dbSpreadsheetImport`, `dbKvEditor`, `dbListEditor` | `dblDataTable`, `dblAutoForm`, `dblWizard`, `dblSelectRemote`, `dblResourceDetails`, `dblTabsLazy`, `dblSpreadsheetImport`, `dblKvEditor`, `dblListEditor` |
| `dbCalendar`, `dbMap`, `dbUpload`, `dbRichText`, `dbSignature`, `dbFlowchart` | `dblCalendar`, `dblMap`, `dblUpload`, `dblRichText`, `dblSignature`, `dblFlowchart` |
| `dbTabs` (no la usaba ninguna vista) | fuera. El nombre `dblTabs` es ahora la factoría de `navigation.tabs` (índice de la pestaña abierta) |
| (inline en la vista) | nuevas: `dblSteps`, `dblCarousel`, `dblTextRotate`, `dblRepeater`, `dblSelectSearch`, `dblToastSingle`, `dblTabs` |
| `dbSelectRemote`: `search`, `open`, `value`, `filtered()`, `select()`, `labelFor()`, el parámetro `q` de `fetch()` | fuera (restos de un modo buscable que ninguna plantilla usaba; el select buscable es `dblSelectSearch`) |
| `dbSelectRemote.fetch()` | `dblSelectRemote.loadOptions()` (compartía nombre con el `fetch` global) |
| `dbSelectRemote` en el contenedor del `<select>`, con las `<option>` en un `<template x-for>` dentro del `<select>` | `dblSelectRemote` en un elemento junto al `<select>` (que queda fuera de su ámbito); la factoría escribe las `<option>` (`showOptions()`), aplica el `x-model` (`takeModel()`) y escucha en la ventana (`onChange()`) |
| `dbModal({ open })`, `dbSidebar({ open, persistent })` (ninguna vista los pasaba) | `dblModal()` y `dblSidebar()`, sin configuración |
| configuración `url` / `optionsUrl` (select), `action` (auto-form, wizard, upload), `tabs[].url` | `loadUrl`, `submitUrl`, `uploadUrl`, `tabs[].loadUrl` |
| flowchart: `onWindowEvent(detail)` | `onSet`, `onFocus`, `onRemoveEdge`, `onRemoveNode` |

Clases CSS: `db-calendar`, `db-flowchart`, `db-flowchart-handle`, `db-rich-text-content` → `dbl-calendar`, `dbl-flowchart`, `dbl-flowchart-handle`, `dbl-rich-text-content`. localStorage: `db_sidebar` → `dbl_sidebar`; `db_wizard_<usuario>_<formulario>_v<n>` → `dbl_wizard_<usuario>_<formulario>_v<n>`.

#### Eventos

| 2.x | 3.0 |
|---|---|
| `select-change` (solo el select buscable) | `dbl-select-changed` `{ name, value }`, en los tres modos |
| `filter-apply`, `filters-updated` | `dbl-filters-applied` `{ filters }` |
| `filter-clear`, `filters-cleared` | `dbl-filters-cleared` `{ filters }` |
| `page-changed` (navigation.pagination) | `dbl-pagination-page-changed` |
| `step-changed` (navigation.steps) | `dbl-steps-step-changed` |
| `dbl-repeater-change` | `dbl-repeater-changed` |
| (rich-text no lanzaba nada) | `dbl-rich-text-changed` `{ name, value }` |
| `signed`, `cleared` | `dbl-signature-signed`, `dbl-signature-cleared` (`detail`: `{ value }` → `{ name, value }`) |
| `uploaded`, `removed` | `dbl-upload-uploaded`, `dbl-upload-removed` |
| `map-picked` | `dbl-map-picked` |
| `event-click` | `dbl-calendar-event-clicked` |
| `node-click`, `edge-click` (display.flowchart) | `dbl-flowchart-node-clicked`, `dbl-flowchart-edge-clicked` |
| `node-move`, `edge-create` (display.flowchart: el padre acepta o rechaza) | `dbl-flowchart-node-move-requested`, `dbl-flowchart-edge-requested` |
| `node-click`, `badge-click` (display.node-graph) | `dbl-node-graph-node-clicked`, `dbl-node-graph-badge-clicked` |
| `node-create`, `node-delete`, `badge-create`, `badge-delete`, `edge-create` (display.node-graph: el padre decide) | `dbl-node-graph-node-create-requested`, `-node-delete-requested`, `-badge-create-requested`, `-badge-delete-requested`, `-edge-requested` |
| `open-modal`, `close-modal` (orden de ventana, `{ id }`) | `dbl-modal-open`, `dbl-modal-close` |
| `notify` (orden de ventana, `{ message, type, duration }`) | `dbl-toast-show` |
| `sidebar-toggle` (orden de ventana) | `dbl-sidebar-toggle` |
| `dbl-flowchart` con `{ name, action: 'set' \| 'focus' \| 'removeEdge' \| 'removeNode', ... }` | una orden por verbo, cada una con `name`: `dbl-flowchart-set`, `dbl-flowchart-focus`, `dbl-flowchart-remove-edge`, `dbl-flowchart-remove-node` |
| `dbl-map-focus` | igual (ya cumplía la regla) |
| `action` de un ítem de `navigation.menu` / `navigation.dock` | igual: es el nombre del evento que lanza *tu* aplicación |

#### Por componente

**Acciones**

| Componente | 2.x | 3.0 |
|---|---|---|
| `actions.button` | `variant="btn-primary"`, `size="btn-md"` | `color="primary"` (también `ghost`, `link`), `size="md"` |
| `actions.fab` | `variant="btn-primary"`, `size="btn-lg"`, `class` (el botón) | `color="primary"`, `size="lg"`, `button-class`; `class` va a la raíz |
| `actions.swap` | `variant="btn-ghost"`, `size="btn-md"`, `class` (los botones) | `color="ghost"`, `size="md"`, `button-class`; `class` va a la raíz |
| `actions.modal` | `open-modal` / `close-modal`; `size` sm..xl, full; sin `$attributes` | `dbl-modal-open` / `dbl-modal-close`; añade `xs`; `class` y atributos a la raíz |

**Display**

| Componente | 2.x | 3.0 |
|---|---|---|
| `display.accordion` | `class` (título), `container-class` | `title-class`, `class` |
| `display.avatar` | `class` | `avatar-class` (`class` va a la raíz) |
| `display.badge` | `color` y `variant`, `ghost` booleano, `color="soft"` / `"dash"` / `"outline"` | solo `color` (admite `ghost`); `outline` sigue siendo booleano; `soft` y `dash` van por `class="badge-soft"` |
| `display.calendar` | `event-click`; `dbCalendar` | `dbl-calendar-event-clicked`; `dblCalendar`; `load-url` vacía lanza `InvalidArgumentException` |
| `display.card` | `background` (cualquier color), `border-color`, `actions[].variant` (`btn-primary`) | `background` es un token; `border-color` fuera (`class="border border-primary"`); `actions[].color` (`primary`) |
| `display.carousel` | `class` (diapositiva), `container-class` (la tira) | `slide-class`, `carousel-class`; `class` a la raíz |
| `display.chat-bubble` | `class` | `bubble-class` |
| `display.collapse` | `variant` (tinte del contenido), `class` (título), `container-class` | `background`, `title-class`, `class` |
| `display.compare`, `display.table` | `class` (tabla), `container-class` | `table-class`, `class` |
| `display.data-table` | `class` (tabla), `container-class`; escucha `filters-updated`, `filters-cleared`, `filter-apply`, `filter-clear`; lleva `display.filters` | `table-class`, `class`; escucha `dbl-filters-applied` y `dbl-filters-cleared`; lleva `form.filters`; `load-url` vacía lanza `InvalidArgumentException` |
| `display.diff` | `class` (cada fila); clave `lineNumber` | `row-class`; clave `line_number` |
| `display.filters` | — | `form.filters` |
| `display.flowchart` | `class` (lienzo), `container-class`; eventos y orden `dbl-flowchart`; id `dbf-…` | `canvas-class`, `class`; eventos y órdenes de las tablas de arriba; id `dbl-flowchart-…-help` |
| `display.hover-3d-card` | `class` | `content-class` |
| `display.hover-gallery` | `class` (imagen), `container-class` (rejilla); clave `viewUrl`; sin raíz única | `item-class`, `grid-class`; clave `view_url`; una raíz que recibe `class` |
| `display.kbd` | `variant`, `class` | `color`, `kbd-class` |
| `display.list` | `class` (la lista), `container-class` | `list-class`, `class` |
| `display.map` | capas `url`; `map-picked`; `data-config` | capas `load_url`; `dbl-map-picked`; sin `data-config` |
| `display.node-graph`, `display.tree` | `class` (el árbol), `container-class`; eventos del node-graph | `tree-class`, `class`; eventos de las tablas de arriba |
| `display.radial-progress` | `class` | `circle-class` |
| `display.resource-details` | `class` (contenido), `container-class` | `content-class`, `class`; `load-url` vacía lanza `InvalidArgumentException` |
| `display.stat` | `background-color="bg-base-100"`; sin `$attributes` | `background="base-100"`; `class` a la raíz |
| `display.status` | `class` | `text-class` (el texto de los modos `inline` y `dot`) |
| `display.text-rotate` | `class`, `size="base"` | `text-class`, `size="md"` |
| `display.timeline` | `variant`, ítem `variant`, etiqueta `variant`, `class` (punto), `container-class` | `color`, ítem `color`, etiqueta `color`, `dot-class`, `class` |

**Feedback**

| Componente | 2.x | 3.0 |
|---|---|---|
| `feedback.alert`, `feedback.loading` | `class` (contenido), `container-class` | `content-class`, `class` |
| `feedback.progress` | `class` (el `<progress>`), `container-class` | `progress-class`, `class` |
| `feedback.skeleton` | `class`, `container-class` | `skeleton-class`, `class` |
| `feedback.toast` | `notify`; sin `$attributes` | `dbl-toast-show`; `class` a la raíz |
| `feedback.tooltip` | `class` (el icono o texto), `tooltip-class` (raíz); `color` solo success, warning, error, info | `trigger-class`, `class`; `color` con los ocho colores |

**Formulario**

| Componente | 2.x | 3.0 |
|---|---|---|
| `form.input` | `class` (control), `container-class`, `description`; sin `value` ni `size` | `class` (control, con todos los atributos), `wrapper-class`, `hint`; `value`, `size` |
| `form.textarea` | `class` (control), `container-class`, `description`; sin `size` | `class` (control), `wrapper-class`, `hint`; `size` |
| `form.checkbox`, `form.toggle` | `class` (control), `container-class`, `description`; sin `value` ni `size`; el toggle sin `required` ni `id` | `class` (control), `wrapper-class`, `hint`; `value`, `size`; `required` e `id` (a `name` si no escribes `id`) llegan al control también en el toggle |
| `form.radio` | `class` (cada radio), `container-class`; sin `hint` ni `size`; el `id` iba a todos los radios; `required` no llegaba a los radios | `class` (cada radio), `wrapper-class`; `hint`, `size`; `id` solo al primer radio; `required` en cada radio |
| `form.select` | `options-url`, `class` (control), `container-class`; `select-change` solo en el buscable; sin `hint` | `load-url`, `class` (el `<select>`; en el modo `searchable`, la caja de texto), `wrapper-class`; `dbl-select-changed` en los tres modos; `hint`. Con `load-url`, el `<select>` queda fuera del ámbito del componente (lo que escribes en él lee tus datos) y su `x-model` se aplica cuando llegan las opciones; en `searchable`, tu `@input` y tu `@focus` funcionan |
| `form.rich-text` | `description`, `container-class` (la raíz); no lanzaba nada | `hint`, `wrapper-class`; `class` y los atributos van a la caja del editor; `dbl-rich-text-changed`; la etiqueta enfoca el editor (sin `for`) |
| `form.signature` | `description`, `class` (la raíz); `signed`, `cleared` | `hint`, `wrapper-class`; `class` y los atributos van al lienzo; `dbl-signature-signed`, `dbl-signature-cleared` |
| `form.upload` | `action`, `description`, `class` (zona de soltar), `container-class`; `uploaded`, `removed` | `upload-url` (obligatoria: vacía lanza `InvalidArgumentException`), `hint`, `class` (zona de soltar), `wrapper-class`; `dbl-upload-uploaded`, `dbl-upload-removed` |
| `form.repeater` | `dbl-repeater-change`; sin `hint`; sin `$attributes` | `dbl-repeater-changed`; `hint`; `class` a la raíz |
| `form.kv-editor`, `form.list-editor` | `class` | `class` (a la raíz; ya era la raíz) |
| `form.fields` | `alpha-errors`; un fragmento sin raíz | `alpine-errors`; envuelto en un `<div class="contents">` que recibe `$attributes` |
| `form.filter`, `display.filters` | `fields[name]` / `filters[key]`; `apply-url`; `help`, `description` del filtro | `form.filters`, `filters[key]`; `apply-url` fuera; `hint`, `text` del filtro |

**Layout, navegación, secciones**

| Componente | 2.x | 3.0 |
|---|---|---|
| `layout.app` | `html-class` | `class` (va al `<html>`) |
| `layout.auth` | sin `$attributes` | `class` y atributos al `<html>` |
| `layout.footer` | `bg-class="bg-base-200 text-base-content"`, `container-class`, `class` | `background="base-200"`, `class` |
| `layout.divider` | `class` (la línea), `container-class` | `divider-class`, `class` |
| `layout.join` | `class` (la unión), `container-class` | `join-class`, `class` |
| `layout.hero`, `layout.indicator`, `layout.stack`, `layout.section-wrapper` | `class`, `container-class` | `class` (la raíz); `content-class`, `indicator-class` y `overlay-class` se quedan |
| `navigation.breadcrumb` | `class` (la lista), `container-class` | `list-class`, `class` |
| `navigation.dock` | `icon-size="w-6 h-6"`, `class` (la barra), `container-class` | `icon-size="md"`, `bar-class`, `class` |
| `navigation.menu` | ítem `badgeColor`; `class`, `container-class` | ítem `badge_color`; `class` |
| `navigation.pagination` | `page-changed`; `class` (la unión) | `dbl-pagination-page-changed`; `join-class` |
| `navigation.steps` | `step-changed`; `class` (la lista) | `dbl-steps-step-changed`; `steps-class` |
| `navigation.sidebar` | `sidebar-toggle`; `class` (el `<aside>`) | `dbl-sidebar-toggle`; `aside-class` (`class` va al `<div>` que lo envuelve) |
| `navigation.navbar`, `navigation.sidebar-tree`, `navigation.tabs` | `class` como propiedad, o sin `$attributes` | `class` y atributos a la raíz |
| `sections.auto-form` | `action`, `class` (el `<form>`), `container-class` | `submit-url` (obligatoria en modo `alpine`: vacía lanza `InvalidArgumentException`), `form-class`, `class` |
| `sections.wizard` | `action`, `container-class`; `x-init` con los valores | `submit-url` (obligatoria: vacía lanza `InvalidArgumentException`), `class`; los `values` viajan en la configuración |
| `sections.tabs` | clave `'load-url'`, `class` (panel), `container-class` | clave `'load_url'`, `panel-class`, `class` |
| `import.spreadsheet` | `container-class`, `max-preview` | `class` (`upload-url` y `template-url` no cambian; `upload-url` es obligatoria: vacía lanza `InvalidArgumentException`). `max-preview` se quita: no había vista previa que limitar |

#### Otros cambios que se ven

- `form.checkbox` y `form.toggle` quedan marcados con `value` verdadero; lo que se envía sigue siendo `1` (y `0` si no está marcado).
- `form.fields` y `x-model`: el enlace usa `values['clave']` en lugar de `values.clave`.
- `display.card` pone un color de texto (`text-primary-content`...) a juego con el fondo.
- **`id` y la etiqueta en un control de formulario.** Regla: `id` es el del control y la etiqueta apunta a él con `for` (a `name` si no escribes `id`). Vale para input, textarea, select, checkbox y toggle; en el modo `searchable` de `form.select` el control es la caja de texto (su texto se enlaza con `:value` y `@input`, no con `x-model`, para que el `x-model` de quien lo usa no se repita). Tres excepciones, porque ahí el control no es un único elemento nativo:
  - **radio:** un grupo son varios `<input>`, y el `id` que escribes va solo al **primer** radio (el mismo `id` en todos sería HTML inválido, y `getElementById` o `aria-describedby` solo llegarían al primero). Los demás radios no llevan `id`, el grupo no lleva `id` si no escribes ninguno, y la etiqueta del grupo no lleva `for` (al pulsarla marcaría el primer radio). El resto de atributos (`class`, `x-model`, `required`...) sí van a cada radio.
  - **rich-text, signature y upload:** el control es una caja (un `<div>`), y el `id` que escribes va a esa caja, pero la etiqueta no apunta a ella. La de rich-text no lleva `for` (un `<div>` no se puede etiquetar): al pulsarla enfoca el editor. La de signature es un `<div>` y el lienzo lleva `aria-label`. La de upload apunta con `for` al `<input type="file">` oculto (`<name>-upload`), que es el que abre el selector.
- `daisyblade:install` copia también `components/` y `daisyblade:make` genera vistas que siguen las convenciones.

### Añadido
- `x-dbl::form.filters` (ver convención 9), `value` en input, checkbox y toggle, `hint` en todos los controles, `size` en input, textarea, checkbox, radio y toggle, y las factorías `dblSteps`, `dblCarousel`, `dblTextRotate`, `dblRepeater`, `dblSelectSearch`, `dblToastSingle` y `dblTabs`.
- `dbl-rich-text-changed`, `dbl-select-changed` en el select nativo y en el remoto, y `name` en los eventos de signature.
- `php artisan vendor:publish --tag=daisyblade-lang`, y `components/` en `daisyblade:install` y en `daisyblade-assets`.
- Pruebas que impiden volver atrás: `tests/Feature/ConventionsTest.php` y `tests/js/conventions.test.mjs`, una por convención.

### Corregido
- `sections.wizard` pisaba el progreso guardado con sus `values` iniciales (convención 12).
- `feedback.toast`: dos avisos en el mismo milisegundo compartían id y cerrar uno cerraba los dos.
- `display.text-rotate`: el temporizador no se paraba al destruir el componente.
- `form.textarea`: el mensaje de error abría un `<div>` y no lo cerraba.
- `display.card`, `display.timeline`: ya no montan clases en ejecución (`bg-{$background}`, `border-{$borderColor}`, `badge-{$variant}`).
- `form.select` con `load-url`: lo que quien lo usa escribía en el `<select>` (`::disabled="loading"`, `::data-err="error"`...) se evaluaba dentro del ámbito del componente, donde `loading`, `error`, `options`, `selected`, `disabled` y `waiting` tapaban los del padre: el select quedaba habilitado y el atributo vacío, sin error. Ahora el `<select>` va fuera del elemento con el `x-data` (que se queda con el mensaje de error y el botón «Reintentar»), así que las ligaduras de quien lo usa leen sus propios datos. Como el `<select>` ya no ve las `options` del componente, la factoría escribe las `<option>` ella misma (con `textContent`, nunca como HTML). Se mantienen los arreglos de la 2.4.0: el escuchador de `change` del componente va con `x-on:change.window.capture` (corre antes que cualquier escuchador del usuario, que podría pararlo), `x-effect="syncField()"` sigue en el elemento del componente, y un select que la vista deshabilita con su propio `:disabled` no se habilita al llegar las opciones. Se quita el soporte de una vista de la 2.3 con el `x-data` en el propio `<select>`: la 3.x no la acepta de todos modos (las factorías se llaman `dbl…`).
- `form.select` con `load-url` y `x-model` en el `<select>` (como manda la guía): el modelo no se aplicaba nunca. Alpine aplica un modelo a un `<select>` cuando cambia el modelo, no cuando aparecen `<option>`: la primera aplicación no encontraba opciones y al llegar nadie la repetía. Con `form.city = '2'` el select acababa en `''`, sin error. Ahora, cada vez que llegan las opciones, lo que tiene el modelo es lo que queda elegido. Un modelo vacío (`null` o `''`) no cuenta como elección: con `value="2"` (un formulario de edición) se queda el `value`, como en la 2.4.0. Alpine no tiene una vía pública para leer el modelo de un elemento, así que la factoría usa `el._x_model` (comprobado con Alpine 3.14.9; si una versión futura lo quita, el select se comporta como antes).
- `daisyblade:install` pisaba sin avisar `resources/js/components/` (y `daisyblade.js` y `http.js`) de la aplicación: `File::copyDirectory` fusiona y sobrescribe, y esa carpeta es donde casi toda aplicación Laravel guarda sus propios `calendar.js`, `map.js`, `upload.js`... Ahora un fichero JS que ya existe y es distinto no se copia salvo con `--force`, y el comando lista los que se saltó y dice cómo sobrescribirlos; uno idéntico al del paquete no se lista. Las vistas se siguen publicando siempre (su carpeta, `resources/views/vendor/daisyblade`, es del paquete). Cambia el paso de actualizar: tras actualizar el paquete, `daisyblade:install --force`.
- `form.rich-text`: la etiqueta apuntaba con `for` a un `<div>` (`<name>-editor`), que no se puede etiquetar: pulsarla no hacía nada, y el `id` que escribe quien lo usa (que va a la caja del editor) nunca era a lo que apuntaba. Ahora la etiqueta no lleva `for` y, al pulsarla, enfoca el editor (`dblRichText.focus()`). La guía dice que rich-text, signature y upload son la excepción de la regla del `id`.
- `form.radio`: el `id` que escribía quien lo usa se repetía en todos los radios del grupo (HTML inválido; `getElementById`, `aria-describedby` y un `<label for>` solo llegaban al primero). Ahora va solo al primer radio; los demás no llevan `id`. Elegimos el primero y no `{id}-{valor}` en cada uno para que el `id` que escribiste exista tal cual. Viene de la 2.x (se arregla también en la 2.4.1).
- `form.toggle` y `form.radio` con `required`: el asterisco de la etiqueta (el del toggle ni eso) no llegaba al control, y `form.checkValidity()` daba `true` con un toggle apagado o un grupo de radios sin marcar. Ahora `required` va al `<input>` del toggle y a cada radio del grupo, como en `form.checkbox`; el toggle pinta también el asterisco. Viene de la 2.x (se arregla también en la 2.4.1).
- `form.toggle`: no tomaba `id` de su `name` como `form.checkbox`, aunque la guía lo dice de todo control. Ahora `id` es el que escribes o, si no, `name`.
- Un nombre de la 2.x en una URL obligatoria dejaba la URL vacía y el componente «funcionaba»: `<x-dbl::sections.auto-form action="/save">` dejaba `action` como atributo HTML del `<div>`, el envío hacía `POST` a la página actual, recibía su HTML con un 200 y enseñaba «Saved!» sin haber guardado nada; `<x-dbl::display.data-table options-url="/rows">` pedía la página actual y enseñaba «Could not load the data» como si fallara el servidor. Ahora `sections.auto-form` (en modo `alpine`; en modo `form` una acción vacía es la página actual), `sections.wizard`, `display.data-table`, `display.resource-details`, `display.calendar`, `form.upload` e `import.spreadsheet` lanzan `InvalidArgumentException` al renderizar si su URL (`submit-url`, `load-url` o `upload-url`) está vacía, con un mensaje que dice qué propiedad falta y, si la tenía, cómo se llamaba en la 2 (`action`). No es un alias: solo avisa. Laravel envuelve el error en una `ViewException`; el original está en `getPrevious()`.
- `sections.auto-form` y `sections.wizard`: una respuesta que es texto y no JSON al enviar (la página de login tras una sesión caducada, o la página actual por una URL vacía) daba por bueno el envío: «Saved!», y el asistente borraba el progreso guardado. Ahora es un fallo, como ya lo era en el select, la tabla y el detalle (`expectJson`): mensaje de error, y el progreso del asistente se conserva. Una respuesta vacía (204) sigue siendo un éxito. El servidor debe contestar JSON.
- `form.select` con `searchable`: el `@input` y el `@focus` de quien lo usa desaparecían sin error, porque la caja de texto llevaba los suyos y después `$attributes`, y el navegador se queda con el primero de dos atributos iguales (escritos como `x-on:input` funcionaban, que era el duplicado). Los del componente van ahora en la caja que envuelve el campo, como `x-on:input` (que sube) y `x-on:focusin` (`focus` no sube, `focusin` sí).
- `form.kv-editor`: un `true` volvía como `false` al guardar el formulario sin tocar la fila (la vista escribía `"1"` y el editor lee un booleano comparando con `'true'`). Ahora se escribe `true` o `false`. Prueba: `tests/Feature/KvEditorBoolTest.php`.
- `import.spreadsheet`, `form.upload` y `form.rich-text`: una respuesta que es texto (el login de una sesión caducada, con un 200) se daba por buena: «Import complete», un fichero «subido» sin `id` (y el formulario enviaba `files[]=`) y una imagen sin `src` en el editor. Ahora es un fallo, como en el resto (`expectJson` pasa a `http.js` para que lo usen los tres). Pruebas: `tests/js/html-answer-is-failure.test.mjs`.
- `import.spreadsheet`: la vista previa decía «0 rows detected» y enseñaba una tabla vacía, porque el navegador nunca leyó el fichero (un `.xlsx` es un zip de XML y leerlo exige una librería que el paquete no lleva). El paso ahora es una confirmación con el nombre y el tamaño del fichero; las filas las cuenta el servidor, que sí lo lee (`imported` en su respuesta). Pruebas: `tests/js/spreadsheet-confirm-step.test.mjs`.
- **Un atributo de Alpine del componente en el elemento que recibe `$attributes`** (la familia de `form.select`, de la 2.4.0): si quien lo usa escribe el mismo, el HTML lo lleva dos veces y el navegador se queda con el primero, así que uno de los dos desaparecía sin error. Estaba en `form.repeater` (`x-on:input`, `x-effect`: el manejador de quien lo usa corría 0 veces), `actions.modal` (`:class`, `@keydown.escape.window`, y los dos `.window`), `feedback.alert` y `feedback.toast` (`x-show`, `@dbl-toast-show.window`), `form.upload` (`x-bind:class`, `x-on:drag*`), `form.signature` (`x-bind:class`), `display.data-table` (`@dbl-filters-*.window`), `display.flowchart`, `display.map` y `navigation.sidebar` (sus órdenes `.window`) y la caja de texto del select `searchable` (`:value`). Ahora el elemento raíz lleva solo su `x-data` y lo demás va en un elemento de dentro (los escuchadores, porque los eventos suben; las clases y el `x-show`, con un `x-effect` que llega a la raíz con `$root`). `form.repeater` tiene un elemento más, el de dentro, que es el objetivo `root` de `events`. Prueba que lo impide en todo el paquete: `tests/Feature/AlpineAttributeOwnershipTest.php`; los casos: `tests/Feature/DuplicateAlpineAttributeTest.php`.
- `form.fields` y `form.repeater`: el `class` de `attrs` de un campo se escribía antes del `class` propio del control y el navegador se queda con el primero, así que se perdían `input input-sm w-full`. Ahora van juntos en un solo `class` (`Attrs::render` admite un segundo argumento con las clases del componente). Pruebas: `tests/Feature/FieldAttrsClassTest.php`, `tests/Unit/AttrsTest.php`.
- `sections.auto-form` y `sections.wizard` ignoraban el `default` de cada campo en el modo `alpine` (el de por defecto): `form.fields` solo lo usaba en el modo `form`. Ahora el formulario arranca con el `default` de cada campo y, encima, los `values`. Prueba: `tests/Feature/AutoFormDefaultTest.php`.
- `display.data-table` con dos tablas en la página: el filtro de una recargaba también la otra con los filtros de la primera, porque escuchaba `dbl-filters-applied` en la ventana. Con la prop `filters` el panel está dentro de la tabla y el evento le sube: no usa `.window`. Sin la prop (los filtros están en otro sitio) sigue escuchando en la ventana. Prueba: `tests/Feature/DataTableFiltersScopeTest.php`.
- `form.filters`: «Clear all» vaciaba el estado y recargaba la tabla, pero los controles seguían enseñando lo escrito. Ahora los vacía. Prueba: `tests/Feature/FiltersClearAllTest.php`.
- `form.select` con `searchable`: el `x-model` de la caja de texto se quedaba con lo tecleado a medias («On») cuando el usuario elegía «One» (cambiar la caja por código no lanza `input`), y si luego borraba el texto el `<input type="hidden">` seguía enviando la clave. Ahora elegir lanza `input` en la caja con la etiqueta elegida, y teclear vacía el hidden y lanza `dbl-select-changed` con valor vacío. El `x-model` de la caja es **el texto de la caja** (lo tecleado, y la etiqueta una vez elegida); la clave está en el hidden y en `dbl-select-changed`. Prueba: `tests/js/select-search-model.test.mjs`.
- `form.textarea` con `max-length`: el contador era un `0/N` fijo. Ahora cuenta lo que se escribe (el escuchador va en la caja de fuera, así que los atributos de quien lo usa no se tocan). Prueba: `tests/Feature/TextareaCounterTest.php`.
- `sections.tabs`: una pestaña con la clave de la 2.x (`'load-url'`) o sin `load_url` daba un panel vacío para siempre, sin petición ni error. Ahora lanza `InvalidArgumentException` nombrando la pestaña y la clave. Prueba: `tests/Feature/TabsOldKeyTest.php`.
- `actions.modal` con `closable="false"`: solo ocultaba la X, y Escape y el clic en el fondo seguían cerrándolo. Ahora no lo cierran; solo `dbl-modal-close`. Prueba: `tests/Feature/ModalClosableTest.php`.
- `form.fields` (modo `form`) lanzaba «Undefined variable $errors» si la vista se pintaba sin la bolsa de errores compartida. Prueba: `tests/Feature/FieldsWithoutErrorsBagTest.php`.
- `navigation.tabs`: el nombre de una pestaña sin `label` era `"Tab {$i}"` en inglés fijo; ahora sale traducido (`Tab :number`).
- Documentación: la cabecera de `display.map` decía `map-picked`; el evento es `dbl-map-picked`. Prueba: `tests/Feature/MapViewCommentTest.php`.

### Quitado
- Todos los nombres de la tabla de arriba, sin alias.
- `display.filters`, `form.filter`, la factoría `dbTabs`, y `component_map`, `defaults`, `toast` y `sidebar` de `config/daisyblade.php`.

## [2.4.1] — sin publicar

Solo arreglos, sin cambiar ningún nombre de propiedad, evento ni clase.

### Corregido
- `form.select` con `options-url` y `x-model` en el `<select>`: el modelo no se aplicaba nunca. Alpine aplica un modelo a un `<select>` cuando el modelo cambia, no cuando aparecen las `<option>`; la primera vez no había opciones y al llegar nadie lo repetía, así que el select quedaba vacío con el modelo en `'2'` (y con `value="2"` y el modelo vacío, al revés). Ahora, al llegar las opciones, lo que tenga el modelo es lo que queda elegido (el modelo manda sobre `value`, como en cualquier campo con `x-model`). Prueba: `tests/js/select-remote-model.test.mjs`.
- `form.toggle` y `form.radio`: `required` pintaba el asterisco pero no llegaba al `<input>`, así que el navegador dejaba enviar el formulario con el toggle apagado o sin radio marcado. Ahora lo llevan el toggle y cada radio.
- `form.radio`: el `id` que escribe quien lo usa iba a todos los radios del grupo (HTML inválido). Ahora va solo al primero; el resto de atributos siguen yendo a cada radio.
- `form.toggle`: toma su `id` de `name`, como el checkbox (y el `id` que escribas manda). Pruebas de estos tres: `tests/Feature/ToggleRadioFixesTest.php`.

## [2.4.0] — 2026-11-04

### Añadido
- `form.select`: prop `depends-on` (solo junto a `options-url`), el `name` de otro control del mismo formulario (o del documento si no hay `<form>`). Al cargar y cada vez que ese control cambia, el select pide `options-url` añadiendo su valor como parámetro con el nombre del control (`?country_code=ES`). Si lo elegido ya no está en las opciones nuevas, se vacía y lanza `change` (para un tercer desplegable encadenado); con el control vacío no pide nada y el select queda vacío y deshabilitado. Si dos respuestas se cruzan, solo vale la de la última petición. Doc: `docs/components/select.md`.
- `form.select` con `options-url`: la prop `options` se pinta como opciones del servidor (con `value` marcado) para cuando el JS no carga; el JS las sustituye por las que lleguen. Un `multiple` no pinta el `placeholder` y el select lleva `select-error` como el normal.
- Funciones puras `toTexts`, `dependencyParams` y `stillOffered` (en `daisyblade.js`), con tests `node --test`.
- `display.flowchart`: diagrama de cajas y flechas sobre un lienzo SVG, con arrastrar y soltar (Pointer Events: ratón, táctil y lápiz) y teclado. Grafo libre: ciclos, flechas de un nodo a sí mismo y varias flechas entre el mismo par. Agnóstico: no llama al servidor.
  - Nodos con `shape` (`rectangle`, `rounded`, `pill`, `diamond`, `circle`), `description`, `icon`, `badge`, `group` (banda de fondo / calle), `variant`, `muted` y `flat`. Prop `direction` (`vertical` u `horizontal`) para la colocación automática, además de `nodes`, `edges`, `editable`, `name`, `height`, `grid` y `show-muted`.
  - Eventos `node-click`, `edge-click`, `node-move` y `edge-create`. Los dos últimos se pueden responder (`accept(datos?)` / `reject(mensaje?)`, con `preventDefault()` para decidir más tarde); la flecha nueva se pinta provisional mientras tanto, y sin respuesta se acepta tal cual. El rechazo con mensaje usa el evento `notify` de `feedback.toast`.
  - Colocación automática por capas con orden por baricentro, padres centrados sobre sus hijos y el dibujo normalizado al margen. Zoom con botones +, − y encuadrar, y prop `fit` (como la de `display.map`); al encuadrar, el dibujo queda centrado en el eje que sobra (`centerOffset`).
  - Desde fuera: métodos `set`, `focus`, `removeEdge`, `removeNode` y evento de ventana `dbl-flowchart`. Doc: `docs/components/flowchart.md`.
  - Lógica pura en `resources/js/components/flowchart/` (`geometry.js`, `protocol.js`) con tests `node --test`. Su JS no está en `daisyblade.js`: se registra con `registerFlowchart(Alpine)` y no tiene dependencias.
- **Un solo cliente HTTP para todos los componentes** (`resources/js/http.js`: `request`, `joinUrl`, `isCanceled`), que usan `daisyblade.js`, `calendar`, `map`, `upload` y `rich-text`. Orden: el cliente inyectado (`register*(Alpine, { axios })`), si no `window.axios`, y si no `fetch`. Una URL de otro dominio va siempre por `fetch`, sin `X-Requested-With` ni CSRF. Con `fetch` en el mismo dominio se envían `X-Requested-With`, `credentials: 'same-origin'` y el CSRF (de `<meta name="csrf-token">` o de la cookie `XSRF-TOKEN`) en lo que no es GET; el JSON se lee solo y un 4xx o 5xx falla con `error.response.status` y `error.response.data`, como en axios. Sin axios, la subida con progreso usa `XMLHttpRequest` con la misma forma de error. Un solo unidor de URL y parámetros (había tres copias). Pruebas `node --test` en `tests/js/http.test.mjs`.
- `registerDaisyBlade(Alpine, { axios })` (opcional) para inyectar el cliente en las factorías de `daisyblade.js`.
- `daisyblade:install` copia también `http.js` junto a `daisyblade.js`, porque este lo importa.
- `display.data-table`, `sections.tabs` y `form.select` con `options-url`: si la petición falla, mensaje traducido con botón «Reintentar» (el detalle va a la consola). Métodos `applyFilters(filters)` y `clearFilters()` en la factoría de la tabla.
- `resources/lang/ca.json`, con todas las claves de `es.json` en catalán. Textos nuevos para kv-editor, list-editor, node-graph, los errores por defecto de `daisyblade.js` y `daisyblade::map.init_failed`, en es, ca y en.
- `layout.app` y `layout.auth` incluyen la regla `[x-cloak] { display: none !important; }` en el `<head>`.
- `composer test` ejecuta también `node --test tests/js/*.test.mjs`.

### Corregido
- `form.select` con `options-url` no pedía nada: el Blade pasaba `url` a `dbSelectRemote` y la factoría leía `optionsUrl`. Ahora lee `url` (y sigue aceptando `optionsUrl`). Además `value` queda seleccionado al llegar las opciones y el control se envía con el formulario (`name`, `name[]` con `multiple`).
- Arranque doble: `data-table`, `resource-details`, `sidebar`, `sections.tabs`, `sections.wizard` y `form.select` tenían `x-init="init()"` además del `init()` que Alpine ya llama solo, así que pedían sus datos dos veces.
- `display.data-table`, `sections.tabs` y `form.select` con `options-url` se quedaban cargando para siempre cuando fallaba la petición (no había `catch`); `data-table` ya no pinta una respuesta vieja que llega después de una más nueva, y pulsar una pestaña cuya petición sigue en marcha no la repite.
- `display.data-table` no escuchaba a `form.filter` (`filter-apply` y `filter-clear`). Además, al limpiar los filtros no quitaba nada de la petición (`filters-cleared` no trae detalle) y elegir «Todos» en un filtro dejaba su valor anterior; ahora quita lo que los filtros habían añadido y devuelve el parámetro fijo de la prop `params` que taparan.
- `display.map`: las capas y el buscador de direcciones usaban `fetch` a pelo y no el `axios` que se pasa a `registerMap`; ahora usan el cliente común. El mensaje de desarrollo con que fallaba al arrancar (`falta { maplibregl }…`) ya no se enseña al usuario: ve un mensaje traducido y el detalle va a la consola.
- `form.kv-editor`, `form.list-editor` y `display.node-graph` tenían textos fijos en español (`clave`, `valor`, `tipo`, `campo`, `Eliminar`, `Añadir campo`, `Sin nodos`, los `title` de los botones) o en inglés (`Node N`, `root`, `child`); los errores por defecto de `daisyblade.js` («Error loading resource», «Upload failed», «An error occurred») eran siempre inglés. Ahora son traducciones. Se dejan los valores por defecto `Button`, `Avatar` y `Card Image`, que son el valor de una prop.
- Clases de Tailwind montadas en ejecución (`checkbox-{$color}`, `radio-`, `toggle-`, `bg-{{ $color }}`, `toast-`, `alert-`, `step-`, y las de `badge`, `progress`, `loading`, `radial-progress`, `text-rotate`, `tabs`, `menu`, `skeleton` e `indicator`): Tailwind no las encontraba al compilar. Ahora cada vista traduce el valor con un mapa de nombres escritos enteros. Valores admitidos: los colores de DaisyUI (`neutral`, `primary`, `secondary`, `accent`, `info`, `success`, `warning`, `error`; con `base-100`, `base-200`, `base-300`, los `*-content` (`primary-content`, `base-content`…) y `white` donde la clase es `bg-` o `text-`: `loading`, `radial-progress`, `text-rotate` y `avatar`, para que `<x-dbl::feedback.loading color="primary-content" />` dentro de un botón primario siga pintándose como en la 2.3), `start|center|end`, `top|middle|bottom`; un valor que no esté en la lista da el aspecto por defecto de la prop. `display.card` no cambia (`background` y `border-color` aceptan cualquier color de Tailwind).
- `daisyblade:make --type=3` generaba `axios.get(loadUrl)` con una variable JavaScript que no existe; ahora pasa `loadUrl` desde PHP.
- Documentación: `docs/components/flowchart.md` ejemplificaba un `fetch` PATCH sin CSRF; README: `sort`/`direction` (no `sort_by`/`sort_dir`) en el ejemplo de data-table, `:fields` (no `:schema`) en el de auto-form, `resource-details` clasificado como de tipo 3, `flowchart` en una sola tabla, `<script type="module">` para `daisyblade.js` (un `<script>` suelto falla con `export`), el contrato de error 422 y el número de pruebas.
- `form.select` con `options-url`: el `x-on:change` y el `:disabled` que la vista pasa al `<select>` desaparecían sin error, porque la plantilla escribía los suyos en el mismo `<select>` y el navegador se queda con el primero de dos atributos iguales. Ahora los escuchadores (`change` propio y `change` del control del que depende) y la ligadura de `disabled` y `aria-busy` van en el contenedor (`x-on:change.capture`, `x-on:change.window`, `x-effect="syncField()"`), y el `<select>` lleva solo lo que llevaba en la 2.3 más lo del usuario. Un select que la vista deshabilita con su propio `:disabled` no se vuelve a habilitar cuando llegan las opciones.
- `form.select` con `options-url`: una vista publicada con la 2.3 (con el `x-data` en el propio `<select>` y un `x-init="init()"`) junto al JavaScript de la 2.4 lanzaba dos `TypeError` por select y pedía las opciones dos veces en cada carga. Ahora la factoría encuentra el `<select>` sea cual sea el sitio del `x-data`, y el segundo `init()` espera al primero en vez de volver a pedir. Pasa si importas el JavaScript desde `vendor/` o publicas solo `daisyblade-assets` sin volver a publicar las vistas.
- `form.select` con `depends-on` sobre un `form.radio` o un `form.checkbox` (o `form.toggle`) leía el control equivocado: el valor del primer control con ese nombre, estuviera marcado o no (con radios, siempre el primero; con el checkbox, el `0` oculto aunque estuviera marcado). Ahora lee lo que el formulario enviaría: el radio marcado (cadena vacía si no hay ninguno, y entonces el select espera) y `1` o `0` en el checkbox.
- `form.select` con `options-url`, `display.data-table` y `display.resource-details`: cuando la sesión caduca, la URL de datos redirige al login y axios y `fetch` entregan su HTML como una respuesta 200 normal; el componente lo leía como «cero opciones» o «cero filas» y no enseñaba ningún error. Ahora una respuesta que es texto y no JSON cuenta como fallo: mensaje traducido con «Reintentar» (el detalle va a la consola). Una respuesta vacía (204) sigue siendo «nada».
- `display.flowchart`: una `x` o `y` negativa que mandara el padre se respetaba tal cual (al arrastrar y con el teclado ya no se llegaba a negativos), y la caja se pintaba cortada por el borde del lienzo, sin scroll para alcanzarla. Ahora `normalize` la lleva a 0.
- `display.flowchart`: el `icon` de un nodo se pintaba con `<x-dynamic-component>`, así que valía el nombre de cualquier componente Blade (`dbl::feedback.loading` dibujaba una rueda de carga dentro del nodo); si los nodos salen de datos que edita una persona, esa persona elegía qué componente se renderiza. Ahora se pide a blade-icons con `svg()`, que solo conoce iconos (`heroicon-o-...` o los de cualquier otro juego instalado); un nombre que no es un icono se omite. El diagrama es nuevo en la 2.4, así que ninguna vista de la 2.3 lo usaba.
- `display.data-table`, `display.resource-details`, `import.spreadsheet`, `sections.wizard`, `form.filter` y `sections.auto-form` escribían las URL (y `method`, `userId` y `formId` del asistente) dentro de un texto JavaScript con `'{{ $loadUrl }}'`: una comilla en la URL (`/rows?q=it's`) llegaba a Alpine como `'/rows?q=it's'`, un error de sintaxis que dejaba el componente muerto. Ahora van con `@js(...)`, como ya iba la configuración de `form.select`. No cambia ningún nombre.

Compatible hacia atrás: no cambia ningún nombre de propiedad, evento ni clase. Quien copie `daisyblade.js` a mano (sin `daisyblade:install`) debe copiar también `http.js`, que ahora importa. Cambios que se pueden ver: los textos de kv-editor salen con mayúscula inicial («Clave», «Valor», «Tipo»); `form.select` con `options-url` lleva su `x-data` en el contenedor y no en el `<select>` (el HTML es el mismo); el aviso de error de `auto-form` y `wizard` sale traducido en vez del mensaje técnico de axios cuando el servidor no manda `message` (una vista publicada antes de la 2.4 conserva el mensaje de antes).

**Si publicaste las vistas, vuelve a publicarlas** (`php artisan daisyblade:install`, o `php artisan vendor:publish --tag=daisyblade-views --force`). Una vista publicada con la 2.3 sigue funcionando con el JavaScript de la 2.4, sin errores ni peticiones dobles, pero no tiene lo que cambia en las vistas: por ejemplo `depends-on` y el mensaje con «Reintentar» de `form.select`, las URL con comillas de `display.data-table` y compañía, o los colores `*-content`.

## [2.3.0] — 2026-10-09

### Añadido
- `display.map`: prop `fit` (por defecto `false`). Al cargar por primera vez las capas visibles encuadra todos sus puntos (`fitBounds` en MapLibre, `LatLngBounds` en Google; margen y `maxZoom` 17). Sin puntos conserva `center`/`zoom`; no reencuadra al filtrar ni con `focus()`.
- `boundsOf(features)` en `map/features.js` (función pura) con tests; método `fit(bounds, maxZoom)` en los dos adaptadores.

### Corregido
- `form.select`: el bucle que calcula el texto seleccionado pisaba la variable `$label`, y con `value` o `options` salía una etiqueta visible con el texto de la última opción (p. ej. «English» sobre un selector de idioma).

Compatible hacia atrás: sin `fit` el comportamiento no cambia.

## [2.2.0] — 2026-10-09

### Añadido
- `display.map` (MapLibre y Google, misma interfaz de adaptador):
  - **Color por punto**: `properties.color` (hex válido) sustituye al color de la capa; si no es válido se usa el de la capa.
  - **Número en el marcador**: `properties.number` (entero o texto de 1 a 3 caracteres). MapLibre usa marcadores DOM (sin glyphs remotos); Google, el `label` del marker. Los puntos con número **no se agrupan** en clústeres.
  - **Abrir una ficha desde fuera**: método Alpine `focus(layerId, featureId)` y evento de ventana `dbl-map-focus` (`detail: {map, layer, id}`), con la nueva prop opcional `name` para identificar el mapa.
- Funciones puras en `resources/js/components/map/features.js` (validación de color, formato del número, búsqueda por id) con tests `node --test`.

Compatible hacia atrás: sin `color`, `number` ni `name` los mapas se comportan igual.

## [2.1.3] — 2026-10-08

### Añadido
- `form.select`: prop `value` (o un array con `multiple`) que deja marcada la opción en el select nativo, con grupos, y en el buscable (texto visible e input oculto). Antes no había forma de preseleccionar sin JS, y `x-init="… @js(…)"` no funciona dentro de los atributos de un componente, porque Blade no compila directivas ahí.

## [2.1.2] — 2026-10-08

### Corregido
- `display.calendar`: los títulos ponían en mayúscula cada palabra («Octubre De 2026»). Ahora solo la primera letra («Octubre de 2026»), con `first-letter:uppercase`.

## [2.1.1] — 2026-10-08

### Corregido
- `form.textarea`: nueva prop `value`, que se pinta como contenido del `<textarea>` (antes no había forma de darle un valor inicial). El mensaje de error cerraba un `<span>` con `</div>`.

## [2.1.0] — 2026-10-08

### Añadido
- `layout.app`: props `lang` y `htmlClass` (opcionales, compatibles con lo anterior).
- `display.map`: mapa con drivers MapLibre y Google (Strategy), bases OSM, IGN Base y PNOA, capas GeoJSON con agrupación, filtros y modo selector. Geocodificador PHP (`GeocoderManager`: CartoCiudad, Nominatim, Google) con el cliente HTTP y la caché inyectados. Doc: `docs/components/map.md`.
- `form.upload`: subida de ficheros con progreso, cancelar, reintentar, reordenar y cámara del móvil. Doc: `docs/components/upload.md`.
- `form.rich-text`: editor Tiptap 3. Doc: `docs/components/rich-text.md`.
- `form.signature`: firma en pantalla con signature_pad 5. Doc: `docs/components/signature.md`.
- `display.calendar`: calendario de mes y de lista sin dependencias. Doc: `docs/components/calendar.md`.

Los componentes nuevos son **opcionales**: su JS vive en `resources/js/components/*.js` y no está en `daisyblade.js`. Cada uno exporta `register<Nombre>(Alpine, deps)` y **recibe sus librerías inyectadas** (maplibre-gl, @tiptap/*, signature_pad, axios), así que solo instala esas dependencias quien use el componente.

## v2.0.1 — 2026-09-29

Dos fallos que hacían que los componentes se vieran mal en cualquier proyecto con DaisyUI 5,
encontrados al abrir un panel en el navegador (ninguno de los dos salía en los tests).

- **`actions/modal`: el contenido del modal era invisible.** DaisyUI 5 mantiene `.modal-box` a
  opacidad 0 salvo que su contenedor sea un `.modal` con `.modal-open`, y el componente usaba una
  estructura propia (`fixed inset-0 flex` con `x-show`). El HTML estaba en la página —los tests
  lo encontraban— pero el usuario no veía nada. Ahora usa la estructura de DaisyUI: contenedor
  `.modal` con `:class="open && 'modal-open'"`, panel `.modal-box` y `.modal-backdrop` para
  cerrar al hacer clic fuera. Las transiciones las hace DaisyUI, así que desaparecen las de Alpine.
- **Las traducciones no se aplicaban nunca.** Los componentes usan claves JSON (`__('Search...')`)
  pero el paquete solo registraba `loadTranslationsFrom()`, que es para ficheros PHP con espacio
  de nombres. Se añade `loadJsonTranslationsFrom()` y se completan `es.json` y `en.json` con las
  50 cadenas que usan hoy los componentes; antes había 20 y algunas ni coincidían (`Search` frente
  a `Search...`). Un test comprueba que no falte ninguna.

## v2.0.0 — 2026-09-01

**Sube a mayor porque rompe compatibilidad en dos sitios**, los dos marcados abajo: `display/card`
cambia a QUÉ elemento se aplica su prop `class` (antes al cuerpo, ahora a la tarjeta), y el
marcado de los formularios deja de emitir las clases de DaisyUI 4 (`form-control`, `label-text`,
`label-text-alt`), así que cualquier CSS propio que las apuntara deja de encontrar nada.

- `form/fields` y `form/repeater`: **soporte real de `type => 'hidden'`**. No existía, así que
  caía al `default => 'text'` del renderizador y una clave ajena —el `id` de la fila del repeater,
  el `student_id` de un formulario anidado— se pintaba como una caja de texto editable y estrecha,
  encajada entre dos etiquetas. Además de romper la maquetación, es peligroso: quien la edite
  reapunta el registro a otro padre, y en el repeater hace que al guardar se pise otra fila.
  Ahora no ocupa celda ni lleva etiqueta; en modo Alpine no se pinta nada (el valor ya viaja en el
  estado del formulario) y en modo form sale como `<input type="hidden">`.

- `layout/app`: el lienzo del contenido pasa a `bg-base-200`. Antes era `base-100`, el mismo color
  que las tarjetas, así que una tarjeta no se distinguía del fondo y una pantalla de varias
  secciones se leía como un bloque continuo. `layout/auth` ya usaba `base-200` por lo mismo.

- Tests: los que ejecutan `vendor:publish` o `daisyblade:install` **ya limpian lo que publican**
  (`afterEach` global en `tests/Pest.php`). Una vista publicada en el esqueleto de testbench TAPA
  la del paquete, así que la copia sobrevivía a la ejecución y la siguiente renderizaba la versión
  de ANTES del último cambio: de ahí el «falla una vez y a la segunda pasa» que se venía notando.
  Peor que la molestia era el riesgo contrario — dar por bueno un cambio roto porque lo probado
  era la copia vieja.

- `display/card`: **`class` ahora estiliza la tarjeta, no su interior.** Estaba declarado como
  prop y se colocaba en el `card-body`, así que un `class="mb-6"` —que en cualquier otro
  componente separa la tarjeta de la siguiente— acababa como margen interior y por fuera no movía
  nada. Los doce usos que hay en el proyecto son todos intención de fuera (`mb-6`,
  `mt-6 max-w-xl`): ninguno hacía lo que decía. Para el interior está el nuevo `bodyClass`.

- `form/fields`: el interruptor (`toggle`) ya no se monta con su etiqueta en una columna estrecha.
  `justify-between` reparte el espacio **sobrante**, y con una etiqueta larga en un `col-span-3`
  no sobra ninguno: texto e interruptor acababan encima. Ahora hay `gap-3`, la etiqueta puede
  encogerse (`min-w-0`) y el interruptor no (`shrink-0`).

- `form/fields` y `form/repeater`: **los anchos de columna no llegaban al CSS.** La clase se
  construía en ejecución (`'col-span-' . $n`), y Tailwind solo genera lo que encuentra **literal**
  al escanear los ficheros: `col-span-3` y `col-span-4` no existían en la hoja compilada, así que
  el ancho no se aplicaba y los campos caían todos en la misma fila, montándose unos con otros.
  Solo funcionaban por casualidad los valores que aparecían escritos en algún otro sitio (2, 6 y
  12). Ahora las doce clases van escritas enteras, con un test que lo vigila.

- **Migración a DaisyUI 5 del marcado de formularios.** La v5 eliminó `form-control`, `label-text`,
  `label-text-alt` y los sufijos `-bordered`, y redefinió `.label` como un `inline-flex` pensado
  para ir DENTRO de un `.input` (los complementos tipo «https://» pegados al campo), no encima de
  él. Nada de esto da error: las clases no existen y el navegador las ignora. El efecto era que
  **la etiqueta se quedaba a la izquierda del campo en vez de encima**, y solo se salvaban los
  campos con `w-full` — que fuerzan el salto de línea por accidente, no por diseño. Como todos los
  campos del renderizador llevan `w-full`, el fallo estaba escondido: se veía en cuanto alguien
  escribía un `<input class="input">` a mano, por ejemplo dentro de un modal.

  Afecta a `form/fields`, `form/repeater`, `form/input`, `form/textarea`, `form/select`,
  `form/checkbox`, `form/radio`, `form/toggle`, `form/filter`, `form/kv-editor`,
  `form/list-editor` y `display/filters`. El marcado pasa a utilidades explícitas de Tailwind
  (`mb-1 block text-sm font-medium` en la etiqueta, `mt-1 text-xs text-error` en el error), que no
  dependen de qué clases traiga la versión de turno de DaisyUI. Un test recorre todas las vistas
  del paquete y falla si alguna vuelve a usarlas.

  De paso, dos arreglos que salieron de aquí:
  - Las etiquetas en línea de `checkbox`, `radio` y `toggle` eran `inline-flex`, así que se
    encogían al contenido y `justify-between` **no repartía nada**: el interruptor quedaba pegado
    al texto en vez de al borde. Ahora son `flex w-full`.
  - `form/textarea`: el contador de caracteres (`maxLength`) quedaba fuera de la etiqueta y sin
    alinear.

<!--
  Anota aquí cada cambio EN CUANTO lo hagas, aunque no se vaya a publicar todavía: durante el
  desarrollo la app trabaja contra el repo local por enlace simbólico
  (`vendor/edumicro/daisyblade` → este directorio), así que los cambios se ven al instante y es
  fácil llegar al final de una tarde sin recordar qué entró.

  Para volver a la versión publicada:  composer install    (rehace vendor/ desde el lock)
-->

## v1.8.0 — 2026-09-01

- `sidebar`: nueva opción `floatingToggle`. El botón flotante vive abajo a la izquierda y el de
  cerrar arriba a la derecha, así que el control salta de punta a punta de la pantalla; quien ya
  tiene un toggle en su navbar prefiere uno solo y donde se mira. Por defecto sigue activo, que es
  lo correcto para un consumidor sin navbar.

## v1.7.0 — 2026-09-01

- `sidebar`: se recupera desde cualquier parte de la página despachando `sidebar-toggle` en
  `window`. Antes, cerrada, el único acceso de vuelta era su botón flotante; barra y navbar están
  en slots distintos del layout y no comparten ámbito Alpine. Con `collapsible => false` no se
  engancha el escuchador, porque esa opción promete que la barra no se cierra.

## v1.6.1 — 2026-09-01

- `auto-form`: **los formularios sin valores iniciales enviaban el payload vacío**. En PHP un
  array asociativo vacío se serializa como `[]`, no como `{}`, así que `values` se sembraba con un
  array de JavaScript; Alpine escribía `values.email` sin protestar, pero `JSON.stringify` descarta
  las propiedades con nombre de un array. El servidor recibía `[]` y respondía 422 «campo
  obligatorio». Afectaba a todos los formularios de alta, el de inicio de sesión incluido.

## v1.6.0 — 2026-09-01

- `node-graph`: componente genérico con modo editable y `drag-to-connect` (`@edge-create`).
