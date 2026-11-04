<?php

use Illuminate\Support\Facades\Blade;

/**
 * The table listened to `dbl-filters-applied` on the WINDOW, so with two tables in a page the filter of the first one
 * reloaded the second too, with the filters of the first. With the `filters` prop the panel is inside the table and its
 * events rise to it: no `.window`. Without the prop the panel is somewhere else in the page, and the table listens on the window.
 */
it('listens only to its own filters when it carries the panel', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'id\']]" :filters="[[\'key\' => \'st\']]" />');

    expect($html)->toContain('@dbl-filters-applied="applyFilters($event.detail.filters)"')
        ->toContain('@dbl-filters-cleared="clearFilters()"')
        ->not->toContain('dbl-filters-applied.window')
        ->not->toContain('dbl-filters-cleared.window');
});

it('listens to no filters at all without the filters prop nor external-filters', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'id\']]" />');

    expect($html)->not->toContain('dbl-filters-applied')->not->toContain('dbl-filters-cleared');
});

it('listens on the window only when external-filters says its filters are somewhere else in the page', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'id\']]" external-filters />');

    expect($html)->toContain('@dbl-filters-applied.window="applyFilters($event.detail.filters)"')
        ->toContain('@dbl-filters-cleared.window="clearFilters()"');
});

it('puts the listeners on an element that contains the panel of filters', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'id\']]" :filters="[[\'key\' => \'st\']]" />');

    // The element with the listener must be an ancestor of the panel (the x-data of form.filters), or the event would not rise to it.
    $listener = strpos($html, '@dbl-filters-applied=');
    $panel = strpos($html, 'filters: {}');
    $closeOfListenerTag = strpos($html, '>', $listener);

    expect($listener)->not->toBeFalse()->and($panel)->toBeGreaterThan($closeOfListenerTag);
});
