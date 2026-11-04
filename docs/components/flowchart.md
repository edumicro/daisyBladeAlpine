# Diagrama de flujo (`display.flowchart`)

Diagrama de cajas y flechas sobre un lienzo, con arrastrar y soltar. SVG y Alpine puro, sin librerías.
No sabe qué dibuja: un organigrama, un mapa de procesos, un flujo de aprobación o los estados de una tarea. **Emite eventos y el padre decide**; no hace llamadas al servidor.

A diferencia de `display.node-graph` (un árbol, cada nodo con un padre), es un grafo libre: admite ciclos, flechas de un nodo a sí mismo y **varias flechas entre el mismo par** con distinta etiqueta.

```blade
<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" editable name="flujo" :grid="20"
    x-on:node-click="abrir($event.detail.id)" x-on:edge-click="abrir($event.detail.id)"
    x-on:edge-create="..." x-on:node-move="..." />
```

```php
$nodes = [
    ['id' => 'inicio',  'label' => 'Inicio', 'shape' => 'pill', 'variant' => 'success', 'group' => 'Solicitante'],
    ['id' => 'solicitud', 'label' => 'Solicitud', 'description' => 'Se rellena el formulario', 'icon' => 'heroicon-o-document-text', 'group' => 'Solicitante'],
    ['id' => 'decide',  'label' => '¿Aprobado?', 'shape' => 'diamond', 'group' => 'Revisor'],
    ['id' => 'envio',   'label' => 'Envío', 'group' => 'Revisor', 'x' => 400, 'y' => 200],
    ['id' => 'borrador', 'label' => 'Borrador', 'muted' => true],
];
$edges = [
    ['id' => 1, 'from' => 'inicio', 'to' => 'solicitud'],
    ['id' => 2, 'from' => 'solicitud', 'to' => 'decide', 'label' => 'se envía'],
    ['id' => 3, 'from' => 'solicitud', 'to' => 'decide', 'label' => 'se adjunta'],   // varias entre el mismo par
    ['id' => 4, 'from' => 'decide', 'to' => 'solicitud', 'label' => 'se devuelve'],  // ida y vuelta
    ['id' => 5, 'from' => 'decide', 'to' => 'envio',  'label' => 'sí', 'variant' => 'success'],
    ['id' => 6, 'from' => 'envio',  'to' => 'envio',  'label' => 'se reintenta'], // bucle
];
```

## Props

| Prop | Por defecto | |
|---|---|---|
| `nodes` | `[]` | Ver «Nodos». |
| `edges` | `[]` | `[{id, from, to, label?, variant?, muted?, flat?}]`. Una flecha cuyo `from` o `to` no existe se descarta. |
| `direction` | `vertical` | Colocación automática: `vertical` (de arriba abajo) u `horizontal` (de izquierda a derecha). |
| `editable` | `false` | Mover cajas y crear flechas. |
| `name` | `''` | Identifica el diagrama para el evento de ventana `dbl-flowchart`. |
| `height` | `28rem` | Altura CSS del lienzo (`px`, `rem`, `em`, `vh` o `%`); el contenido hace scroll dentro. |
| `grid` | `0` | Paso de ajuste (px) al soltar una caja; `0` = sin ajuste. |
| `fit` | `false` | Como en `display.map`: al cargar, encuadra todo el dibujo en el lienzo (escala máxima 1: no agranda) y lo deja **centrado** en el eje en que sobra sitio. A 390 px se ve entero, aunque pequeño. El botón de encuadrar y el zoom también lo centran mientras el dibujo cabe en la vista. |
| `show-muted` | `false` | Pinta también los `muted`, en discontinua. Sin él hay un interruptor visible (solo si hay algo oculto). |
| `label`, `class`, `container-class` | | Título, clases del lienzo y clases del contenedor. |

### Nodos

