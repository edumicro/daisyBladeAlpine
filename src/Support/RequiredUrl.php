<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Support;

use InvalidArgumentException;

/**
 * Refuses to render a component whose URL is the reason it exists, when the caller did not give it.
 *
 * Why: 3.0 has no aliases, so a name of the 2.x line (`action=`, `options-url=`) is not a prop any more. Blade
 * does not complain about an unknown attribute: it lands in `$attributes` as a plain HTML attribute, and the
 * component runs with an EMPTY URL. In the browser that looks like a working component: an auto-form POSTs to the
 * current page, receives its HTML with a 200 and shows "Saved!". Stopping at render time, with a message that
 * names the missing property (and the old name, if it had one), is the cheapest warning that is not an alias.
 *
 * It only checks for an empty URL. It does not know what URLs the application has.
 */
final class RequiredUrl
{
    /**
     * @param  string  $component  The tag without the `x-dbl::` prefix, e.g. `sections.auto-form`.
     * @param  string  $property  The property as written in the tag (kebab-case), e.g. `submit-url`.
     * @param  string  $url  What the view received for it.
     * @param  string|null  $nameInTwo  The name this property had in the 2.x line, when it was another one.
     *
     * @throws InvalidArgumentException when `$url` is empty.
     */
    public static function check(string $component, string $property, string $url, ?string $nameInTwo = null): void
    {
        if ($url !== '') {
            return;
        }

        $hint = $nameInTwo === null ? '' : " (in 2.x it was called `{$nameInTwo}`)";

        throw new InvalidArgumentException("<x-dbl::{$component}> needs the `{$property}` property{$hint}.");
    }
}
