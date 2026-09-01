<?php

use Illuminate\Support\Facades\Blade;

// ── navigation/breadcrumb ─────────────────────────────────────────────────────

it('breadcrumb renders items with url as anchor', function () {
    $html = Blade::render(
        '<x-dbl::navigation.breadcrumb :items="$items" />',
        ['items' => [['label' => 'Inicio', 'url' => '/'], ['label' => 'Productos', 'url' => '/products']]]
    );
    expect($html)->toContain('Inicio')->toContain('Productos')->toContain('<a');
});

it('breadcrumb last item has no anchor', function () {
    $html = Blade::render(
        '<x-dbl::navigation.breadcrumb :items="$items" />',
        ['items' => [['label' => 'Inicio', 'url' => '/'], ['label' => 'Actual']]]
    );
    // "Actual" is the last item, should be a span not a link
    expect($html)->toContain('Actual');
    // The last item should not wrap its label in an <a>
    preg_match_all('/<a[^>]*>.*?Actual.*?<\/a>/s', $html, $matches);
    expect($matches[0])->toBeEmpty();
});

it('breadcrumb renders empty without exception', function () {
    $html = Blade::render('<x-dbl::navigation.breadcrumb :items="[]" />');
    expect($html)->not->toBeEmpty();
});

it('breadcrumb without wire: or livewire', function () {
    $html = Blade::render(
        '<x-dbl::navigation.breadcrumb :items="$items" />',
        ['items' => [['label' => 'Home', 'url' => '/']]]
    );
    expect($html)->not->toContain('wire:')->not->toContain('livewire');
});

// ── navigation/pagination ─────────────────────────────────────────────────────

it('pagination with meta and base-url renders page links', function () {
    $html = Blade::render(
        '<x-dbl::navigation.pagination :meta="$meta" base-url="/products" />',
        ['meta' => ['current_page' => 2, 'last_page' => 5, 'from' => 16, 'to' => 30, 'total' => 75]]
    );
    expect($html)->toContain('page=')->toContain('/products');
});

it('pagination shows total record info', function () {
    $html = Blade::render(
        '<x-dbl::navigation.pagination :meta="$meta" base-url="/x" />',
        ['meta' => ['current_page' => 1, 'last_page' => 3, 'from' => 1, 'to' => 15, 'total' => 45]]
    );
    expect($html)->toContain('45');
});

it('pagination single page has no link to page 2', function () {
    $html = Blade::render(
        '<x-dbl::navigation.pagination :meta="$meta" base-url="/x" />',
        ['meta' => ['current_page' => 1, 'last_page' => 1, 'from' => 1, 'to' => 5, 'total' => 5]]
    );
    expect($html)->not->toContain('page=2');
});

it('pagination without base-url dispatches page-changed event', function () {
    $html = Blade::render(
        '<x-dbl::navigation.pagination :meta="$meta" />',
        ['meta' => ['current_page' => 1, 'last_page' => 3, 'from' => 1, 'to' => 15, 'total' => 45]]
    );
    expect($html)->toContain('page-changed');
});

it('pagination has no wire: attributes', function () {
    $html = Blade::render(
        '<x-dbl::navigation.pagination :meta="$meta" />',
        ['meta' => ['current_page' => 1, 'last_page' => 2, 'from' => 1, 'to' => 15, 'total' => 20]]
    );
    expect($html)->not->toContain('wire:');
});

// ── navigation/tabs ───────────────────────────────────────────────────────────

it('tabs renders item labels', function () {
    $html = Blade::render(
        '<x-dbl::navigation.tabs :items="$items" />',
        ['items' => [['label' => 'General', 'content' => 'Content A'], ['label' => 'Avanzado', 'content' => 'Content B']]]
    );
    expect($html)->toContain('General')->toContain('Avanzado');
});

it('tabs renders item content', function () {
    $html = Blade::render(
        '<x-dbl::navigation.tabs :items="$items" />',
        ['items' => [['label' => 'Tab 1', 'content' => '<p>Mi contenido</p>']]]
    );
    expect($html)->toContain('Mi contenido');
});

it('tabs has x-data for Alpine', function () {
    $html = Blade::render(
        '<x-dbl::navigation.tabs :items="$items" />',
        ['items' => [['label' => 'A', 'content' => 'X']]]
    );
    expect($html)->toContain('x-data');
});

it('tabs with active=1 marks second tab active', function () {
    $html = Blade::render(
        '<x-dbl::navigation.tabs :items="$items" :active="1" />',
        ['items' => [['label' => 'Uno', 'content' => 'A'], ['label' => 'Dos', 'content' => 'B']]]
    );
    expect($html)->toContain('active === 1');
});

it('tabs has no wire: attributes', function () {
    $html = Blade::render(
        '<x-dbl::navigation.tabs :items="$items" />',
        ['items' => [['label' => 'Tab', 'content' => 'X']]]
    );
    expect($html)->not->toContain('wire:');
});

// ── navigation/sidebar: fuera de pantalla, no un raíl que recorta ──────────────

it('sidebar slides off-canvas instead of collapsing to a clipped rail', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar>menu</x-dbl::navigation.sidebar>');

    expect($html)
        ->toContain('-translate-x-full')
        ->not->toContain("'w-16'");
});

it('sidebar keeps a way back in once it is closed', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar>menu</x-dbl::navigation.sidebar>');

    // The floating toggle must not depend on the host defining [x-cloak], or it flashes on load.
    expect($html)
        ->toContain('x-show="!open"')
        ->toContain('style="display: none"');
});

it('sidebar with collapsible=false has no toggles at all', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar :collapsible="false">menu</x-dbl::navigation.sidebar>');

    expect($html)->not->toContain('toggle()');
});

// ── sidebar: la forma de recuperarla ─────────────────────────────────────────

it('sidebar can be reopened from anywhere via a window event', function () {
    // La barra y la navbar estan en slots distintos del layout: no comparten ambito Alpine, asi
    // que sin el escuchador global la unica forma de recuperarla es su propio boton de esquina.
    $html = Blade::render('<x-dbl::navigation.sidebar>menu</x-dbl::navigation.sidebar>');

    expect($html)->toContain('sidebar-toggle.window');
});

it('sidebar can drop the floating toggle for hosts that provide their own', function () {
    // Cerrar arriba y reabrir abajo hace saltar el control de punta a punta de la pantalla: quien
    // pone su propio boton en la navbar quiere UNO, y donde se mira.
    $html = Blade::render('<x-dbl::navigation.sidebar :floating-toggle="false">menu</x-dbl::navigation.sidebar>');

    expect($html)
        ->not->toContain('bottom-4')
        ->toContain('sidebar-toggle.window');   // sigue siendo recuperable desde fuera
});
