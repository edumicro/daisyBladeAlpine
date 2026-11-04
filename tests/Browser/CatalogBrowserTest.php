<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;

/*
 * Smoke test of the catalog (tests/Catalog/Catalog.php) in a real browser: every example is painted, started by Alpine, and the
 * page must stay quiet: no error and no warning in the console (an Alpine expression that fails, a missing factory, an import
 * that does not resolve...).
 *
 * Every example is opened twice. The second time its texts are hostile (Example::HOSTILE: it closes a quote, opens an <img onerror>
 * and breaks out of a JavaScript string with `'+alert(1)+'`): if any of them gets to run, the page's `alert()` is called, which
 * the layout of the browser tests records in `window.__alerted`.
 */

/** @return array<string, array{0: string}> the examples that a browser can open (and, with `$withTexts`, that have texts of the caller), by name. */
function catalogForTheBrowser(bool $withTexts = false): array
{
    $names = [];
    foreach (Catalog::examples() as $name => $example) {
        if ($example->whyNotInBrowser() === null && (! $withTexts || $example->hasTexts())) {
            $names[$name] = [$name];
        }
    }

    return $names;
}

/** Opens `$name` painted with its own texts or with the hostile ones, and gives back the page after Alpine and the first requests had time. */
function openCatalogExample(string $name, bool $hostile)
{
    $example = Catalog::examples()[$name];
    $slug = 'catalog-'.($hostile ? 'hostile-' : '').preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
    $page = visit(browserPageOfHtml($slug, $example->renderForBrowser($hostile), ['modules' => $example->modulesForBrowser()]));
    pause($page, 0.5);

    return $page;
}

it('the examples that the browser skips say why', function () {
    $skipped = collect(Catalog::examples())->filter(fn ($example) => $example->whyNotInBrowser() !== null)->map(fn ($example) => $example->whyNotInBrowser());

    expect($skipped->every(fn ($reason) => strlen($reason) > 10))->toBeTrue();
});

it('an example of the catalog starts in the browser without errors in the console', function (string $name) {
    $page = openCatalogExample($name, hostile: false);

    $page->assertNoSmoke();
})->with(fn () => catalogForTheBrowser());

it('an example of the catalog with hostile texts runs nothing of what the texts say', function (string $name) {
    $page = openCatalogExample($name, hostile: true);

    expect($page->script('window.__alerted === true'))->toBeFalse();
    $page->assertNoSmoke();
})->with(fn () => catalogForTheBrowser(withTexts: true));
