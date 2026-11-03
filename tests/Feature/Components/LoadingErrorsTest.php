<?php

use Illuminate\Support\Facades\Blade;

/**
 * The components that ask the server for something must never stay "loading" for ever: when the request
 * fails they show a (translated) message and, where it makes sense, a retry button. The JavaScript side
 * is tested in tests/js/loading-errors.test.mjs; here, that the Blade gives it somewhere to show it.
 */

it('data-table shows a failed load as a message with a retry button instead of an empty table', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'a\', \'label\' => \'A\']]" />');

    expect($html)->toContain('x-if="!loading && error"')
        ->toContain('x-text="error"')
        ->toContain('role="alert"')
        ->toContain('Retry')
        // The "no data" row must not appear on top of the error.
        ->toContain('x-if="!loading && !error && rows.length === 0"');
});

it('lazy tabs show a failed tab as a message with a retry button, and hide the empty content', function () {
    $html = Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load-url\' => \'/a\']]" />');

    expect($html)->toContain('hasError(0)')
        ->toContain('x-text="errors[0]"')
        ->toContain('retry(0)')
        ->toContain('x-show="!isLoading(0) && !hasError(0)"');
});

it('puts the Alpine scope of the remote select on its container, so the error can sit next to the select', function () {
    $html = Blade::render('<x-dbl::form.select name="city" options-url="/cities" />');

    // x-data comes before the <select>, on the container; the <select> itself has none.
    $dataAt = strpos($html, 'x-data="dbSelectRemote');
    $selectAt = strpos($html, '<select');
    expect($dataAt)->not->toBeFalse()
        ->and($dataAt)->toBeLessThan($selectAt)
        ->and(substr($html, $selectAt, strpos($html, '>', $selectAt) - $selectAt))->not->toContain('x-data')
        ->and($html)->toContain('x-show="error"')->toContain('retry()')->toContain(':aria-busy="loading"');
});

it('keeps the other selects without any Alpine scope on the container', function () {
    $native = Blade::render('<x-dbl::form.select name="x" :options="[\'a\' => \'A\']" />');

    expect($native)->not->toContain('dbSelectRemote')->not->toContain('x-show="error"');
});

// ── data-table listens to both filter bars ────────────────────────────────────

it('data-table listens to the events of display.filters and of form.filter, with the names each one already used', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[]" />');

    expect($html)->toContain('@filters-updated.window="applyFilters($event.detail.filters)"')
        ->toContain('@filters-cleared.window="clearFilters()"')
        ->toContain('@filter-apply.window="applyFilters($event.detail.values)"')
        ->toContain('@filter-clear.window="clearFilters()"');
});

it('keeps the event names of both filter bars as they were', function () {
    $filters = Blade::render('<x-dbl::display.filters :filters="[[\'key\' => \'a\']]" />');
    $form = Blade::render('<x-dbl::form.filter :fields="[[\'name\' => \'a\']]" />');

    expect($filters)->toContain("\$dispatch('filters-updated', { filters: this.active })")->toContain("\$dispatch('filters-cleared')")
        ->and($form)->toContain("\$dispatch('filter-apply', { values: this.values, queryString: qs.toString() })")->toContain("\$dispatch('filter-clear')");
});
