# DAISYBLADE — Prompt de contexto para Claude Code

## Qué somos

Estamos creando `edumicro/daisyblade`, un paquete Laravel de componentes Blade + Alpine.js + DaisyUI para interfaces ERP/CRM. Es la evolución de `edumicro/daisylw4` (mismo autor), eliminando la dependencia de Livewire y sustituyéndola por Blade puro + Alpine.js + Axios.

**Stack objetivo:** Laravel 12 · PHP 8.3 · Alpine.js 3 · Axios · DaisyUI 5 · blade-heroicons

**Repo de origen (referencia, no copiar lógica Livewire):** https://packagist.org/packages/edumicro/daisylw4

---

## Arquitectura de DaisyBlade

### Principio central
Cada componente es un **Blade Component** puro. El estado interactivo vive en Alpine.js. Las llamadas al servidor son Axios explícitas contra controladores Laravel normales. Sin protocolo Livewire, sin websockets, sin magia.

### Tres tipos de componentes

**Tipo 1 — Estáticos** (solo HTML + DaisyUI, sin JS)
Reciben props Blade, renderizan HTML. Sin `x-data`. Sin llamadas al servidor.
Ejemplos: `badge`, `avatar`, `stat`, `card`, `alert`, `kbd`, `divider`, `timeline`...

**Tipo 2 — Interactivos locales** (Alpine puro, sin servidor)
Estado efímero de UI gestionado por Alpine. Sin Axios.
Ejemplos: `modal`, `tabs` (navigation), `accordion`, `collapse`, `swap`, `toast`

**Tipo 3 — Interactivos remotos** (Alpine + Axios)
Se instancian con una URL de carga y opcionalmente URLs de acción.
Ejemplos: `data-table`, `auto-form`, `select` (con búsqueda remota), `import/spreadsheet`

### Patrón de instanciación Tipo 3

```blade
{{-- En la vista --}}
<x-db::display.data-table
    :load-url="route('users.data')"
    :columns="$columns"
    :per-page="15"
/>
```

```blade
{{-- En el componente Blade --}}
<div x-data="dbDataTable(@js($props))" x-init="init()">
    ...
</div>
```

```js
// En resources/js/daisyblade.js
function dbDataTable(config) {
    return {
        rows: [], loading: true, page: 1, search: '', sort: null,
        async init() { await this.load() },
        async load() {
            this.loading = true
            const { data } = await axios.get(config.loadUrl, {
                params: { page: this.page, search: this.search, sort: this.sort }
            })
            this.rows = data.data
            this.meta  = data.meta
            this.loading = false
        }
    }
}
```

### auto-form: declarativo, submit al servidor

El schema se mantiene idéntico al de daisylw4. Lo que cambia es el submit:
- **No** usa Livewire methods
- Submit → Axios POST → el servidor devuelve `{success: true, redirect: '...'}` o `{success: false, errors: {...}}`
- Errores se muestran inline en Alpine sin roundtrip de renderizado

```blade
<x-db::form.auto-form
    :schema="[
        ['name' => 'name',        'label' => 'Nombre',    'order' => 10],
        ['name' => 'category_id', 'type'  => 'relation',  'order' => 20],
        ['name' => 'price',       'type'  => 'money',     'order' => 30],
    ]"
    action="{{ route('products.store') }}"
    method="POST"
/>
```

### sections/wizard: LocalStorage con clave versionada

```js
function dbWizard(config) {
    const storageKey = `db_wizard_${config.userId}_${config.formId}_v${config.schemaVersion}`
    return {
        step: 0,
        data: {},
        init() {
            const saved = localStorage.getItem(storageKey)
            if (saved) { const p = JSON.parse(saved); this.step = p.step; this.data = p.data }
        },
        save() { localStorage.setItem(storageKey, JSON.stringify({step: this.step, data: this.data})) },
        clear() { localStorage.removeItem(storageKey) },
        next() { this.step++; this.save() },
        prev() { this.step--; this.save() },
        async submit() {
            const { data } = await axios.post(config.action, this.data)
            if (data.success) { this.clear(); window.location = data.redirect }
            else this.errors = data.errors
        }
    }
}
```

