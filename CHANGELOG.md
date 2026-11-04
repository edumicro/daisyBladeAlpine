# Cambios

Formato: lo que está sin publicar arriba. Al sacar versión, esa sección pasa a llevar su número
y su fecha, y se etiqueta.

## [3.0.0] — sin publicar

La 3.0 **cambia nombres** para que todo el paquete siga una sola convención. No arrastra los nombres viejos: **no hay alias ni avisos de obsoleto**. Quien no quiera migrar se queda en la línea `2.x`, que se sigue manteniendo. La sección `[2.4.0]` de más abajo es la de la 2.x y también está en esta línea (la 3.0 parte de ella).

### Guía de la 2 a la 3

Doce convenciones. Cada una tiene su párrafo y, después, una tabla por componente con el nombre de antes y el de ahora.

1. **Un solo prefijo, `dbl`.** Las etiquetas Blade ya eran `x-dbl::`. Ahora también lo son los eventos (`dbl-…`), las factorías de JavaScript (`dblSelectRemote`), las clases CSS propias (`dbl-flowchart`), los ids que genera un componente (`dbl-flowchart-<aleatorio>-help`) y las claves de localStorage (`dbl_sidebar`, `dbl_wizard_…`). Al leer, si solo existe la clave vieja `db_…`, su valor se mueve una vez a la nueva y la vieja se borra: nadie pierde la barra lateral ni el progreso de un asistente.
2. **Propiedades de URL.** En PHP camelCase, en la etiqueta kebab-case, y las claves de los arrays de configuración en snake_case (como en Laravel). Cada URL dice para qué sirve: `load-url` (de dónde lee el componente sus datos), `submit-url` (adónde manda lo que el usuario rellenó), `upload-url` (adónde van los ficheros), `geocode-url` (se queda). Las claves de array que eran camelCase pasan a snake_case: `badge_color`, `view_url`, `line_number`.
3. **Valor.** `value` es un solo valor, en todos los controles de formulario (input, checkbox y toggle lo ganan; checkbox y toggle quedan marcados si es verdadero). `values` es solo un mapa nombre → valor: auto-form, fields y los pasos del wizard.
4. **Ayuda.** `hint` en todos los controles de formulario; `description` desaparece de ellos. Se pinta siempre debajo del control. select, radio y repeater ganan `hint`.
5. **Tamaño.** `size` es siempre un token `xs|sm|md|lg|xl` (más los extras con sentido: `full` en modal, `2xl` en avatar, `2xl`, `3xl` y `4xl` en text-rotate). El componente lo traduce a su clase con un `match` de nombres escritos enteros; un valor desconocido da `md`. input, textarea, checkbox, radio y toggle ganan `size`.
6. **Color.** `color` es siempre un token (`primary|secondary|accent|neutral|info|success|warning|error`, más `ghost|link` en los botones y `ghost` en badge), nunca la clase. `background` es el único nombre del fondo, con un token `base-100|base-200|base-300` o uno de los colores. Lo que no esté en la lista va por `class`.
7. **`class` y los demás atributos van al elemento principal del componente**, y todas las vistas aplican `$attributes` (las 27 que no lo hacían, también). El elemento principal es la raíz, como en cualquier componente de Laravel; **en un control de formulario es el propio control** (el `<input>`, el `<textarea>`, el `<select>`, el checkbox, cada radio, el interruptor y, en rich-text, signature y upload, la caja que hace de control), como en el `text-input` de Breeze: quien escribe `<x-dbl::form.input type="number" min="0" x-model="qty">` tiene el `min` y el `x-model` en el `<input>`, sin excepciones. La caja de fuera del control (etiqueta, ayuda, error) recibe sus clases con **`wrapper-class`**. Para dar clases a otra parte interior hay una propiedad `<parte>-class` (`canvas-class`, `table-class`...). `containerClass` desaparece. **Qué pasaba en la 2:** en un control `class` iba al control y `containerClass` a la caja, y el resto de atributos (`min`, `x-model`...) al control; en un componente que no era un control `class` era a veces la raíz, a veces una parte, y `containerClass` la caja, y 27 vistas no aplicaban `$attributes`. **Qué pasa ahora:** en un control, `class` y todos los atributos siguen en el control y `container-class` pasa a `wrapper-class`; en los demás, `class` y los atributos van a la raíz, `container-class` desaparece y la parte a la que iba el `class` viejo tiene su `<parte>-class`. Los editores compuestos (kv-editor, list-editor, repeater) no son un control sino un conjunto: su elemento principal es la raíz.
8. **Eventos.** Lo que un componente anuncia se llama `dbl-<componente>-<lo que pasó, en participio>`; las órdenes que escucha en la ventana, `dbl-<componente>-<verbo>`. Siempre con `$dispatch` desde el componente (sube por el DOM) y el `detail` es siempre un objeto. El select nativo y el remoto ahora también lanzan `dbl-select-changed` `{ name, value }`, y rich-text lanza `dbl-rich-text-changed`. La excepción a `$dispatch` es display.flowchart, que despacha desde su raíz con las mismas opciones porque necesita saber si un oyente llamó a `preventDefault()`.
9. **Un solo componente de filtros:** `x-dbl::form.filters`, con `filters[clave]` y los eventos `dbl-filters-applied` y `dbl-filters-cleared`, los dos con `{ filters }`. `form.filter` y `display.filters` desaparecen; `display.data-table` escucha esos dos eventos y nada más.
10. **La configuración llega al JavaScript de una sola forma:** `x-data="dblComponente(@js($config))"`. Fuera el `'{{ $url }}'` dentro de un texto JS (una comilla lo rompía), el `JSON.parse($el.dataset.config)` y el atributo `data-config`, `Js::from` y los objetos en línea con datos de PHP. Un `x-data` en línea sin datos de PHP puede quedarse.
11. **Limpieza.** Fuera la configuración muerta de `config/daisyblade.php` (`component_map`, `defaults`, `toast`, `sidebar`; quedan `map` y `geo`) y la factoría que nadie usaba. `daisyblade:install` publica también `components/`, y las traducciones tienen la etiqueta `daisyblade-lang`.
12. **El fallo del wizard.** Los `values` de los pasos son el punto de partida y el progreso guardado de ese mismo asistente se aplica encima: antes un `x-init` ponía los valores después de restaurar el progreso y lo pisaba.

