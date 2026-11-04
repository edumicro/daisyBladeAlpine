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
- **The HTTP client** (axios, or plain `fetch` if the page has none) handles explicit server calls
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

The install command will ask whether to publish assets for **Vite** (recommended) or as a **public script tag**. Either way it copies, side by side, `daisyblade.js`, `http.js` (the one function every component uses to call the server) and the `components/` folder (the optional components: map, upload, rich text, signature, calendar, flowchart):

```bash
# Vite — assets published to resources/js/daisyblade.js, resources/js/http.js and resources/js/components/
php artisan daisyblade:install --vite

# Script tag — assets published to public/vendor/daisyblade/ (daisyblade.js, http.js, components/)
php artisan daisyblade:install --public
```

The JS files go into folders where your own files may live (`resources/js/components/calendar.js` can be yours). So a file that already exists and is different is **not overwritten**: the command lists what it left alone. After updating the package, run it again with `--force` to replace them with the new copy:

```bash
php artisan daisyblade:install --vite --force
```

The views are published to `resources/views/vendor/daisyblade/` every time (that folder is the package's own).

The texts of the components (Spanish, Catalan and English) can be published too, and edited there:

```bash
php artisan vendor:publish --tag=daisyblade-lang     # lang/vendor/daisyblade/
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

Everything the package names uses the `dbl` prefix: the Blade tags (`x-dbl::`), the events (`dbl-select-changed`), the Alpine factories (`dblSelectRemote`), its own CSS classes (`dbl-flowchart`) and the localStorage keys (`dbl_sidebar`).

```blade
<x-dbl::display.badge label="Active" color="success" />
<x-dbl::form.input name="email" type="email" label="Email" />
<x-dbl::display.data-table :load-url="route('products.data')" :columns="$columns" />
```

---

## One convention for every component

Learn it once, it is the same everywhere. The migration guide from 2.x is in `CHANGELOG.md`, section `[3.0.0]`.

| What | Rule |
|---|---|
| **URLs** | In PHP camelCase, in the tag kebab-case; the keys of the arrays you pass are snake_case. A component that exists for its URL (`auto-form` in `alpine` mode, `wizard`, `data-table`, `resource-details`, `calendar`, `upload`, `spreadsheet`) throws an `InvalidArgumentException` when it is empty, which is what a 2.x name (`action=`) now looks like. `load-url`: where the component reads its data (calendar, data-table, resource-details, remote select, lazy tabs with `load_url`, map layers with `load_url`). `submit-url`: where it sends what the user filled in (auto-form, wizard). `upload-url`: where the files go (upload, rich-text, spreadsheet). `geocode-url`: the address search of the map. |
| **Value** | `value` is one value, in every form control that starts with one: `input`, `textarea`, `select`, `checkbox`, `radio`, `toggle` (checked when it is true), `rich-text`, `kv-editor`, `list-editor` and `repeater`. `upload` starts from `existing` (a list of files) and `signature` starts empty. `values` is a `name => value` map, only in `auto-form`, `fields` and the steps of `wizard`. |
| **Help** | `hint`, in every form control. There is no `description` on a control. |
| **Size** | `size` is a token: `xs\|sm\|md\|lg\|xl` (and `full` in `modal`, `2xl`..`4xl` in `text-rotate`, `2xl` in `avatar`). The component turns it into its class with a literal map. Controls (`input`, `textarea`, `select`, `checkbox`, `radio`, `toggle`) have it. |
| **Color** | `color` is a token: `primary\|secondary\|accent\|neutral\|info\|success\|warning\|error`, plus `ghost\|link` on buttons. `background` is the only name for a background: `base-100\|base-200\|base-300` or one of the colors (card, stat, footer, collapse). Anything else goes in `class`. |
| **`class` and attributes** | Everything you write (`class`, `id`, `data-*`, `aria-*`, `x-on:*`) goes to the **main element** of the component. That is its root, as in any Laravel component; in a **form control** it is the control itself, as in Breeze's text-input: `<x-dbl::form.input type="number" min="0" x-model="qty">` puts `min` and `x-model` in the `<input>`. The box around a control (label, hint, error) takes its classes from `wrapper-class`. `id` is the exception in a **radio** group (it goes to the first radio only: the same `id` on every radio would be invalid HTML). In `rich-text`, `signature` and `upload` the control is a box, not a native element, so the label does not point at it with `for` (the `rich-text` label focuses the editor when pressed). To style another inner part there is a `<part>-class` prop: `canvas-class`, `table-class`... There is no `container-class`. |
| **Events** | What a component announces: `dbl-<component>-<what happened, in the past>` (`dbl-select-changed`, `dbl-pagination-page-changed`, `dbl-upload-removed`). The orders it listens to on the window: `dbl-<component>-<verb>` (`dbl-modal-open`, `dbl-modal-close`, `dbl-toast-show`, `dbl-sidebar-toggle`, `dbl-map-focus`, `dbl-flowchart-focus`). Always `$dispatch`, and the `detail` is always an object. |
| **Filters** | One component, `x-dbl::form.filters`, announcing `dbl-filters-applied` and `dbl-filters-cleared` with `{ filters }`. `display.data-table` listens to those two. |
| **Configuration for the JavaScript** | One way: `x-data="dblName(@js($config))"`. Nothing from PHP is glued inside a JavaScript text. |

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
| `actions.button` | Button with colors, loading, icon |
| `actions.fab` | Floating action button |
| `actions.swap` | Toggle swap element |
| `components.icon` | Heroicon wrapper |

### Type 2 — Interactive local (Alpine, no server calls)

UI state managed by Alpine. No server calls.

| Component | Usage |
|---|---|
| `actions.modal` | Modal with Alpine open/close |
| `display.carousel` | Image/content carousel |
| `display.node-graph` | Tree graph with event timeline + detail modal |
| `display.text-rotate` | Animated rotating text |
| `feedback.toast` | Toast notification |
| `form.filters` | Filter panel (dropdown): announces `dbl-filters-applied` / `dbl-filters-cleared` |
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

### Type 3 — Interactive remote (Alpine + server calls)

Receive a `load-url`, `submit-url` or `upload-url` prop. Call plain Laravel controllers returning JSON.

| Component | Usage |
|---|---|
| `display.data-table` | Paginated, sortable, filterable table (listens to `form.filters`) |
| `display.resource-details` | Detail view loaded from `load-url` |
| `form.select` | Select whose options come from `load-url`; `depends-on="other_field"` asks again when that field changes (`docs/components/select.md`) |
| `import.spreadsheet` | Excel/CSV import: choose the file, confirm (name and size; the browser does not read the rows), progress. The server reads the file and answers `{ imported }` |
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

The table asks `load-url` with these query params: `page`, `per_page`, `search`, `sort` (the column key), `direction` (`asc` or `desc`) and one param per active filter. The `filters` prop shows the `form.filters` dropdown; a `<x-dbl::form.filters>` placed anywhere on the page filters the table too (the table listens to its `dbl-filters-applied` and `dbl-filters-cleared` events). If the request fails, the table shows a message with a **Retry** button instead of spinning for ever.

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
<x-dbl::form.select name="region_code" load-url="/location/regions" depends-on="country_code" />
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
    submit-url="{{ route('products.store') }}"
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

The repeater dispatches `dbl-repeater-changed` (`{ name, rows }`) whenever the rows change, so a
parent can react without reaching into its Alpine scope:

```blade
<div x-data="{ save() { /* … */ } }" @dbl-repeater-changed.debounce.500ms="save()">
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
    submit-url="{{ route('products.store') }}"
    :steps="[
        ['label' => 'Basic info', 'schema' => ['name' => ['label' => 'Name'], 'category_id' => ['label' => 'Category']], 'values' => ['name' => 'New product']],
        ['label' => 'Pricing',    'schema' => ['price' => ['label' => 'Price', 'type' => 'money']]],
    ]"
