# DaisyBlade

**Pure Blade + Alpine.js + DaisyUI components for Laravel ERP/CRM interfaces.**

No Livewire. No magic. No hidden server roundtrips.

```bash
composer require edumicro/daisyblade
```

---

## Philosophy

DaisyBlade is the evolution of [edumicro/daisylw4](https://github.com/edumicro/daisylw4), rebuilt without Livewire. The stack is intentionally boring:

- **Blade** renders structure
- **Alpine.js** manages local UI state
- **Axios** (or plain `fetch`, if the page has no axios) handles explicit server calls
- **DaisyUI 5** provides the design system

Every server interaction is a plain Laravel controller returning JSON. No protocol overhead, no wire attributes, no object inspector surprises. If something breaks, you know exactly where to look.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |
| Alpine.js | ^3.0 |
| DaisyUI | ^5.0 |
| blade-heroicons | ^2.4 |

---

## Installation

```bash
composer require edumicro/daisyblade
php artisan daisyblade:install
```

The install command will ask whether to publish assets for **Vite** (recommended) or as a **public script tag**. Either way it copies two files side by side, `daisyblade.js` and `http.js` (the one function every component uses to call the server):

```bash
# Vite — assets published to resources/js/daisyblade.js and resources/js/http.js
php artisan daisyblade:install --vite

# Script tag — assets published to public/vendor/daisyblade/
php artisan daisyblade:install --public
```

### Vite setup

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [laravel({ input: ['resources/js/app.js'] })],
})
```

```js
// resources/js/app.js
import './daisyblade.js'
import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()
```

### Script tag setup

`daisyblade.js` is an **ES module** (it imports `./http.js` and exports its factories), so it needs `type="module"`. A plain `<script src>` fails with `Unexpected token 'export'`. A module script is deferred, like Alpine's `defer`, and both run in page order: keep DaisyBlade before Alpine.

```html
<script type="module" src="/vendor/daisyblade/daisyblade.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
```

The optional components (map, upload, rich text, signature, calendar, flowchart; see the last section) are ES modules too and are registered from your own module script.

### Tailwind and `[x-cloak]`

- **Tailwind has to scan the package views** to generate the classes the components use (every class name is written out in full inside the views). In `app.css`: `@source "../../vendor/edumicro/daisyblade/resources/views";`.
- **`[x-cloak]`**: the components hide things until Alpine starts with `x-cloak`, which needs `[x-cloak] { display: none !important; }`. `layout.app` and `layout.auth` already put that rule in the `<head>`. If you use your own layout, add it there.

---

## HTTP client

Every server call of every component (`daisyblade.js` and the optional `components/*.js`) goes through one function, `request()` in `http.js`. It picks the client in this order:

1. the client injected by the app: `registerCalendar(Alpine, { axios })`, `registerMap(Alpine, { maplibregl, axios })`, `registerUpload(Alpine, { axios })`, `registerRichText(Alpine, { …, axios })` and, for `daisyblade.js`, `registerDaisyBlade(Alpine, { axios })` (optional, before `Alpine.start()`);
2. `window.axios` (what Laravel's `bootstrap.js` sets up);
3. `fetch`.

A URL on **another domain** always goes through `fetch` and carries none of Laravel's headers (`X-Requested-With`, CSRF), because servers that are not yours usually reject them in the CORS preflight.

With `fetch` on the **same domain**, the fallback does what axios + Laravel do for you:

- sends `X-Requested-With` and `credentials: 'same-origin'`;
- on anything that is not a GET, sends the CSRF token, taken from `<meta name="csrf-token">` or else from the `XSRF-TOKEN` cookie;
- reads the JSON by itself;
- turns a 4xx or 5xx into an error shaped like axios' (`error.response.status`, `error.response.data`), so code that looks for a 422 or for `data.message` works with either client.

`fetch` cannot report upload progress. When a component needs it (`form.upload`, `import.spreadsheet`) and there is no axios, it uses `XMLHttpRequest` with the same headers and the same error shape. Uploads to another domain use `fetch` and show no progress.

---

## Component prefix

All components use the `dbl` prefix:

```blade
<x-dbl::display.badge label="Active" color="success" />
<x-dbl::form.input name="email" type="email" label="Email" />
<x-dbl::display.data-table :load-url="route('products.data')" :columns="$columns" />
```

---

## Component reference

### Type 1 — Static (Blade + DaisyUI only)

No JavaScript. Receive props, render HTML.

| Component | Usage |
|---|---|
| `display.accordion` | DaisyUI accordion (CSS-driven) |
| `display.avatar` | User avatar with initials fallback |
| `display.badge` | Status badges with color variants |
| `display.card` | Content card with optional shadow |
| `display.chat-bubble` | Chat message bubble |
| `display.collapse` | DaisyUI collapse panel (CSS-driven) |
| `display.compare` | Side-by-side property comparison table |
| `display.diff` | Code diff viewer (line-by-line +/−/~) |
| `display.hover-3d-card` | CSS 3D tilt card on hover |
| `display.hover-gallery` | CSS-animated image gallery |
| `display.kbd` | Keyboard shortcut display |
| `display.list` | Styled list component |
| `display.mask` | DaisyUI mask shapes |
| `display.radial-progress` | Circular progress indicator |
| `display.stat` | KPI card with value, title, trend |
| `display.status` | Status dot with label |
| `display.table` | Static HTML table from array data |
| `display.timeline` | Vertical event timeline |
| `display.tree` | Recursive tree from nested array |
| `feedback.alert` | Alert message with type variants |
| `feedback.loading` | Loading spinner |
| `feedback.progress` | Linear progress bar |
| `feedback.skeleton` | Content skeleton placeholder |
| `feedback.tooltip` | Tooltip wrapper |
| `form.checkbox` | Checkbox field with label |
| `form.input` | Text/email/number/date input |
| `form.radio` | Radio button group |
| `form.textarea` | Textarea with label |
| `form.toggle` | Toggle switch |
| `form.validator` | Inline validation message display |
| `layout.divider` | Section divider |
| `layout.footer` | Page footer |
| `layout.hero` | Hero section |
| `layout.indicator` | Badge indicator overlay |
| `layout.join` | DaisyUI join group |
| `layout.section-wrapper` | Padded section container |
| `layout.stack` | DaisyUI stack layout |
| `navigation.breadcrumb` | Breadcrumb trail |
| `navigation.dock` | Bottom dock navigation |
| `navigation.steps` | Step indicator |
| `actions.button` | Button with variants, loading, icon |
| `actions.fab` | Floating action button |
| `actions.swap` | Toggle swap element |
| `components.icon` | Heroicon wrapper |

### Type 2 — Interactive local (Alpine, no server calls)

UI state managed by Alpine. No Axios.

| Component | Usage |
|---|---|
| `actions.modal` | Modal with Alpine open/close |
| `display.carousel` | Image/content carousel |
| `display.filters` | Filter bar with Alpine state |
| `display.node-graph` | Tree graph with event timeline + detail modal |
| `display.text-rotate` | Animated rotating text |
| `feedback.toast` | Toast notification |
| `form.kv-editor` | Key-value editor with auto-inferring type selector |
| `form.list-editor` | Chip/tag array-of-strings editor |
| `form.repeater` | Dynamic repeatable field group |
| `navigation.menu` | Dropdown / nested menu |
| `navigation.navbar` | Top navigation bar |
| `navigation.pagination` | Page navigation controls |
| `navigation.sidebar` | Collapsible sidebar |
| `navigation.sidebar-tree` | Nested sidebar menu |
| `layout.app` | Full page layout with slots |
| `layout.auth` | Auth page layout |
| `navigation.tabs` | Tab switcher (inline content) |

### Type 3 — Interactive remote (Alpine + Axios)

Receive a `load-url` or `action` prop. Call plain Laravel controllers returning JSON.

| Component | Usage |
|---|---|
| `display.data-table` | Paginated, sortable, filterable table (listens to `display.filters` and to `form.filter`) |
| `display.resource-details` | Detail view loaded from `load-url` |
| `form.filter` | Filter bar for data-table |
| `form.select` | Select whose options come from `options-url`; `depends-on="other_field"` asks again when that field changes (`docs/components/select.md`) |
| `import.spreadsheet` | Excel/CSV chunked import |
| `sections.auto-form` | Declarative form from schema array |
| `sections.tabs` | Tabs with lazy-loaded content |
| `sections.wizard` | Multi-step form with localStorage resume |

---

## Usage examples

### Static badge

```blade
<x-dbl::display.badge label="Active" color="success" />
<x-dbl::display.badge label="Pending" color="warning" size="lg" />
<x-dbl::display.badge label="Error" color="error" :outline="true" />
```

### KPI stat card

```blade
<x-dbl::display.stat
    title="Monthly revenue"
    value="€ 12.400"
    description="vs last month"
    trend="up"
    icon="heroicon-o-banknotes"
/>
```

### Node graph (Type 2)

Renders a tree of simulation/workflow nodes with per-event color coding and a detail modal. Each node is an array with `id`, `parent` (null for roots), `label`, `status`, `events[]`, and optional `meta{}`.

```blade
@php
$nodes = [
    [
        'id'     => 1,
        'parent' => null,
        'label'  => 'Main trajectory',
        'status' => 'completed',
        'events' => [
            ['name' => 'timer_done', 'action_type' => 'terminate', 'termination_type' => 'end',   't_s' => 12.5],
            ['name' => 'heat_acc',   'action_type' => 'accumulator_abort', 'termination_type' => 'abort', 'acc_value' => 42.7],
        ],
        'meta' => ['h_max_m' => 350.2, 't_final_s' => 12.5],
    ],
    [
        'id'     => 2,
        'parent' => 1,
        'label'  => 'Branch — ricochet',
        'status' => 'failed',
        'events' => [
            ['name' => 'impact', 'action_type' => 'accumulator_fail', 'termination_type' => 'fail', 'acc_value' => 0.1],
        ],
        'meta' => [],
    ],
];
@endphp

<x-dbl::display.node-graph :nodes="$nodes" label="Execution tree" />
```

Event badge colours: `end` → green, `abort` → amber, `fail` → red, no termination → ghost. Accumulator events show `acc_value` inline. Click any node dot or event badge to open the detail modal.

### Data table (Type 3)

```blade
{{-- In your Blade view --}}
<x-dbl::display.data-table
    :load-url="route('products.data')"
    :columns="$columns"
    :per-page="15"
    :filters="[['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['active' => 'Active', 'draft' => 'Draft']]]"
/>
```

The table asks `load-url` with these query params: `page`, `per_page`, `search`, `sort` (the column key), `direction` (`asc` or `desc`) and one param per active filter. The `filters` prop shows the `display.filters` dropdown; a `form.filter` bar placed anywhere on the page filters the table too (the table listens to the events of both). If the request fails, the table shows a message with a **Retry** button instead of spinning for ever.

#### Toolbar links — export, print, anything

The `toolbar` prop adds header links that carry the table's **current** state (page, search,
sort, active filters) to a URL of your choosing:

```blade
<x-dbl::display.data-table
    :load-url="route('products.data')"
    :columns="$columns"
    :toolbar="[
        ['label' => 'Excel', 'url' => route('products.export'), 'icon' => 'heroicon-o-arrow-down-tray'],
        ['label' => 'CSV',   'url' => route('products.export.csv')],
    ]"
/>
```

The component is deliberately **format agnostic** — it knows nothing about spreadsheets, CSV or
PDF. It hands the state over and the URL decides what to build, so a second format is a second
array entry, not a change here. Each item takes `label`, `url`, an optional `icon` (a full
blade-heroicons component name) and an optional `download` (defaults to `true`; set it to
`false` for a link meant to open in the page, like a printable view).

Your endpoint receives the same query params as `load-url`, so build the query in **one** place
and let both the JSON endpoint and the export use it — otherwise the file people download stops
matching what they were looking at.

```php
public function export(Request $request)
{
    return Excel::download(new ProductsExport($request), 'products.xlsx');
}
```

```php
// In your controller
public function index()
{
    return view('products.index', [
        'columns' => [
            ['key' => 'name',     'label' => 'Name',     'sortable' => true],
            ['key' => 'category', 'label' => 'Category', 'sortable' => false],
            ['key' => 'price',    'label' => 'Price',    'sortable' => true],
        ],
    ]);
}

// Data endpoint — returns JSON
public function data(Request $request)
{
    $products = Product::query()
        ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
        ->orderBy($request->sort ?? 'name', $request->direction === 'desc' ? 'desc' : 'asc')
        ->paginate($request->per_page ?? 15);

    return response()->json($products);
}
```

### Dependent select (Type 3)

```blade
<x-dbl::form.select name="country_code" :options="$countries" />
<x-dbl::form.select name="region_code" options-url="/location/regions" depends-on="country_code" />
{{-- GET /location/regions?country_code=ES  ->  [{"value": "ES-TF", "label": "Santa Cruz de Tenerife"}, ...] --}}
```

### Declarative form (Type 3)

```blade
<x-dbl::sections.auto-form
    :fields="[
        'name'        => ['label' => 'Product name'],
        'category_id' => ['label' => 'Category', 'type' => 'relation', 'options' => $categories],
        'price'       => ['label' => 'Price',    'type' => 'money'],
        'active'      => ['label' => 'Active',   'type' => 'toggle'],
    ]"
    action="{{ route('products.store') }}"
    method="POST"
/>
```

`fields` is a map: the key is the field name, the value its definition (`type`, `label`, `options`, `cols`, `required`, …).

```php
// Controller — plain Laravel, no Livewire
public function store(Request $request)
{
    $validated = $request->validate([
        'name'        => 'required|string|max:255',
        'category_id' => 'required|exists:categories,id',
        'price'       => 'required|numeric|min:0',
        'active'      => 'boolean',
    ]);

    $product = Product::create($validated);

    // DaisyBlade reads `redirect` from the answer; a failed validation is a 422 with `errors`
    // (Laravel's `validate()` already does that).
    return response()->json([
        'success'  => true,
        'redirect' => route('products.index'),
    ]);
}
```

### Repeater field (Type 2)

Each field definition is keyed by `key` (not `name`):

```blade
<x-dbl::form.repeater
    name="installments"
    label="Installments"
    :fields="[
        ['key' => 'label',  'type' => 'text',    'label' => 'Label'],
        ['key' => 'amount', 'type' => 'decimal', 'label' => 'Amount'],
    ]"
    :value="old('installments', [])"
    add-label="Add installment"
    :min="1"
    :max="10"
/>
```

The repeater dispatches `dbl-repeater-change` (`{ name, rows }`) whenever the rows change, so a
parent can react without reaching into its Alpine scope:

```blade
<div x-data="{ save() { /* … */ } }" @dbl-repeater-change.debounce.500ms="save()">
    <x-dbl::form.repeater name="installments" :fields="$fields" />
