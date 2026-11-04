<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

/**
 * 3.0 renamed the key of a tab, `'load-url'` to `'load_url'`, with no alias. A tab written with the old key (or with none)
 * got an empty URL: no request, no spinner, no error, an empty panel for ever. Every other URL of the package that is
 * missing warns at render time; the one of a tab is a key of an array, not a prop, so it has its own check.
 */
it('refuses a tab that has no load_url, naming the key and the old name', function () {
    try {
        Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load-url\' => \'/tab\']]" />');
    } catch (ViewException $failure) {
        // Laravel wraps what a view throws, once for each view that was rendering: the first that is not a wrapper is the real one.
        $thrown = $failure;
        while ($thrown instanceof ViewException && $thrown->getPrevious() !== null) {
            $thrown = $thrown->getPrevious();
        }

        expect($thrown)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($thrown->getMessage())->toContain('tabs[0].load_url')->toContain('load-url');

        return;
    }

    throw new RuntimeException('The tab without load_url rendered without failing.');
});

it('points at the tab that has no URL, not only at the first', function () {
    expect(fn () => Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/a\'], [\'label\' => \'B\']]" />'))
        ->toThrow(ViewException::class, 'tabs[1].load_url');
});

it('renders the tabs that have their load_url', function () {
    $html = Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/a\']]" />');

    expect($html)->toContain('dblTabsLazy');
});
