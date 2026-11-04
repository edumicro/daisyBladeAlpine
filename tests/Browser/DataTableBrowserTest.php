<?php

/* display.data-table with filters. Findings: ronda 1 DT2 · ronda 2 DT3. */

it('data-table: the filter of table A reloads A and not B', function () {
    $page = visit(browserPage('data-table-filter-scope', <<<'BLADE'
        <x-dbl::display.data-table id="A" load-url="/rowsA" :columns="[['key' => 'id']]" :filters="[['key' => 'st', 'type' => 'text']]" />
        <x-dbl::display.data-table id="B" load-url="/rowsB" :columns="[['key' => 'id']]" :filters="[['key' => 'zz', 'type' => 'text']]" />
        BLADE));
    $page->script('window.__net.log.length = 0');

    $page->script("document.querySelector('#A .dropdown label').click()");
    setField($page, '#A .dropdown input[type=text]', 'x');
    pause($page, 0.9);

    $log = $page->script('window.__net.log');
    $forA = array_filter($log, fn ($u) => str_contains($u, '/rowsA') && str_contains($u, 'st=x'));
    $forB = array_filter($log, fn ($u) => str_contains($u, '/rowsB') && str_contains($u, 'st=x'));

    expect($forA)->not->toBeEmpty()->and($forB)->toBeEmpty();
});

it('data-table without filters is not reloaded by the filters of another table', function () {
    $page = visit(browserPage('data-table-no-filters', <<<'BLADE'
        <x-dbl::display.data-table id="A" load-url="/rowsA" :columns="[['key' => 'id']]" :filters="[['key' => 'st', 'type' => 'text']]" :searchable="false" />
        <x-dbl::display.data-table id="B" load-url="/rowsB" :columns="[['key' => 'id']]" :searchable="false" />
        BLADE));
    $page->script('window.__net.log.length = 0');

    $page->script("document.querySelector('#A .dropdown label').click()");
    setField($page, '#A .dropdown input[type=text]', 'x');
    pause($page, 0.9);

    $log = $page->script('window.__net.log');
    $forA = array_filter($log, fn ($u) => str_contains($u, '/rowsA') && str_contains($u, 'st=x'));
    $forB = array_filter($log, fn ($u) => str_contains($u, '/rowsB') && str_contains($u, 'st=x'));

    // A must reload (otherwise the test proves nothing); B has no filters and must stay quiet.
    expect($forA)->not->toBeEmpty()->and($forB)->toBeEmpty();
});