</div>
```

#### `decimal` — the field type for localised numbers

`<input type="number">` is the wrong element wherever the decimal separator is not a dot. When the
browser cannot parse what was typed it sanitises `value` to the empty string, so `0,5` reaches the
server as nothing at all — and which browsers do this in which locale is inconsistent, which is
worse than failing outright.

`'type' => 'decimal'` renders `type="text" inputmode="decimal"`: the numeric keypad still comes up
on mobile, the text arrives intact, and the backend parses it in its own locale.

#### `default` — what a new row starts from

Field definitions take `default`, and it applies to the first row *and* to every row `add()`
creates. A default that only reaches the first row is a default the user stops seeing the moment
they add a second one:

```php
['key' => 'unit', 'type' => 'select', 'options' => $units, 'default' => 'mg/h']
```

#### `attrs` — raw HTML attributes

Schema-driven components take arrays, so there is no attribute bag to merge into. `attrs` is the
array equivalent, available on `form.repeater` fields and `form.fields`:

```php
['key' => 'ref', 'attrs' => ['autocomplete' => 'off', 'maxlength' => 12, 'aria-label' => 'Reference']]
```

Values are escaped. Attribute names are validated, and `on*` handlers are refused — event handlers
go through `events`, which is explicit about executing code.

#### `events` — Alpine bindings, per target

`events` maps a target element to Alpine bindings, merged over the component's own defaults. The
path is the binding spec and the leaf is the expression:

```blade
<x-dbl::form.repeater
    name="drugs"
    :fields="$fields"
    :events="[
        'root' => ['keydown' => ['enter' => ['prevent' => 'add()']]],
    ]"
