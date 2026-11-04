<?php

namespace Edumicro\DaisyBlade\Tests\Catalog;

/**
 * The VARIANTS of every example, DERIVED from the `@props` of its view instead of written by hand.
 *
 * A hand-written example only touches the props its author thought of, so a prop that nobody listed (`avatar-class`, `method`,
 * the `editable` flag of a graph) is never painted and its bugs never seen. Here the props themselves say what to try:
 *
 *   a TEXT prop (default `''`, `'POST'` or `null`)       -> the hostile text (Example::HOSTILE), and a marked text (`Marked§`)
 *   a BOOLEAN prop (default `true` or `false`)            -> the opposite of what the example gives it
 *   a NUMBER prop (default `15`)                          -> the number plus one
 *   a prop with a closed list of VALUES                   -> one variant per value
 *        (the arms of `match($prop)` in the view, or the `// a | b | c` comment next to the prop)
 *   any other prop (a list of items, a callback)          -> not derived: only a hand-written example exercises it
 *
 * `DerivedVariants::exercised()` tells which props some example or variant touches; the test of the catalog fails for the rest.
 * The hand-written examples stay for what only a person knows (a business case, a typical set of columns).
 */
final class DerivedVariants
{
    /** The marked text of the caller: the translator test must see it as the caller's, not as a default of the component. */
    public const MARKED = 'Marked'.Example::CALLER_MARK;

    /** Props that hold HTML on purpose, documented as trusted HTML in docs/contrato.md: they do not get the hostile text. */
    public const TRUSTED_HTML = ['body', 'content'];

    /** @var array<string, array<string, array{default: string, kind: string, values: list<string>}>> memo by component */
    private static array $declared = [];

    /**
     * The props a view declares: name => ['default' => the PHP source of the default, 'kind' => text|bool|other, 'values' => [...]].
     *
     * @return array<string, array{default: string, kind: string, values: list<string>}>
     */
    public static function declared(string $component): array
    {
        return self::$declared[$component] ??= self::readProps(file_get_contents(__DIR__.'/../../resources/views/daisyblade/'.str_replace('.', '/', $component).'.blade.php'));
    }

    /** @return array<string, array{default: string, kind: string, values: list<string>}> */
    private static function readProps(string $source): array
    {
        if (preg_match('/@props\(\[(.*?)\]\)\s*(?:\n|$)/s', $source, $block) !== 1) {
            return [];
        }
        $props = [];
        foreach (self::entriesOf($block[1]) as [$name, $default, $comment]) {
            $props[$name] = ['default' => $default, 'kind' => self::kindOf($default), 'values' => self::valuesOf($name, $comment, $source)];
        }

        return $props;
    }

