<?php

use Illuminate\Support\Facades\Blade;

// ── Reglas generales: ningún componente debe emitir wire: ni livewire ─────────

$displayComponents = [
    '<x-dbl::display.badge label="X" />',
    '<x-dbl::display.avatar />',
    '<x-dbl::display.stat title="T" value="V" />',
    '<x-dbl::display.card title="T" />',
    '<x-dbl::display.table :rows="[]" :columns="[]" />',
    '<x-dbl::display.timeline :items="[]" />',
    '<x-dbl::display.accordion :items="[]" />',
    '<x-dbl::display.carousel :images="[]" />',
    '<x-dbl::display.chat-bubble message="Hi" />',
    '<x-dbl::display.collapse title="T" />',
    '<x-dbl::display.diff :changes="[]" />',
    '<x-dbl::display.hover-gallery :images="[]" />',
    '<x-dbl::display.kbd keys="Ctrl" />',
    '<x-dbl::display.list :items="[]" />',
    '<x-dbl::display.mask src="/x.jpg" />',
    '<x-dbl::display.radial-progress :value="50" />',
    '<x-dbl::display.status />',
    '<x-dbl::display.text-rotate :texts="[]" />',
];

dataset('display_components', $displayComponents);

it('renders display component without wire: attributes', function (string $template) {
    $html = Blade::render($template);
    expect($html)
        ->not->toContain('wire:')
        ->not->toContain('livewire')
        ->not->toContain('Livewire');
})->with('display_components');

// ── display/badge ─────────────────────────────────────────────────────────────

it('badge shows label', function () {
    $html = Blade::render('<x-dbl::display.badge label="Activo" />');
    expect($html)->toContain('Activo');
});

it('badge with color=success contains badge-success', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" color="success" />');
    expect($html)->toContain('badge-success');
});

it('badge with color=error contains badge-error', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" color="error" />');
    expect($html)->toContain('badge-error');
});

it('badge with color=warning contains badge-warning', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" color="warning" />');
    expect($html)->toContain('badge-warning');
});

it('badge renders without color prop', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" />');
    expect($html)->toContain('badge');
});

it('badge with size=lg contains badge-lg', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" size="lg" />');
    expect($html)->toContain('badge-lg');
});

it('badge with outline=true contains badge-outline', function () {
    $html = Blade::render('<x-dbl::display.badge label="X" :outline="true" />');
    expect($html)->toContain('badge-outline');
});

// ── display/avatar ────────────────────────────────────────────────────────────

it('avatar with src renders img tag', function () {
    $html = Blade::render('<x-dbl::display.avatar src="https://example.com/img.jpg" alt="Usuario" />');
    expect($html)->toContain('<img')->toContain('https://example.com/img.jpg');
});

it('avatar with initials shows initials and no img', function () {
    $html = Blade::render('<x-dbl::display.avatar initials="EV" />');
    expect($html)->toContain('EV')->not->toContain('<img');
});

it('avatar with size=lg contains size class', function () {
    $html = Blade::render('<x-dbl::display.avatar size="lg" />');
    expect($html)->toContain('w-16');
});

it('avatar with online=true contains online class', function () {
    $html = Blade::render('<x-dbl::display.avatar :online="true" />');
    expect($html)->toContain('online');
});

it('avatar with offline=true contains offline class', function () {
    $html = Blade::render('<x-dbl::display.avatar :offline="true" />');
    expect($html)->toContain('offline');
});

// ── display/stat ──────────────────────────────────────────────────────────────

it('stat shows title and value', function () {
    $html = Blade::render('<x-dbl::display.stat title="Ventas" value="1.240 €" />');
    expect($html)->toContain('Ventas')->toContain('1.240 €');
});

it('stat shows description', function () {
    $html = Blade::render('<x-dbl::display.stat title="X" value="Y" description="Mes actual" />');
    expect($html)->toContain('Mes actual');
});

it('stat with icon renders heroicon reference', function () {
    $html = Blade::render('<x-dbl::display.stat title="X" value="Y" icon="heroicon-o-chart-bar" />');
    expect($html)->toMatch('/<svg|heroicon|chart-bar/i');
});

it('stat with trend=up contains positive trend element', function () {
    $html = Blade::render('<x-dbl::display.stat title="X" value="Y" trend="up" />');
    expect($html)->toMatch('/trend|up|increase|success|arrow/i');
});

it('stat with trend=down contains negative trend element', function () {
    $html = Blade::render('<x-dbl::display.stat title="X" value="Y" trend="down" />');
    expect($html)->toMatch('/trend|down|decrease|error|arrow/i');
});

