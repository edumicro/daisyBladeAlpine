# Cambios

Formato: lo que está sin publicar arriba. Al sacar versión, esa sección pasa a llevar su número
y su fecha, y se etiqueta.

## [2.4.1] — sin publicar

Solo arreglos, sin cambiar ningún nombre de propiedad, evento ni clase.

### Corregido
- `form.kv-editor`: un `true` volvía como `false` al guardar el formulario sin tocar la fila (la vista escribía `"1"` y el editor lee un booleano comparando con `'true'`). Ahora se escribe `true` o `false`. Prueba: `tests/Feature/KvEditorBoolTest.php`.
- `form.fields` (modo `form`) lanzaba «Undefined variable $errors» si la vista se pintaba sin la bolsa de errores compartida (un correo, un comando, un test). Prueba: `tests/Feature/FieldsWithoutErrorsBagTest.php`.

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