/>
```

If the user refreshes mid-wizard, their progress is automatically restored from localStorage. The storage key is `dbl_wizard_{userId}_{formId}_v{schemaVersion}`, so changing `schema-version` invalidates stale state. The `values` of the steps are where the form starts; the saved progress goes on top of them (what the user typed is not overwritten).

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

## Events

Announced by the components (they bubble up from the component, so `x-on:` on any ancestor hears them; `detail` is an object). What already happened ends in the past (`-changed`, `-clicked`, `-removed`); when the parent decides whether something happens, the name says so: `-requested`.


| Component | Event | `detail` |
|---|---|---|
| `form.select` (all three modes) | `dbl-select-changed` | `{ name, value }` (`value` is a list with `multiple`) |
| `form.filters` | `dbl-filters-applied`, `dbl-filters-cleared` | `{ filters }` |
| `form.repeater` | `dbl-repeater-changed` | `{ name, rows }` |
| `form.rich-text` | `dbl-rich-text-changed` | `{ name, value }` (the HTML) |
| `form.signature` | `dbl-signature-signed`, `dbl-signature-cleared` | `{ name, value }` |
| `form.upload` | `dbl-upload-uploaded`, `dbl-upload-removed` | `{ id, name, url, file }`, `{ id, name }` |
| `navigation.pagination` | `dbl-pagination-page-changed` | `{ page }` |
| `navigation.steps` | `dbl-steps-step-changed` | `{ step }` |
| `display.calendar` | `dbl-calendar-event-clicked` | the event |
| `display.map` | `dbl-map-picked` | `{ lat, lng }` |
| `display.flowchart` | `dbl-flowchart-node-clicked`, `-edge-clicked`, and the two that ask the parent: `-node-move-requested`, `-edge-requested` | see `docs/components/flowchart.md` |
| `display.node-graph` | `dbl-node-graph-node-clicked`, `-badge-clicked`, and the ones that ask the parent: `-node-create-requested`, `-node-delete-requested`, `-badge-create-requested`, `-badge-delete-requested`, `-edge-requested` | the node and badge ids |

Orders the components listen to on the window (`window.dispatchEvent(new CustomEvent('dbl-modal-open', { detail: { id: 'help' } }))`, or `$dispatch` from any element):

| Order | `detail` | Effect |
|---|---|---|
| `dbl-modal-open`, `dbl-modal-close` | `{ id }` | Opens / closes the `actions.modal` with that `id` |
| `dbl-toast-show` | `{ message, type?, duration? }` | `feedback.toast` shows a toast |
| `dbl-sidebar-toggle` | | `navigation.sidebar` opens / closes |
| `dbl-map-focus` | `{ map, layer, id }` | `display.map` centers on a point |
| `dbl-flowchart-set`, `-focus`, `-remove-edge`, `-remove-node` | `{ name, ... }` | `display.flowchart` with that `name` |

The `action` of a `navigation.menu` or `navigation.dock` item is the name of the event it dispatches: the name is yours (`'action' => 'dbl-sidebar-toggle'`).

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

`composer test` runs both: the Pest tests (components, translations, install command) and the `node --test` tests of the JavaScript (HTTP client, error handling, dates, map, flowchart). Currently 539 Pest tests (2221 assertions) and 224 JavaScript tests, all green. `tests/Feature/ConventionsTest.php` and `tests/js/conventions.test.mjs` hold one test per convention of the table above, so the package cannot slide back to a 2.x name.

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
