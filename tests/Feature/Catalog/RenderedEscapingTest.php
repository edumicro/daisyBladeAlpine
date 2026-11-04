<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;
use Edumicro\DaisyBlade\Tests\Catalog\Example;
use Edumicro\DaisyBlade\Tests\Catalog\RenderedHtml;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * The text of a user is text, never markup. The test paints every example of the catalog with a hostile text
 * (Example::HOSTILE: it closes an attribute and opens an <img onerror>) in EVERY prop that holds text of the caller, and looks
 * at the painted HTML: no tag that the hostile text wrote may be alive, and no tag may carry an event handler (`onerror`...).
 *
 * The props that the documentation declares TRUSTED HTML (the content of a tab, of an accordion, of a collapse) are not given the
 * hostile text: they are HTML on purpose, and the example does not list them in `texts()`. They are the short list at the end of
 * docs/contrato.md ("Texto y HTML de confianza").
 * What runs in the JavaScript of an Alpine attribute is checked in the browser: tests/Browser/CatalogBrowserTest.php.
 */

beforeEach(function () {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();
});

it('the hostile text of a user is escaped in every prop that holds text', function (string $name) {
    $example = Catalog::examples()[$name];
    expect($example->hasTexts())->toBeTrue('the example lists no prop with text: say which ones in ->texts(...)');

    expect(RenderedHtml::liveHostileTags($example->render(hostile: true)))->toBe([]);
})->with(fn () => collect(Catalog::examples())->filter(fn ($example) => $example->hasTexts())->keys()->mapWithKeys(fn ($name) => [$name => [$name]])->all());

it('the examples without texts of the caller are the ones that have none to escape', function () {
    // A list, not a rule: a new component without texts in its example is a decision that shows in the review.
    $without = collect(Catalog::examples())->reject(fn ($example) => $example->hasTexts())->keys()->all();

    expect($without)->toBe(array_values($without))->and(count($without))->toBeLessThan(count(Catalog::examples()) / 2);
});

it('the detector sees a hostile text that was NOT escaped', function () {
    // The test of the test: the same text written raw is alive.
    $raw = '<p title="'.Example::HOSTILE.'">'.Example::HOSTILE.'</p>';

    expect(RenderedHtml::liveHostileTags($raw))->not->toBe([]);
    expect(RenderedHtml::liveHostileTags(e($raw)))->toBe([]);
});