    /**
     * The entries of the array inside `@props([ ... ])`, as [name, source of the default, the `// comment` after it].
     * A scanner and not a regex: a default can hold commas, quotes and brackets (`config('a.b', 'c')`, `['x' => 1, 'y' => 2]`).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function entriesOf(string $inside): array
    {
        $entries = [];
        $length = strlen($inside);
        $position = 0;
        while ($position < $length) {
            if (preg_match("/\G\s*(?:\/\/[^\n]*+)?+\s*'([\w-]+)'\s*/", $inside, $key, 0, $position) !== 1) {
                break;
            }
            $position += strlen($key[0]);
            $default = '';
            if (($inside[$position] ?? '') === '=' && ($inside[$position + 1] ?? '') === '>') {
                $position += 2;
                $start = $position;
                $depth = 0;
                $quote = null;
                for (; $position < $length; $position++) {
                    $character = $inside[$position];
                    if ($quote !== null) {
                        $quote = ($character === $quote && $inside[$position - 1] !== '\\') ? null : $quote;
                    } elseif ($character === "'" || $character === '"') {
                        $quote = $character;
                    } elseif (str_contains('([', $character)) {
                        $depth++;
                    } elseif (str_contains(')]', $character)) {
                        $depth--;
                    } elseif ($character === ',' && $depth === 0) {
                        break;
                    }
                }
                $default = trim(preg_replace('/\/\/[^\n]*/', '', substr($inside, $start, $position - $start)));
            }
            $position++;   // the comma
            $comment = preg_match('/\G[ \t]*\/\/[ \t]*([^\n]*)/', $inside, $found, 0, $position) === 1 ? $found[1] : '';
            if ($default === '' && $comment !== '' && $position > 0 && ! str_contains(substr($inside, 0, $position), '=>')) {
                $comment = '';
            }
            $entries[] = [$key[1], $default, $comment];
        }

        return $entries;
    }

    private static function kindOf(string $default): string
    {
        return match (true) {
            in_array($default, ['true', 'false'], true) => 'bool',
            preg_match('/^-?\d+$/', $default) === 1 => 'number',
            // A text, or something that gives one: a config value whose fallback is a text, the locale of the application.
            $default === 'null' => 'nullable',
            preg_match("/^(''|\"\"|'[^']*'|\"[^\"]*\")\$/", $default) === 1, preg_match("/^config\\([^,]+,\\s*'[^']*'\\)\$/", $default) === 1, str_starts_with($default, 'str_replace(') => 'text',
            default => 'other',
        };
    }

    /** The closed list of values of a prop: the arms of `match($prop)` in the view, plus the ones its comment lists (`xs | sm | md`). */
    private static function valuesOf(string $prop, string $comment, string $source): array
    {
        $values = [];
        if (preg_match('/match\s*\(\s*\$'.preg_quote($prop, '/').'\s*\)\s*\{(.*?)\n\s*\}\s*;/s', $source, $match) === 1) {
            preg_match_all("/^\\s*((?:'[^']*'\\s*,\\s*)*'[^']*')\\s*=>/m", $match[1], $arms);
            foreach ($arms[1] as $labels) {
                preg_match_all("/'([^']*)'/", $labels, $each);
                array_push($values, ...$each[1]);
            }
        }
        if (str_contains($comment, '|') && preg_match('/^[\w\s|.\/-]+$/', $comment) === 1) {
            array_push($values, ...array_map('trim', explode('|', $comment)));
        }

        return array_values(array_unique(array_filter($values, fn ($value) => $value !== '')));
    }

    /**
     * The variants of one example: label => [the props to paint with, the prop each one is about].
     *
     * @return array<string, array{props: array, prop: string, kind: string, guess: bool}>  `guess`: the prop defaults to `null`, so a text is only a guess of its type (it may be a list, a model...)
     */
    public static function of(Example $example): array
    {
        $variants = [];
        $passed = $example->propsPassed();
        foreach (self::declared($example->component) as $name => $prop) {
            if (in_array($prop['kind'], ['text', 'nullable'], true)) {
                // HTML that the documentation declares trusted gets no hostile text: it is markup on purpose.
                if (! in_array($name, self::TRUSTED_HTML, true)) {
                    $variants["{$name} hostile"] = ['props' => [$name => Example::HOSTILE], 'prop' => $name, 'kind' => 'hostile', 'guess' => $prop['kind'] === 'nullable'];
                }
                $variants["{$name} marked"] = ['props' => [$name => self::MARKED], 'prop' => $name, 'kind' => 'marked', 'guess' => $prop['kind'] === 'nullable'];
            }
            if ($prop['kind'] === 'number') {
                // A number the example does not give: one more than the default (a page size, a maximum, a duration).
                $current = array_key_exists($name, $passed) ? (int) $passed[$name] : (int) $prop['default'];
                $variants["{$name} = ".($current + 1)] = ['props' => [$name => $current + 1], 'prop' => $name, 'kind' => 'number', 'guess' => false];
            }
            if ($prop['kind'] === 'bool') {
                $current = array_key_exists($name, $passed) ? (bool) $passed[$name] : $prop['default'] === 'true';
                $variants["{$name} ".($current ? 'off' : 'on')] = ['props' => [$name => ! $current], 'prop' => $name, 'kind' => 'flipped', 'guess' => false];
            }
            foreach ($prop['values'] as $value) {
                $variants["{$name} = {$value}"] = ['props' => [$name => $value], 'prop' => $name, 'kind' => 'value', 'guess' => false];
            }
        }

        return $variants;
    }

    /** The props that the hand-written examples of `$component` pass, plus the ones its variants touch. @return list<string> */
    public static function exercised(string $component, iterable $examples): array
    {
        $names = [];
        foreach ($examples as $example) {
            if ($example->component !== $component) {
                continue;
            }
            array_push($names, ...array_keys($example->propsPassed()));
            array_push($names, ...array_column(self::of($example), 'prop'));
        }

        return array_values(array_unique($names));
    }
}
