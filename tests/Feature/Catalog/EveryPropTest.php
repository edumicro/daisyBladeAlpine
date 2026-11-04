<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;
use Edumicro\DaisyBlade\Tests\Catalog\DerivedVariants;
use Edumicro\DaisyBlade\Tests\Catalog\Example;
use Edumicro\DaisyBlade\Tests\Catalog\MarkingTranslator;
use Edumicro\DaisyBlade\Tests\Catalog\RenderedHtml;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * THE CATALOG DERIVED FROM `@props` (tests/Catalog/DerivedVariants.php).
 *
 * The hand-written examples only paint the props their author thought of. Here every view says by itself what to try: each text
 * prop gets the hostile text and a marked text, each boolean its opposite, each prop with a closed list of values every value. The
 * rules of the other catalog tests (escaped, translated, no repeated attribute) are applied to the result, and a last test fails
 * for any prop that no example and no variant ever paints, so a new prop cannot hide from them.
 */

beforeEach(function () {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();
});

/**
 * A prop that NAMES a thing that must exist (a heroicon): an unknown name is an error of the developer, not something to escape, so a
 * variant that gives it a text and fails to paint is not a finding. The icon component itself is all about that name.
 */
function namesAnIcon(Example $example, string $prop): bool
{
    return $example->component === 'components.icon' || str_contains(strtolower($prop), 'icon');
}

/**
 * Props that no example needs to pass, and why. Every line is a decision somebody can read; a new prop is not here by default.
 */
const PROPS_WITHOUT_AN_EXERCISE = [];

/**
 * Paints the variants of the catalog of the kinds asked for. A variant can make no sense with some example (a `load-url` on an example
 * with grouped options is two modes at once): the ones that fail to paint are skipped, but a variant that NO example of its component
 * can paint is a finding, reported by `unpaintedVariants()`.
 *
 * @return array{painted: list<array{0: Example, 1: string, 2: string}>, unpainted: list<string>}  painted = [example, label, html]
 */
function paintVariants(array $kinds, bool $withBag = false): array
{
    $painted = [];
    $triedOk = [];       // "component / variant label" => true once some example painted it
    $triedAtAll = [];    // "component / variant label" => the error of the last attempt
    foreach (Catalog::examples() as $example) {
        foreach (DerivedVariants::of($example) as $label => $variant) {
            if (! in_array($variant['kind'], $kinds, true)) {
                continue;
            }
            $key = "{$example->component} / {$label}";
            // A prop that names an icon, or that defaults to `null` (it may be a list or a model), may get a text of the wrong type: not a finding.
            $tolerated = $variant['guess'] || $example->component === 'components.icon' || str_contains(strtolower($variant['prop']), 'icon');
            try {
                $html = $example->withProps($variant['props'])->render($withBag ? $example->bag() : []);
                $painted[] = [$example, $label, $html];
                $triedOk[$key] = true;
            } catch (Throwable $error) {
                $triedAtAll[$key] = $tolerated ? null : $error->getMessage();
            }
        }
    }
    $unpainted = [];
    foreach ($triedAtAll as $key => $message) {
        if ($message !== null && ! isset($triedOk[$key])) {
            $unpainted[] = "{$key}: {$message}";
        }
    }

    return ['painted' => $painted, 'unpainted' => $unpainted];
}

it('every prop declared in a @props is painted by some example or variant', function () {
    $unexercised = [];
    foreach (Catalog::componentsWithAView() as $component) {
        $exercised = DerivedVariants::exercised($component, Catalog::examples());
        foreach (array_keys(DerivedVariants::declared($component)) as $prop) {
            if (! in_array($prop, $exercised, true) && ! in_array("{$component}.{$prop}", array_keys(PROPS_WITHOUT_AN_EXERCISE), true)) {
                $unexercised[] = "{$component}.{$prop}";
            }
        }
    }

    expect($unexercised)->toBe([]);
});


it('the hostile text of a user is escaped in every text prop, also the ones no example passes', function () {
    $run = paintVariants(['hostile']);

    $alive = [];
    foreach ($run['painted'] as [$example, $label, $html]) {
        if (RenderedHtml::liveHostileTags($html) !== []) {
            $alive[] = $example->name().' / '.$label;
        }
    }

    expect($alive)->toBe([])->and($run['unpainted'])->toBe([]);
});

it('every text is translated or the caller\'s, with each text prop given by the caller, each flag turned and each value tried', function () {
    MarkingTranslator::install($this->app);
    config(['app.name' => 'App'.Example::CALLER_MARK]);
    $run = paintVariants(['marked', 'flipped', 'value', 'number']);

    $untranslated = [];
    foreach ($run['painted'] as [$example, $label, $html]) {
        $texts = array_merge(RenderedHtml::visibleTexts($html), array_column(RenderedHtml::textAttributes($html), 1));
        foreach ($texts as $text) {
            if (! str_contains($text, MarkingTranslator::OPENING) && ! str_contains($text, Example::CALLER_MARK) && ! $example->isTechnical($text)) {
                $untranslated[$example->name().' / '.$label][] = $text;
            }
        }
    }

    expect($untranslated)->toBe([])->and($run['unpainted'])->toBe([]);
});

it('no tag repeats an attribute, with the attributes of a user on it, in any variant', function () {
    $run = paintVariants(['hostile', 'marked', 'flipped', 'value', 'number'], withBag: true);

    $repeated = [];
    foreach ($run['painted'] as [$example, $label, $html]) {
        foreach (RenderedHtml::repeatedAttributes($html) as $found) {
            $repeated[$example->name().' / '.$label][] = $found;
        }
    }

    expect($repeated)->toBe([]);
});