/>
```

Targets are `root`, `row`, `add` and `remove`. In scope: `rows`, `add()`, `remove(index)`,
`notify()` — plus `index` inside a row.

Dotted keys mean the same thing, so a binding can be pasted straight out of the Alpine docs:

```php
['root' => ['keydown.enter.prevent' => 'add()']]
```

The nesting is what makes overriding work. A leaf replaces the branch below it, so passing
`['keydown' => ['enter' => 'mine()']]` over a default of `keydown.enter.prevent` leaves **one**
binding — yours. With flat keys the two would be different keys, both would survive, and both would
fire. Declaring the same binding twice, or a handler and modifiers on the same node, throws.

To extend a default rather than replace it, call it: `['add' => ['click' => 'add(); mine()']]`.

> `events` leaves are executable by design. They are for expressions written by the developer, never
> for values arriving from outside the application.

### Key-value editor (Type 2)

Renders a table of key/value rows with an auto-inferring type selector (num/str/bool/{…}). Serialises to a hidden `<input>` as JSON.

```blade
<x-dbl::form.kv-editor
    name="parameters"
    :value="$model->parameters ?? []"
    label="Parámetros"
    hint="Campos habituales: mass_kg, v0_ms, diameter_m"
/>
```

`value` accepts a PHP associative array. Types are inferred automatically when the user types; the selector lets them override if needed.

### List editor (Type 2)

Chip/tag input for an array of strings. Enter or comma adds a chip; Backspace removes the last one.

```blade
<x-dbl::form.list-editor
    name="output_fields"
    :value="$model->output_fields ?? []"
    label="Campos de output"
    hint="Disponibles: t_s, x_m, h_m, v_ms, mach"
    placeholder="Escribe un campo y pulsa Enter…"
