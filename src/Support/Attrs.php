<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Support;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Renders an array of raw HTML attributes onto an element.
 *
 * Schema-driven components (`form.repeater`, `form.fields`, `sections.auto-form`) take their field
 * definitions as PHP arrays, so they have no attribute bag to merge into. Without this, every HTML
 * attribute a caller might need — `inputmode`, `autocomplete`, `pattern`, `maxlength`, `aria-*` —
 * has to be added to the component one prop at a time. This is the array equivalent of the
 * attribute bag: the component stops needing to know which attributes exist.
 *
 *     ['key' => 'ref', 'attrs' => ['autocomplete' => 'off', 'maxlength' => 12]]
 *
 * `Illuminate\View\ComponentAttributeBag` deliberately does *not* do this job: it is `Htmlable`, so
 * `{{ $attributes }}` never escapes it, and its only guard is `str_replace('"', '\"')` — which does
 * not neutralise a quote in attribute context. That is safe for attributes written in Blade source
 * and unsafe for values that arrive as data, which is exactly this case.
 *
 * Event handlers are refused: bindings go through {@see Bindings}, which is explicit about being
 * executable. A schema that can quietly carry an `onclick` is a different kind of object.
 */
final class Attrs
{
    /** Conservative: HTML allows more, but this covers every real attribute and no surprises. */
    private const NAME = '/^[A-Za-z][A-Za-z0-9-]*$/';

    /**
     * @param  array<array-key, mixed>  $attrs  What the caller's schema wants on the element. It WINS over `$defaults`.
     * @param  string  $componentClass  The classes the component itself puts on the element. They are written together with
     *                                  the `class` of the schema in ONE `class` attribute: with two, the browser keeps the first
     *                                  and silently drops the other (`['class' => 'font-mono']` used to erase `input w-full`).
     * @param  array<array-key, mixed>  $defaults  Attributes of the view that the schema may replace; a `null` value writes nothing.
     */
    public static function render(array $attrs, string $componentClass = '', array $defaults = []): HtmlString
    {
        // The attributes that the view itself wants (`placeholder`, `rows`, `type`...) are merged HERE, with the schema's on top.
        // Written next to `Attrs::render()` in the view they were a second copy of the same attribute: the browser keeps the
        // first and drops the other without a word, so the schema lost.
        $attrs = array_merge($defaults, $attrs);
        $rendered = [];

        // The `class` of the schema is joined to the component's, and written first, before the other attributes.
        if ($componentClass !== '') {
            $classes = trim($componentClass.' '.(string) ($attrs['class'] ?? ''));
            unset($attrs['class']);
            $rendered[] = 'class="'.e($classes).'"';
        }

        foreach ($attrs as $name => $value) {
            $name = self::name($name);

            // Valueless attributes: `['readonly' => true]` renders `readonly`, false renders nothing.
            if (is_bool($value) || $value === null) {
                if ($value === true) {
                    $rendered[] = $name;
                }

                continue;
            }

            $rendered[] = $name.'="'.e((string) $value).'"';
        }

        return new HtmlString(implode(' ', $rendered));
    }

    private static function name(int|string $name): string
    {
        $name = (string) $name;

        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(
                "Invalid attribute name [{$name}]: letters, digits and dashes only."
            );
        }

        if (str_starts_with(strtolower($name), 'on')) {
            throw new InvalidArgumentException(
                "Attribute [{$name}] is an event handler. Use the component's [events] prop instead, "
                .'which is explicit about running code in the browser.'
            );
        }

        return $name;
    }
}