---

## Estructura del paquete

```
edumicro/daisyblade/
├── composer.json
├── config/
│   └── daisyblade.php
├── resources/
│   ├── js/
│   │   └── daisyblade.js          ← funciones Alpine globales (dbDataTable, dbAutoForm, dbWizard...)
│   ├── lang/
│   │   ├── en.json
│   │   └── es.json
│   └── views/
│       └── daisyblade/
│           ├── actions/            button, fab, modal, swap
│           ├── components/         icon, ui/logo
│           ├── display/            (ver lista completa abajo)
│           ├── feedback/           alert, loading, progress, skeleton, toast, tooltip
│           ├── form/               auto-form, input, select, checkbox, etc.
│           ├── import/             spreadsheet
│           ├── layout/             app, auth, sidebar, navbar, etc.
│           ├── navigation/         breadcrumb, tabs, pagination, etc.
│           └── sections/           tabs, wizard
├── src/
│   ├── DaisyBladeServiceProvider.php
│   ├── Console/Commands/
│   │   ├── InstallCommand.php
│   │   └── MakeDaisyBladeComponent.php
│   └── Imports/                    ← se mantiene de daisylw4, no depende de Livewire
│       ├── PreviewReadFilter.php
│       ├── SpreadsheetChunkImport.php
│       └── SpreadsheetStreamer.php
└── tests/
```

### Namespace de componentes Blade
- Prefijo: `db` (daisyblade)
- Uso: `<x-db::display.data-table ...>`, `<x-db::form.input ...>`, etc.

---

## Lista completa de componentes y su tipo de migración

### TIPO 1 — Copy & paste (HTML + DaisyUI, sin lógica Livewire)
Estos se copian de daisylw4 sustituyendo cualquier `wire:` por nada o por Alpine equivalente mínimo.

```
display/    accordion, avatar, badge, calendar*, card, carousel*, chart*,
            chat-bubble, collapse, diff, hover-3d-card, hover-gallery,
            kbd, list, mask, radial-progress, stat, status, table,
            text-rotate, timeline
feedback/   alert, loading, progress, skeleton, tooltip
layout/     divider, footer, hero, indicator, join, section-wrapper, stack
navigation/ breadcrumb, dock, steps
actions/    button, fab, swap
components/ icon, ui/logo
```
(*) `calendar`, `carousel`, `chart` pueden tener pequeña interactividad Alpine interna.

### TIPO 2 — Interactivos locales (Alpine, sin Axios)
```
actions/    modal
feedback/   toast
navigation/ tabs, navbar, sidebar, sidebar-tree
layout/     app, auth
```

### TIPO 3 — Interactivos remotos (Alpine + Axios + URL props)
```
display/    data-table, datatable-filters, resource-details
form/       auto-form, auto-form-field-render, select (con búsqueda remota),
            fileinput, filter
navigation/ pagination (como subcomponente de data-table)
import/     spreadsheet
sections/   tabs (lazy-load por tab), wizard (LocalStorage)
```

---

## Orden de construcción sugerido

### Fase 1 — Scaffolding del paquete
1. `composer.json` sin dependencia de livewire
2. `DaisyBladeServiceProvider` con registro de componentes prefijo `db`
3. `config/daisyblade.php`
4. `resources/js/daisyblade.js` (vacío con estructura de módulo)
5. `InstallCommand` que publica vistas y assets

### Fase 2 — Tipo 1 completo (copy & paste con limpieza)
Migrar todos los componentes Tipo 1. Regla: si hay `wire:` en el blade original, eliminar o sustituir por prop estática.

### Fase 3 — Tipo 2 (Alpine local)
`modal`, `toast`, `tabs` (navigation), `navbar`, `sidebar`