| Clave | |
|---|---|
| `id`, `label` | Obligatorio el `id` (número o texto; los eventos devuelven el valor original). Sin `label` se muestra el `id`. |
| `x`, `y` | Posición. Sin ellas el nodo se coloca solo; con ellas se respeta. |
| `shape` | `rectangle`, `rounded` (por defecto), `pill` (inicio/fin), `diamond` (decisión), `circle`. Las flechas cortan el borde de cada forma. |
| `description` | Segunda línea de texto, más pequeña. |
| `icon` | Nombre de un componente de icono (`heroicon-o-...`), el mismo sistema que `status`, `timeline` o `avatar`. Se pinta en el servidor; un nombre que no existe se omite. |
| `badge` | Texto corto sobre la esquina de la caja. |
| `group` | Nombre de grupo. Los nodos con el mismo nombre quedan dentro de una banda de fondo con ese nombre (departamentos, calles de un proceso). |
| `variant` | `success`, `warning`, `error`, `neutral` (el mismo mapa que `node-graph`); cualquier otro valor se ignora. |
| `muted` | Se oculta, y con él las flechas que llegan o salen de ese nodo. |
| `flat` | Dato opaco del padre; vuelve tal cual en cada evento. |

La colocación automática sigue las flechas por capas (los ciclos no la rompen), ordena cada capa para reducir cruces y centra cada padre sobre sus hijos (o a su izquierda, en `horizontal`). Sin flechas entre los nodos, los reparte en rejilla. Con `group`, cada grupo es una **calle** (una franja a lo largo de las capas), así que las bandas no se solapan entre sí; los nodos sin grupo van libres, sin calle. Sin cajas con `x`/`y` fijas, el dibujo se normaliza para empezar en el margen, sin hueco arriba ni a la izquierda. Si pones `x`/`y` a mano, la banda se limita a rodear a sus miembros y puede cubrir otras cajas.

Los textos de `label`, `description`, `badge` y de las flechas se escapan siempre; el SVG de los iconos viene del servidor, no del padre.

## Eventos

Se despachan en el componente (burbujean: se escuchan con `x-on:` en la propia etiqueta).

| Evento | `detail` | Cuándo |
|---|---|---|
| `node-click` | `{id, flat}` | Clic o toque en una caja, o Enter. Siempre. |
| `edge-click` | `{id, flat}` | Clic en una flecha o su etiqueta, o Enter. Siempre. |
| `node-move` | `{id, x, y, flat, accept, reject}` | Con `editable`, al soltar una caja (ajustada a `grid`) o al soltar la tecla de flecha. |
| `edge-create` | `{fromId, toId, fromFlat, toFlat, accept, reject}` | Con `editable`, al arrastrar desde el asa de una caja y soltar sobre otra (o sobre sí misma). |

### Responder a los eventos de edición

`node-move` y `edge-create` se pueden **responder**: el padre aplica su lógica (abrir su diálogo, preguntar a su servidor) y el componente no sabe nada de ella.

- **`edge-create`**: al soltar, la flecha ya aparece, **provisional** (discontinua, sin etiqueta).
  - `accept(datos?)` la fija. `datos` completa la flecha: `{id, label, variant, muted, flat}`. `from` y `to` no se pueden cambiar. Sin `id` se genera uno.
  - `reject(mensaje?)` la quita. Con mensaje, se avisa con el sistema del paquete: se emite el evento de ventana `notify` (`detail: {message, type: 'error'}`) que recoge `<x-dbl::feedback.toast />`, presente en `layout.app`.
- **`node-move`**: al soltar, la caja se queda en su sitio nuevo. `reject(mensaje?)` la devuelve sola a su posición anterior (si ya la moviste otra vez, no se toca). `accept()` existe por simetría y no hace nada.

**Cómo decide el componente si alguien responde.** Los eventos son cancelables y el criterio es el de cualquier evento del DOM:

1. Si el oyente **no** llama a `$event.preventDefault()` ni a `accept`/`reject` de forma síncrona, la edición se acepta tal cual en cuanto termina el despacho. Así funciona en una página sin servidor.
2. Si llama a `$event.preventDefault()`, **se queda la decisión**: el componente espera (el tiempo que haga falta, es asíncrono) hasta que llame a `accept` o `reject`.
3. Llamar a `accept` o `reject` de forma síncrona, sin `preventDefault()`, también vale.
4. Solo cuenta la primera respuesta; las siguientes no hacen nada. Si entretanto el estado se reemplaza con `set()`, una respuesta tardía tampoco hace nada.

Ejemplo: al unir dos pasos, preguntar con un diálogo propio la condición y aceptar con esa etiqueta, o rechazar.

