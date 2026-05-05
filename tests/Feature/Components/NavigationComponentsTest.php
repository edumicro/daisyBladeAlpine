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
