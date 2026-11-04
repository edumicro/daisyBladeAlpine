<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;
use Edumicro\DaisyBlade\Tests\Catalog\Example;
use Edumicro\DaisyBlade\Tests\Catalog\MarkingTranslator;
use Edumicro\DaisyBlade\Tests\Catalog\RenderedHtml;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * Every text that a component adds by itself goes through `__()`, or an application in another language shows it in English.
 *
 * The test paints the catalog with a translator that wraps every translated text in ⟦…⟧. A visible text, or a text attribute
 * (`alt`, `title`, `aria-label`, `placeholder`, `data-tip`, `label`...), that has no ⟦ and does not end in `§` (the mark of the
 * texts that the CALLER wrote in the catalog) was written by hand in a view or is the untranslated default of a prop. Reading the
 * painted page sees both, with any quotes and any prop syntax, which the scan of the source did not.
 */

beforeEach(function () {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();
    MarkingTranslator::install($this->app);
    config(['app.name' => 'App'.Example::CALLER_MARK]);   // the name of the application is the application's, not a text to translate
});

/** A text is accounted for when it was translated, or the caller wrote it. */
function isAccountedFor(string $text): bool
{
    return str_contains($text, MarkingTranslator::OPENING) || str_contains($text, Example::CALLER_MARK);
}

it('the translator of the test marks what it translates', function () {
    expect(__('Search...'))->toStartWith(MarkingTranslator::OPENING)->toEndWith(MarkingTranslator::CLOSING);
});

it('every visible text of a painted component was translated or written by the caller', function (string $name) {
    $example = Catalog::examples()[$name];
    $untranslated = array_values(array_filter(RenderedHtml::visibleTexts($example->render()), fn ($text) => ! isAccountedFor($text) && ! $example->isTechnical($text)));

    expect($untranslated)->toBe([]);
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));

it('every text in an attribute (alt, title, aria-label, placeholder, data-tip, label) was translated or written by the caller', function (string $name) {
    $example = Catalog::examples()[$name];
    $untranslated = array_values(array_filter(RenderedHtml::textAttributes($example->render()), fn ($found) => ! isAccountedFor($found[1]) && ! $example->isTechnical($found[1])));

    expect($untranslated)->toBe([]);
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));

it('every text that an Alpine attribute writes with a literal (:title="\'Open\'") was translated or written by the caller', function (string $name) {
    $untranslated = [];
    foreach (RenderedHtml::tags(Catalog::examples()[$name]->render()) as $tag) {
        foreach ($tag['attributes'] as [$attribute, $value]) {
            if (! in_array($attribute, RenderedHtml::ALPINE_TEXT_ATTRIBUTES, true) || $value === null) {
                continue;
            }
            preg_match_all('/\'([^\']*)\'|"([^"]*)"/', html_entity_decode($value, ENT_QUOTES | ENT_HTML5), $literals);
            foreach (array_filter(array_merge($literals[1], $literals[2]), fn ($literal) => preg_match('/\p{L}{3,}/u', $literal) === 1 && ! isAccountedFor($literal)) as $literal) {
                $untranslated[] = "<{$tag['name']} {$attribute}> {$literal}";
            }
        }
    }

    expect($untranslated)->toBe([]);
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));