// ── display/card ──────────────────────────────────────────────────────────────

it('card shows title', function () {
    $html = Blade::render('<x-dbl::display.card title="Mi Tarjeta" />');
    expect($html)->toContain('Mi Tarjeta')->toContain('card');
});

it('card renders slot content', function () {
    $html = Blade::render('<x-dbl::display.card title="T">Contenido interior</x-dbl::display.card>');
    expect($html)->toContain('Contenido interior');
});

it('card with shadow=false has no shadow class', function () {
    $html = Blade::render('<x-dbl::display.card title="T" :shadow="false" />');
    expect($html)->not->toContain('shadow');
});

it('card with compact=true contains card-compact', function () {
    $html = Blade::render('<x-dbl::display.card title="T" :compact="true" />');
    expect($html)->toContain('card-compact');
});

// ── display/table ─────────────────────────────────────────────────────────────

it('table renders rows and columns', function () {
    $html = Blade::render(
        '<x-dbl::display.table :rows="$rows" :columns="$cols" />',
        ['rows' => [['id' => 1, 'name' => 'Ana'], ['id' => 2, 'name' => 'Luis']], 'cols' => [['key' => 'id', 'label' => 'ID'], ['key' => 'name', 'label' => 'Nombre']]]
    );
    expect($html)
        ->toContain('Ana')->toContain('Luis')
        ->toContain('ID')->toContain('Nombre')
        ->toContain('table');
});

it('table renders empty without exception', function () {
    $html = Blade::render('<x-dbl::display.table :rows="[]" :columns="$cols" />', ['cols' => [['key' => 'id', 'label' => 'ID']]]);
    expect($html)->toContain('table');
});

it('table with striped=true contains table-zebra', function () {
    $html = Blade::render('<x-dbl::display.table :rows="[]" :columns="[]" :striped="true" />');
    expect($html)->toContain('table-zebra');
});

// ── display/data-table ────────────────────────────────────────────────────────

it('data-table contains dbDataTable and load-url', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/api/products" :columns="[]" />');
    expect($html)
        ->toContain('dbDataTable')
        ->toContain('/api/products')
        ->toContain('x-data')
        ->not->toContain('wire:');
});

it('data-table renders column header', function () {
    $html = Blade::render(
        '<x-dbl::display.data-table load-url="/api/x" :columns="$cols" />',
        ['cols' => [['key' => 'name', 'label' => 'Nombre']]]
    );
    expect($html)->toContain('Nombre');
});

it('data-table serializes per-page in x-data', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/api/x" :columns="[]" :per-page="25" />');
    expect($html)->toContain('25');
});

it('data-table has skeleton loading element', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/api/x" :columns="[]" />');
    expect($html)->toContain('skeleton');
});

// ── display/alert ─────────────────────────────────────────────────────────────

it('alert shows message with success type', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="Operación correcta" type="success" />');
    expect($html)->toContain('Operación correcta')->toContain('alert-success');
});

it('alert with type=error contains alert-error', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="Error" type="error" />');
    expect($html)->toContain('alert-error');
});

it('alert with type=warning contains alert-warning', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="Aviso" type="warning" />');
    expect($html)->toContain('alert-warning');
});

it('alert with type=info contains alert-info', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="Info" type="info" />');
    expect($html)->toContain('alert-info');
});

it('alert with closable=true has close button and x-data', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="X" :closable="true" />');
    expect($html)->toContain('x-data')->toContain('button');
});

// ── display/timeline ──────────────────────────────────────────────────────────

it('timeline shows item titles and dates', function () {
    $html = Blade::render(
        '<x-dbl::display.timeline :items="$items" />',
        ['items' => [['title' => 'Creado', 'date' => '2024-01-01'], ['title' => 'Enviado', 'date' => '2024-01-02']]]
    );
    expect($html)->toContain('Creado')->toContain('Enviado')->toContain('2024-01-01');
});

it('timeline renders empty without exception', function () {
    $html = Blade::render('<x-dbl::display.timeline :items="[]" />');
    expect($html)->not->toBeEmpty();
});

// ── display/resource-details ──────────────────────────────────────────────────

it('resource-details contains Alpine factory and load-url', function () {
    $html = Blade::render('<x-dbl::display.resource-details load-url="/api/products/1" />');
    expect($html)
        ->toContain('dbResourceDetails')
        ->toContain('/api/products/1')
        ->toContain('x-data')
        ->not->toContain('wire:');
});