/>
```

### Multi-step wizard with localStorage resume

```blade
<x-dbl::sections.wizard
    form-id="product-onboarding"
    schema-version="2"
    :user-id="auth()->id()"
    action="{{ route('products.store') }}"
    :steps="[
        ['title' => 'Basic info',  'fields' => ['name', 'category_id']],
        ['title' => 'Pricing',     'fields' => ['price', 'currency']],
        ['title' => 'Visibility',  'fields' => ['active', 'publish_at']],
    ]"
/>
```

If the user refreshes mid-wizard, their progress is automatically restored from localStorage. The storage key is versioned (`product-onboarding_{userId}_v2`), so changing `schema-version` invalidates stale state.

### App layout

```blade
{{-- resources/views/products/index.blade.php --}}
<x-dbl::layout.app title="Products">

    <x-slot:navbar>
        <x-dbl::navigation.navbar>
            <x-dbl::navigation.breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Products'],
            ]"/>
        </x-dbl::navigation.navbar>
    </x-slot:navbar>

    <x-slot:sidebar>
        <x-dbl::navigation.sidebar />
    </x-slot:sidebar>

    <x-dbl::display.data-table
        :load-url="route('products.data')"
        :columns="$columns"
    />

</x-dbl::layout.app>
```

---

## JSON response contract

All Type 3 components expect controllers to return JSON following this contract:

```json
// Success with redirect
{ "success": true, "redirect": "/products" }

