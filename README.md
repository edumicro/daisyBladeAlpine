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
- **Axios** handles explicit server calls
- **DaisyUI 5** provides the design system

Every server interaction is a plain Laravel controller returning JSON. No protocol overhead, no wire attributes, no object inspector surprises. If something breaks, you know exactly where to look.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| Alpine.js | ^3.0 |
| DaisyUI | ^5.0 |
| blade-heroicons | ^2.4 |

---

## Installation

```bash
composer require edumicro/daisyblade
php artisan daisyblade:install
```

The install command will ask whether to publish assets for **Vite** (recommended) or as a **public script tag**:

```bash
# Vite — assets published to resources/js/daisyblade.js
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

```html
<script src="/vendor/daisyblade/daisyblade.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
```

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
| `display.badge` | Status badges with color variants |
| `display.avatar` | User avatar with initials fallback |
| `display.stat` | KPI card with value, title, trend |
| `display.card` | Content card with optional shadow |
| `display.table` | Static HTML table from array data |
| `display.timeline` | Vertical event timeline |
| `display.kbd` | Keyboard shortcut display |
| `display.chat-bubble` | Chat message bubble |
| `display.diff` | Code diff viewer (line-by-line +/−/~) |
| `display.compare` | Side-by-side property comparison table |
| `display.tree` | Recursive tree from nested array |
| `display.list` | Styled list component |
| `display.mask` | DaisyUI mask shapes |
| `display.radial-progress` | Circular progress indicator |
| `display.status` | Status dot with label |
| `feedback.alert` | Alert message with type variants |
| `feedback.loading` | Loading spinner |
| `feedback.progress` | Linear progress bar |
| `feedback.skeleton` | Content skeleton placeholder |
| `feedback.tooltip` | Tooltip wrapper |
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
| `feedback.toast` | Toast notification |
| `navigation.tabs` | Tab switcher (inline content) |
| `navigation.navbar` | Top navigation bar |
| `navigation.sidebar` | Collapsible sidebar |
| `navigation.sidebar-tree` | Nested sidebar menu |
| `layout.app` | Full page layout with slots |
| `layout.auth` | Auth page layout |

### Type 3 — Interactive remote (Alpine + Axios)

Receive a `load-url` or `action` prop. Call plain Laravel controllers returning JSON.

| Component | Usage |
|---|---|
| `display.data-table` | Paginated, sortable, filterable table |
| `display.resource-details` | Detail view loaded from URL |
| `form.auto-form` | Declarative form from schema array |
| `form.select` | Select with remote search |
| `form.filter` | Filter bar for data-table |
| `import.spreadsheet` | Excel/CSV chunked import |
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

### Data table (Type 3)

```blade
{{-- In your Blade view --}}
<x-dbl::display.data-table
    :load-url="route('products.data')"
    :columns="$columns"
    :per-page="15"
    :filters-url="route('products.filters')"
/>
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
        ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc')
        ->paginate($request->per_page ?? 15);

    return response()->json($products);
}
```

### Declarative form (Type 3)

```blade
<x-dbl::form.auto-form
    :schema="[
        ['name' => 'name',        'label' => 'Product name', 'order' => 10],
        ['name' => 'category_id', 'label' => 'Category',     'type' => 'relation',
         'options-url' => route('categories.options'),        'order' => 20],
        ['name' => 'price',       'label' => 'Price',         'type' => 'money', 'order' => 30],
        ['name' => 'active',      'label' => 'Active',        'type' => 'toggle', 'order' => 40],
    ]"
    action="{{ route('products.store') }}"
    method="POST"
/>
```

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

    // DaisyBlade expects: {success, redirect} or {success: false, errors}
    return response()->json([
        'success'  => true,
        'redirect' => route('products.index'),
    ]);
}
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

// Validation failure
{ "success": false, "errors": { "name": ["The name field is required."] } }
```

Laravel's `response()->json()` + standard validation exceptions handle this automatically if you let them.

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
# or
vendor/bin/pest
```

188 tests, 294 assertions. All green.

---



## License

MIT — [Eduardo de Vicente](https://github.com/edumicro) / Microvalencia Soluciones Informáticas S.L.
