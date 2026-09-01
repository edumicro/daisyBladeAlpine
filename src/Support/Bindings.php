<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Support;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Turns a nested event map into Alpine `x-on:` attributes.
 *
 * Components declare their own default bindings and let the consumer override them through an
 * `events` prop. The map is a tree whose path is the Alpine binding spec and whose leaves are
 * Alpine expressions:
 *
 *     ['keydown' => ['enter' => ['prevent' => 'add()']]]   →   x-on:keydown.enter.prevent="add()"
 *
 * Dotted keys are accepted and normalised into the same tree, so `'keydown.enter.prevent'` and the
 * nested form are interchangeable — you can copy a binding straight out of the Alpine docs.
 *
 * The tree shape is what makes overriding work. A consumer passing `['keydown' => ['enter' => 'x()']]`
 * over a default of `['keydown' => ['enter' => ['prevent' => 'add()']]]` **replaces** that branch,
 * because a leaf replaces a subtree. With flat dotted keys the two would be different keys, both
 * would survive the merge, and both would fire.
 *
 * Nothing here knows which modifiers exist: a string is a leaf, an array is more modifiers. That is
 * what keeps `.prevent`, `.window`, `.outside`, `.debounce.500ms` and whatever Alpine adds next
 * working without a change in this class.
 *
 * ⚠️ The leaves are executable by design. They are for expressions written by the developer, never
 * for values that reach the application from outside it.
 */
final class Bindings
{
    /** Alpine event names and modifiers. Anything else could break out of the attribute name. */
    private const SEGMENT = '/^[A-Za-z0-9_-]+$/';

    /**
     * Merge consumer bindings over a component's defaults and render them as attributes.
     *
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $overrides
     */
    public static function render(array $defaults, array $overrides = []): HtmlString
    {
        $attributes = [];

        foreach (self::flatten(self::merge($defaults, $overrides)) as $spec => $expression) {
            $attributes[] = 'x-on:'.$spec.'="'.e($expression).'"';
        }

        return new HtmlString(implode(' ', $attributes));
    }

    /**
     * Expand dotted keys into the nested form.
     *
     * @param  array<array-key, mixed>  $map
     * @return array<string, mixed>
     */
    public static function normalize(array $map): array
    {
        $tree = [];

        foreach ($map as $key => $value) {
            $prefix = self::segments($key);

            if (is_array($value)) {
                foreach (self::flatten(self::normalize($value)) as $spec => $expression) {
                    $tree = self::insert($tree, [...$prefix, ...self::segments($spec)], $expression);
                }

                continue;
            }

            $tree = self::insert($tree, $prefix, self::expression($value, $key));
        }

        return $tree;
    }

    /**
     * Overrides win. A leaf on the override side replaces the whole default branch below it.
     *
     * @param  array<array-key, mixed>  $defaults
     * @param  array<array-key, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $overrides): array
    {
        // array_replace_recursive only recurses when *both* sides are arrays, so a consumer leaf
        // replaces the default branch below it — which is the behaviour this map is shaped for.
        return array_replace_recursive(self::normalize($defaults), self::normalize($overrides));
    }

    /**
     * Collapse the tree back into `spec => expression` pairs.
     *
     * @param  array<array-key, mixed>  $tree
     * @return array<string, string>
     */
    public static function flatten(array $tree, string $prefix = ''): array
    {
        $flat = [];

        foreach ($tree as $key => $value) {
            $spec = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            // Assigned key by key, never array_merge: a segment of digits only is an integer key
            // to PHP, and array_merge renumbers those instead of keeping them.
            if (is_array($value)) {
                foreach (self::flatten($value, $spec) as $subSpec => $expression) {
                    $flat[$subSpec] = $expression;
                }

                continue;
            }

            $flat[$spec] = $value;
        }

        return $flat;
    }

    /**
     * @param  list<string>  $path
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    private static function insert(array $tree, array $path, string $expression): array
    {
        $segment = array_shift($path);

        if ($path === []) {
            if (array_key_exists($segment, $tree)) {
                throw self::conflict($segment);
            }

            $tree[$segment] = $expression;

            return $tree;
        }

        $child = $tree[$segment] ?? [];

        if (! is_array($child)) {
            throw self::conflict($segment);
        }

        $tree[$segment] = self::insert($child, $path, $expression);

        return $tree;
    }

    /** @return list<string> */
    private static function segments(int|string $key): array
    {
        $segments = explode('.', (string) $key);

        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT, $segment) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid event segment [{$segment}] in binding [{$key}]: "
                    .'event names and modifiers may only contain letters, digits, underscores and dashes.'
                );
            }
        }

        return $segments;
    }

    private static function expression(mixed $value, int|string $key): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "Binding [{$key}] must be an Alpine expression string; "
                .get_debug_type($value).' given. Handlers run in the browser, not in PHP.'
            );
        }

        return $value;
    }

    private static function conflict(string $segment): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Event [{$segment}] is declared twice, or has both a handler and modifiers below it. "
            .'Alpine cannot express both on one element; give the handler its own modifier, '
            .'or drop the duplicate.'
        );
    }
}