```blade
<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" editable name="flujo"
    x-on:edge-create="
        $event.preventDefault()                         // me quedo la decisión
        const ev = $event.detail
        $store.dialogo.preguntar('¿Qué condición se cumple?').then(
            condicion => ev.accept({ label: condicion, flat: { condicion } }),
            () => ev.reject('No se ha creado la flecha'))
    "
    x-on:node-move="
        $event.preventDefault()
        const ev = $event.detail
        axios.patch('/pasos/' + ev.id, { x: ev.x, y: ev.y })
            .then(() => ev.accept(), () => ev.reject('No se ha podido guardar la posición'))
    " />
```

(`axios` de Laravel ya envía el token CSRF y `X-Requested-With`. Con un `fetch` a pelo hay que añadir la cabecera `X-CSRF-TOKEN`, o el `PATCH` devuelve 419.)

## Desde fuera

Métodos Alpine y su equivalente como evento de ventana (necesita `name`):

| Método | Evento de ventana | |
|---|---|---|
| `set(nodes, edges)` | `{name, action: 'set', nodes, edges}` | Reemplaza el estado. Los nodos sin `x`/`y` conservan la posición que ya tenían. |
| `focus(id)` | `{name, action: 'focus', id}` | Centra y resalta un nodo; si estaba oculto por `muted`, muestra los ocultos. |
| `removeEdge(id)` | `{name, action: 'removeEdge', id}` | Quita una flecha (p. ej. tras un `edge-click`). No emite nada. |
| `removeNode(id)` | `{name, action: 'removeNode', id}` | Quita un nodo y las flechas que llegan o salen de él. No emite nada. |
| `zoomIn()`, `zoomOut()`, `fit()` | | Zoom (de 0,1 a 2) y encuadre; son los botones +, − y encuadrar de la esquina del lienzo. |

```js
window.dispatchEvent(new CustomEvent('dbl-flowchart', { detail: { name: 'flujo', action: 'focus', id: 'envio' } }))
```

## Comportamiento

- **Puntero**: Pointer Events, así que ratón, táctil y lápiz funcionan igual. Las cajas editables tienen `touch-action: none` (el arrastre no desplaza la página); fuera de ellas el lienzo se desplaza con normalidad. Un movimiento menor de 4 px cuenta como clic.
- **Zoom**: botones +, − y encuadrar (con textos traducidos). El puntero y el teclado trabajan siempre en píxeles del dibujo, así que arrastrar funciona igual con cualquier escala.
- **Flechas nuevas**: el asa, pequeña, está en la esquina inferior derecha de cada caja y solo se ve con el puntero encima o con la caja enfocada (en pantallas táctiles, `hover: none`, siempre). Mientras se arrastra hay una línea provisional que sigue al puntero y la caja de destino se resalta. Soltar el asa sin moverla, o en el vacío, no emite nada.
- **Flechas**: con punta, salen y llegan al borde de la caja. Varias entre el mismo par, y la ida y vuelta, se reparten en curvas separadas, con etiquetas que no se pisan. Una flecha de un nodo a sí mismo es un bucle bajo la caja.
- **Teclado**: Tab recorre cajas y flechas; Enter (o Espacio) dispara el clic. Con `editable`, las flechas del teclado mueven la caja seleccionada 10 px (con Shift, un paso de `grid`, o 20 px si no hay rejilla) y **al soltar la tecla** se emite `node-move`. Cajas y flechas llevan `aria-label`.
- **Tema**: usa las variables de color de DaisyUI (`--color-base-100`, `--color-primary`, ...), así que sigue el tema claro u oscuro. Funciona a 390 px de ancho: el lienzo hace scroll dentro, no la página.
- El lienzo no tiene coordenadas negativas: las cajas no se pueden arrastrar más allá del borde superior o izquierdo, y una `x` o `y` negativa que mande el padre se lleva a 0.

## Slots

Sin slots a propósito: las cajas se pintan en el cliente a partir de los datos (como `data-table` con sus filas), así que el contenido propio de cada nodo se expresa con `description`, `icon` y `badge`.

## Registro

```js
import { registerFlowchart } from './components/flowchart.js'
document.addEventListener('alpine:init', () => registerFlowchart(window.Alpine))
```

Sin dependencias npm. Textos en `resources/lang/{es,ca,en}/flowchart.php`. La lógica pura está en `resources/js/components/flowchart/`: `geometry.js` (colocación automática, rejilla, corte con el borde de cada forma, curvas, bucles, bandas, límites) y `protocol.js` (provisional, aceptar y rechazar). Se prueba con `node --test tests/js/flowchart.test.mjs`.