// Success with data (for remote selects, resource-details, etc.)
{ "success": true, "data": [...], "meta": { "current_page": 1, "last_page": 5 } }

// Validation failure: HTTP status 422
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

The components look at the HTTP status (a 422 puts `errors` next to each field) and at `message` for any other failure. Laravel's `response()->json()` + standard validation exceptions give exactly that if you let them.

---

## Publishing views

To customise any component, publish the views:

```bash
php artisan vendor:publish --tag=daisyblade-views
```

Published views in `resources/views/vendor/daisyblade/` take precedence over package views. Edit freely — your customisations survive package updates.

---

## Testing

```bash
composer test
# or, one at a time
vendor/bin/pest
node --test tests/js/*.test.mjs
```

`composer test` runs both: the Pest tests (components, translations, install command) and the `node --test` tests of the JavaScript (HTTP client, error handling, dates, map, flowchart). Currently 367 Pest tests (1482 assertions) and 130 JavaScript tests, all green.

---

## License

MIT — [Eduardo de Vicente](https://github.com/edumicro) / Microvalencia Soluciones Informáticas S.L.


---

## Componentes opcionales

Tienen dependencias propias, así que no se cargan con `daisyblade.js`. Se registran uno a uno pasando sus librerías, **antes de `Alpine.start()`** (o dentro de un `alpine:init`, que se dispara justo al empezar):

```js
import maplibregl from 'maplibre-gl'
import { registerMap } from '../../vendor/edumicro/daisyblade/resources/js/components/map.js'

registerMap(Alpine, { maplibregl, axios })   // axios es opcional: ver «HTTP client»
```

Son módulos ES: se cargan con `import` desde tu propio JS (Vite) o con `<script type="module">`, nunca con un `<script>` suelto.

| Componente | Registro | Dependencias npm | Doc |
|---|---|---|---|
| `<x-dbl::display.map>` | `registerMap` | `maplibre-gl` (o Google: `@googlemaps/js-api-loader`, `@googlemaps/markerclusterer`) | `docs/components/map.md` |
| `<x-dbl::form.upload>` | `registerUpload` | — (axios opcional) | `docs/components/upload.md` |
| `<x-dbl::form.rich-text>` | `registerRichText` | `@tiptap/core`, `@tiptap/pm`, `@tiptap/starter-kit` | `docs/components/rich-text.md` |
| `<x-dbl::form.signature>` | `registerSignature` | `signature_pad` | `docs/components/signature.md` |
| `<x-dbl::display.calendar>` | `registerCalendar` | — | `docs/components/calendar.md` |
| `<x-dbl::display.flowchart>` | `registerFlowchart` | — | `docs/components/flowchart.md` |

`display.flowchart` es un diagrama libre de cajas y flechas con arrastrar y soltar; no llama al servidor, emite eventos y el padre decide.
