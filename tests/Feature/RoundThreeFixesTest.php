<?php

use Illuminate\Support\Facades\Blade;

/**
 * Failures of one component found in round 3 (not of a cause): each one with its own test. The calendar, the upload and the
 * spreadsheet are in tests/js; the pagination also has its browser walk.
 */

/** The labels of the buttons of a pagination: numbers and "...". */
function paginationPages(int $current, int $last, string $props = ''): array
{
    $html = Blade::render('<x-dbl::navigation.pagination :meta="$meta" '.$props.' />', ['meta' => ['current_page' => $current, 'last_page' => $last, 'total' => $last * 10, 'per_page' => 10]]);
    preg_match_all('/<(?:button|span)[^>]*class="[^"]*join-item[^"]*"[^>]*>\s*([\d…]+)\s*<\/(?:button|span)>/', $html, $found);

    return $found[1];
}

// ── pagination: the off-by-one ─────────────────────────────────────────────

it('pagination: page 4 of 10 offers the first page (it used to disappear)', function () {
    expect(paginationPages(4, 10))->toBe(['1', '2', '3', '4', '5', '6', '…', '10']);
});

it('pagination: page 5 of 10 shows the first page and the dots for the ones left out', function () {
    expect(paginationPages(5, 10))->toBe(['1', '…', '3', '4', '5', '6', '7', '…', '10']);
});

it('pagination: it is symmetric at the end', function () {
    expect(paginationPages(7, 10))->toBe(['1', '…', '5', '6', '7', '8', '9', '10']);
    expect(paginationPages(6, 10))->toBe(['1', '…', '4', '5', '6', '7', '8', '…', '10']);
});

it('pagination: the first and the last page are there from wherever the window starts or ends', function () {
    foreach (range(1, 12) as $current) {
        $pages = paginationPages($current, 12);

        expect($pages[0])->toBe('1')->and(end($pages))->toBe('12');
    }
});

it('pagination: without show-first-last there are only the pages of the window', function () {
    expect(paginationPages(5, 10, ':show-first-last="false"'))->toBe(['3', '4', '5', '6', '7']);
});

it('pagination: a single page, or few, have no dots', function () {
    expect(paginationPages(1, 1))->toBe(['1'])
        ->and(paginationPages(2, 3))->toBe(['1', '2', '3']);
});

// ── tree: a list item that is a list ───────────────────────────────────────

it('tree: a list whose item is itself a list is a branch without a title, not "Array to string conversion"', function () {
    $html = Blade::render('<x-dbl::display.tree :items="[[\'a\', \'b\'], \'c\']" />');

    expect($html)->toContain('<details')->toContain('>a<')->toContain('>b<')->toContain('>c<');
});

it('tree: the keys that are names still give the title of the branch', function () {
    $html = Blade::render('<x-dbl::display.tree :items="[\'Root\' => [\'child\']]" />');

    expect($html)->toContain('Root')->toContain('child');
});

// ── layout.auth: lang ──────────────────────────────────────────────────────

it('layout.auth: a lang of the caller is the only lang of the page (it used to write two and the first won)', function () {
    $this->withoutVite();
    $html = Blade::render('<x-dbl::layout.auth lang="ca" />');

    expect(substr_count($html, ' lang='))->toBe(1)
        ->and($html)->toContain('lang="ca"');
});

it('layout.auth: without lang it takes the one of the application', function () {
    $this->withoutVite();
    app()->setLocale('es');

    expect(Blade::render('<x-dbl::layout.auth />'))->toContain('lang="es"');
});
