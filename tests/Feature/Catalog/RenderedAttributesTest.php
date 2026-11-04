<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;
use Edumicro\DaisyBlade\Tests\Catalog\Example;
use Edumicro\DaisyBlade\Tests\Catalog\RenderedHtml;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * Two copies of the same attribute in a tag: the browser keeps the FIRST and drops the other without a word. The user's
 * `placeholder`, `x-on:click` or `:disabled` was lost like that whenever a view wrote its own before the bag of attributes.
 *
 * The test does not read the source of the views (it would need a rule for every way of writing a tag): it paints every
 * example of the catalog and counts the attributes of the HTML that came out.
 */

beforeEach(function () {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();
});

/** The names of the attributes that the HTML repeats within one tag: ['<input> x-model x2', ...]. */
function repeatedAttributes(string $html): array
{
    $repeated = [];
    foreach (RenderedHtml::tags($html) as $tag) {
        foreach (array_count_values(array_column($tag['attributes'], 0)) as $name => $times) {
            if ($times > 1) {
                $repeated[] = "<{$tag['name']}> {$name} x{$times}";
            }
        }
    }

    return $repeated;
}

/** The bag as it reaches the HTML (`::disabled` is written `:disabled`). */
function bagAsPainted(Example $example): array
{
    $bag = [];
    foreach ($example->bag() as $name => $value) {
        $bag[ltrim($name, ':') === $name ? $name : substr($name, 1)] = $value;
    }

    return $bag;
}

it('no tag repeats an attribute, whatever the example', function (string $name) {
    expect(repeatedAttributes(Catalog::examples()[$name]->render()))->toBe([]);
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));

it('no tag repeats an attribute when a user adds attributes to the component', function (string $name) {
    $example = Catalog::examples()[$name];

    expect(repeatedAttributes($example->render($example->bag())))->toBe([]);
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));

it('the attributes of a user reach the element once, and the user wins', function (string $name) {
    $example = Catalog::examples()[$name];
    $tags = RenderedHtml::tags($example->render($example->bag()));
    $receivers = array_values(array_filter($tags, fn ($tag) => RenderedHtml::valueOf($tag, 'data-user') === '1'));

    if (! $example->forwardsAttributes()) {
        expect($receivers)->toBe([], 'it was declared without a bag, and it has one');

        return;
    }
    expect($receivers)->not->toBe([], "{$name} does not hand the attributes of the user to any element");

    foreach ($receivers as $tag) {
        foreach (bagAsPainted($example) as $attribute => $value) {
            if ($attribute === 'class') {
                expect(RenderedHtml::valueOf($tag, 'class'))->toContain('user-class');
            } else {
                expect(RenderedHtml::countOf($tag, $attribute))->toBe(1, "<{$tag['name']}> {$attribute}");
                expect(RenderedHtml::valueOf($tag, $attribute))->toBe($value, "<{$tag['name']}> {$attribute}: the user's value must win");
            }
        }
    }
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));