Para pasar de la 2 a la 3, por este orden: (1) vuelve a publicar las vistas (`php artisan daisyblade:install`; las vistas de la 2.x no funcionan con el JavaScript de la 3.x ni al revés); (2) busca en tus plantillas los nombres de las tablas de abajo (`options-url=`, `action=` en upload, auto-form y wizard, `variant=`, `container-class=`, `description=`, `x-on:select-change`...); (3) cambia los eventos que escuchas y las órdenes que lanzas; (4) pasa de la clase al token en `size` y `color`.

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
| `node-click`, `edge-click`, `node-move`, `edge-create` (display.flowchart) | `dbl-flowchart-node-clicked`, `-edge-clicked`, `-node-moved`, `-edge-created` |
| `node-click`, `badge-click`, `node-create`, `node-delete`, `badge-create`, `badge-delete`, `edge-create` (display.node-graph) | `dbl-node-graph-node-clicked`, `-badge-clicked`, `-node-created`, `-node-deleted`, `-badge-created`, `-badge-deleted`, `-edge-created` |
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
| `display.calendar` | `event-click`; `dbCalendar` | `dbl-calendar-event-clicked`; `dblCalendar` |
| `display.card` | `background` (cualquier color), `border-color`, `actions[].variant` (`btn-primary`) | `background` es un token; `border-color` fuera (`class="border border-primary"`); `actions[].color` (`primary`) |
| `display.carousel` | `class` (diapositiva), `container-class` (la tira) | `slide-class`, `carousel-class`; `class` a la raíz |
| `display.chat-bubble` | `class` | `bubble-class` |
| `display.collapse` | `variant` (tinte del contenido), `class` (título), `container-class` | `background`, `title-class`, `class` |
| `display.compare`, `display.table` | `class` (tabla), `container-class` | `table-class`, `class` |
| `display.data-table` | `class` (tabla), `container-class`; escucha `filters-updated`, `filters-cleared`, `filter-apply`, `filter-clear`; lleva `display.filters` | `table-class`, `class`; escucha `dbl-filters-applied` y `dbl-filters-cleared`; lleva `form.filters` |
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
| `display.resource-details` | `class` (contenido), `container-class` | `content-class`, `class` |
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
| `form.checkbox`, `form.toggle` | `class` (control), `container-class`, `description`; sin `value` ni `size` | `class` (control), `wrapper-class`, `hint`; `value`, `size` |
| `form.radio` | `class` (cada radio), `container-class`; sin `hint` ni `size` | `class` (cada radio), `wrapper-class`; `hint`, `size` |
| `form.select` | `options-url`, `class` (control), `container-class`; `select-change` solo en el buscable; sin `hint` | `load-url`, `class` (el `<select>`; en el modo `searchable`, la caja de texto), `wrapper-class`; `dbl-select-changed` en los tres modos; `hint` |
| `form.rich-text` | `description`, `container-class` (la raíz); no lanzaba nada | `hint`, `wrapper-class`; `class` y los atributos van a la caja del editor; `dbl-rich-text-changed` |
| `form.signature` | `description`, `class` (la raíz); `signed`, `cleared` | `hint`, `wrapper-class`; `class` y los atributos van al lienzo; `dbl-signature-signed`, `dbl-signature-cleared` |
| `form.upload` | `action`, `description`, `class` (zona de soltar), `container-class`; `uploaded`, `removed` | `upload-url`, `hint`, `class` (zona de soltar), `wrapper-class`; `dbl-upload-uploaded`, `dbl-upload-removed` |
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
| `sections.auto-form` | `action`, `class` (el `<form>`), `container-class` | `submit-url`, `form-class`, `class` |
| `sections.wizard` | `action`, `container-class`; `x-init` con los valores | `submit-url`, `class`; los `values` viajan en la configuración |
| `sections.tabs` | clave `'load-url'`, `class` (panel), `container-class` | clave `'load_url'`, `panel-class`, `class` |
| `import.spreadsheet` | `container-class` | `class` (`upload-url` y `template-url` no cambian) |

#### Otros cambios que se ven

- `form.checkbox` y `form.toggle` quedan marcados con `value` verdadero; lo que se envía sigue siendo `1` (y `0` si no está marcado).
- `form.fields` y `x-model`: el enlace usa `values['clave']` en lugar de `values.clave`.
- `display.card` pone un color de texto (`text-primary-content`...) a juego con el fondo.
- En un control de formulario, `id` es el del control y la etiqueta apunta a él (a `name` si no escribes `id`). En el modo `searchable` de `form.select` el control es la caja de texto (su texto se enlaza con `:value` y `@input`, no con `x-model`, para que el `x-model` de quien lo usa no se repita).
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

### Quitado
- Todos los nombres de la tabla de arriba, sin alias.
- `display.filters`, `form.filter`, la factoría `dbTabs`, y `component_map`, `defaults`, `toast` y `sidebar` de `config/daisyblade.php`.

## [2.4.0] — sin publicar

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
