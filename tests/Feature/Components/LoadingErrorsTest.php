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
    $html = Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/a\']]" />');

    expect($html)->toContain('hasError(0)')
        ->toContain('x-text="errors[0]"')
        ->toContain('retry(0)')
        ->toContain('x-show="!isLoading(0) && !hasError(0)"');
});

it('puts the Alpine scope of the remote select next to the select, around its error message', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" />');

    // The <select> comes first and has no x-data; the element with the scope follows it and holds the message and the retry.
    $dataAt = strpos($html, 'x-data="dblSelectRemote');
    $selectAt = strpos($html, '<select');
    expect($dataAt)->not->toBeFalse()
        ->and($selectAt)->toBeLessThan($dataAt)
        ->and(substr($html, $selectAt, strpos($html, '>', $selectAt) - $selectAt))->not->toContain('x-data')
        ->and(strpos($html, 'x-show="error"'))->toBeGreaterThan($dataAt)
        ->and($html)->toContain('retry()')->toContain('x-effect="syncField()"');
});

it('keeps the other selects without any Alpine scope on the container', function () {
    $native = Blade::render('<x-dbl::form.select name="x" :options="[\'a\' => \'A\']" />');

    expect($native)->not->toContain('dblSelectRemote')->not->toContain('x-show="error"');
});

// ── data-table listens to the filter panel ────────────────────────────────────

it('data-table listens to the two events of form.filters', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[]" />');

    expect($html)->toContain('@dbl-filters-applied.window="applyFilters($event.detail.filters)"')
        ->toContain('@dbl-filters-cleared.window="clearFilters()"');
});

it('form.filters announces its two events with { filters }', function () {
    $filters = Blade::render('<x-dbl::form.filters :filters="[[\'key\' => \'a\']]" />');

    expect($filters)->toContain("\$dispatch('dbl-filters-applied', { filters: this.filters })")
        ->toContain("\$dispatch('dbl-filters-cleared', { filters: {} })");
});
