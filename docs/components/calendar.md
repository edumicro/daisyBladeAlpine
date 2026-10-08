# Calendario (`display.calendar`)

Agenda pública de eventos o calendario de un panel. Alpine puro e `Intl`, sin librerías.

```blade
<x-dbl::display.calendar load-url="/api/eventos" x-on:event-click="console.log($event.detail)" />
```

## Props

| Prop | Por defecto | |
|---|---|---|
| `load-url` | (obligatoria) | Endpoint JSON, ver contrato. |
| `locale` | `app()->getLocale()` | Para `Intl`; `ca` se mapea a `ca-ES`. |
| `initial-date` | hoy | `YYYY-MM-DD`; fija el mes inicial. |
| `initial-view` | `month` (escritorio), `list` (< sm) | `month` o `list`. Si se indica, manda sobre el móvil. |
| `week-starts-on` | `1` | 0 = domingo, 1 = lunes... |
| `height` | auto | Altura CSS (`40rem`); el contenido hace scroll dentro. |

## Contrato JSON

`GET {load-url}?start=YYYY-MM-DD&end=YYYY-MM-DD` (rango de la cuadrícula visible, ambos inclusive).
Responde un array (o `{ "data": [...] }`):

```json
[
  { "id": 1, "title": "Asamblea", "start": "2026-10-10T18:30", "end": "2026-10-10T20:00",
    "all_day": false, "url": "/eventos/1", "color": "#e11d48", "subtitle": "Sede social" },
  { "id": 2, "title": "Jornadas", "start": "2026-10-20", "end": "2026-10-22", "all_day": true }
]
```

- `id`, `title`, `start` son obligatorios. `end`, `all_day`, `url`, `color` (cualquier color CSS) y `subtitle` son opcionales.
- Un `start` sin hora (`YYYY-MM-DD`) o `all_day: true` se muestra sin hora.
- Un evento de varios días aparece en cada día de `start` a `end`, ambos inclusive.
- Las respuestas se guardan en caché por rango: volver a un mes ya visto no repite la petición.
- Cliente HTTP: `axios` inyectado en `registerCalendar(Alpine, { axios })`, o `window.axios`, o `fetch`.

## Comportamiento

- **Mes**: 6 semanas, hoy marcado, otros meses atenuados, hasta 3 eventos por día y «+n más», que abre la lista de ese día.
- **Lista**: agrupada por día, con hora y título enlazado.
- **Teclado**: las flechas mueven el foco por los días (cambian de mes si hace falta); Enter abre la lista del día.
- **Estados**: cargando (`skeleton`), vacío y error con botón de reintento.
- **Evento `event-click`**: se dispara en el componente con el evento en `detail`; después navega a `url` si la tiene.

## Registro

```js
import { registerCalendar } from './components/calendar.js'
document.addEventListener('alpine:init', () => registerCalendar(window.Alpine, { axios: window.axios }))
```

Textos en `resources/lang/{es,ca,en}/calendar.php`. La lógica de fechas (`buildMonthGrid`, `eventDays`, `groupByDay`) está exportada y se prueba con `node --test tests/js/calendar.test.mjs`.
