<?php

/* pagination, tabs, navbar. Findings: ronda 2 PG, TB, NV · ronda 3 PG. */

it('pagination: pressing a page outside any x-data fires dbl-pagination-page-changed', function () {
    $page = visit(browserPage('pagination-event', <<<'BLADE'
        <div x-data @dbl-pagination-page-changed.window="window.pg = (window.pg || 0) + 1"></div>
        <x-dbl::navigation.pagination :meta="['current_page' => 2, 'last_page' => 5, 'total' => 50, 'per_page' => 10]" />
        BLADE));

    clickText($page, '3');

    expect(readPage($page, 'window.pg ?? 0'))->toBe(1);
});

it('pagination: on page 4 of 10 the first page is offered', function () {
    $page = visit(browserPage('pagination-first-page', <<<'BLADE'
        <x-dbl::navigation.pagination :meta="['current_page' => 4, 'last_page' => 10, 'total' => 100, 'per_page' => 10]" />
        BLADE));

    $labels = $page->script("[...document.querySelectorAll('.join button')].map(b => b.textContent.trim())");

    expect($labels)->toContain('1');
});

it('tabs: a tab without a label is called "Tab 1", not "Tab 0"', function () {
    $page = visit(browserPage('tabs-default-label', <<<'BLADE'
        <x-dbl::navigation.tabs :items="[['content' => 'a'], ['content' => 'b']]" />
        BLADE));

    $text = $page->script("document.body.innerText.replace(/\\s+/g, ' ')");

    expect($text)->not->toContain('Tab 0');
});

it('navbar: the mobile menu button has an accessible name', function () {
    $page = visit(browserPage('navbar-mobile-button', <<<'BLADE'
        <x-dbl::navigation.navbar brand="X"><x-slot:mobile>m</x-slot:mobile></x-dbl::navigation.navbar>
        BLADE));

    $name = $page->script("(() => { const b = document.querySelector('button.lg\\\\:hidden'); return (b.getAttribute('aria-label') || b.getAttribute('title') || b.textContent).trim() })()");

    expect($name)->not->toBe('');
});