### Fase 4 — data-table (primer Tipo 3, valida el patrón)
Este es el más usado en ERP. Validar aquí el patrón Alpine + Axios antes de `auto-form`.

### Fase 5 — auto-form
El más complejo. Requiere que el patrón de Fase 4 esté validado.

### Fase 6 — Resto Tipo 3
`sections/wizard`, `sections/tabs`, `import/spreadsheet`

---

## Reglas de diseño

1. **Cero dependencias de Livewire.** El `composer.json` no debe tener `livewire/livewire`.
2. **Alpine como única capa JS.** No jQuery, no Vue, no React.
3. **Axios para llamadas remotas.** Siempre devolver JSON `{success, data, errors, redirect}`.
4. **Props explícitas.** Cada componente declara sus props en `@props([])`. Sin `$attributes->get()` implícito para lógica.
5. **URLs como strings, nunca rutas hardcoded.** El componente recibe `:load-url="route(...)"`, no construye la URL internamente.
6. **daisyblade.js es el único JS del paquete.** Las funciones Alpine (`dbDataTable`, `dbAutoForm`, etc.) viven ahí y se registran en `window`.
7. **El estado de formulario vive en Alpine, no en el servidor.** Validación inline = Alpine. Validación real = Laravel al submit.
8. **Heroicons via blade-heroicons.** Misma dependencia que daisylw4 (`blade-ui-kit/blade-heroicons`).
9. **i18n via `__()` con namespace `daisyblade::`** para los textos del paquete.
10. **Tests con Pest.** Estructura de tests igual que daisylw4.

---

## composer.json de referencia

```json
{
    "name": "edumicro/daisyblade",
    "description": "Pure Blade + Alpine.js UI components for Laravel ERP/CRM. DaisyUI styled.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "blade-ui-kit/blade-heroicons": "^2.4",
        "illuminate/support": "^11.0|^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^10.9",
        "pestphp/pest": "^4.3",
        "pestphp/pest-plugin-laravel": "^4.0"
    },
    "suggests": {
        "maatwebsite/excel": "Required by the daisyblade.import.spreadsheet component (^3.1)"
    },
    "autoload": {
        "psr-4": { "Edumicro\\DaisyBlade\\": "src/" }
    },
    "extra": {
        "laravel": {
            "providers": ["Edumicro\\DaisyBlade\\DaisyBladeServiceProvider"]
        }
    }
}
```

---

## DaisyBladeServiceProvider de referencia

```php
<?php

namespace Edumicro\DaisyBlade;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class DaisyBladeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'daisyblade');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'daisyblade');

        Blade::componentNamespace('Edumicro\\DaisyBlade\\View\\Components', 'db');

        // Componentes anónimos (solo Blade, sin clase PHP)
        Blade::anonymousComponentNamespace('daisyblade', 'db');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/daisyblade'),
            ], 'daisyblade-views');

            $this->publishes([
                __DIR__.'/../resources/js/daisyblade.js' => public_path('vendor/daisyblade/daisyblade.js'),
            ], 'daisyblade-assets');

            $this->publishes([
                __DIR__.'/../config/daisyblade.php' => config_path('daisyblade.php'),
            ], 'daisyblade-config');

            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeDaisyBladeComponent::class,
            ]);
        }
    }
}
```

---

## Contexto adicional

- El autor tiene 20 años de experiencia en Laravel y conoce el stack perfectamente. Respuestas escuetas, sin explicar lo obvio.
- El paquete se usará principalmente en FLOW-STUDIO (ERP modular declarativo), donde los módulos declaran intenciones UI (list, form, stats...) y el ThemeDriver las traduce a componentes. DaisyBlade será el ThemeDriver de referencia.
- El objetivo inmediato es tener Fase 1 + Fase 2 completas para poder usarlo en proyectos reales mientras se desarrollan las Fases 3-6.
- Repo de referencia para ver el HTML/DaisyUI de los componentes: https://github.com/edumicro/daisylw4 (público)
