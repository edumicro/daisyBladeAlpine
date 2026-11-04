<?php

namespace Edumicro\DaisyBlade\Tests\Catalog;

/**
 * Reads the HTML that a component PAINTED, which is what the user's browser gets, instead of the Blade source that wrote it.
 *
 * A scan of the source has a hole for every way of writing the same thing (single quotes, `@props`, a variable that is
 * not called `$attributes`...). The painted HTML has one form only, so the architecture tests of tests/Feature/Catalog read it
 * with this class: the tags with their attributes (in the order and as many times as they were written), and the visible texts.
 */
final class RenderedHtml
{
    /** Attributes whose value is a text that a person reads or hears (a screen reader, a tooltip). */
    public const TEXT_ATTRIBUTES = ['alt', 'title', 'aria-label', 'aria-description', 'aria-placeholder', 'aria-roledescription', 'placeholder', 'data-tip', 'label'];

    /**
     * Alpine attributes whose value is JavaScript that can write a text in the page: `:title="open ? 'Close' : 'Open'"`.
     * (`x-text` is not here: its strings are mostly comparisons such as `item.type === 'group'`.)
     */
    public const ALPINE_TEXT_ATTRIBUTES = [':title', ':aria-label', ':placeholder', ':alt', ':data-tip', ':label', 'x-bind:title', 'x-bind:aria-label', 'x-bind:placeholder'];

    /** The HTML without what is not markup of the page: comments, the doctype, scripts and styles (they have their own language). */
    public static function withoutScriptsAndComments(string $html): string
    {
        return preg_replace(['/<!--.*?-->/s', '/<script\b.*?<\/script>/is', '/<style\b.*?<\/style>/is', '/<!doctype[^>]*>/i'], '', $html);
    }

    /**
     * Every opening tag, as ['name' => 'input', 'attributes' => [['type', 'text'], ['x-model', 'a'], ...]].
     * An attribute without value (`disabled`) has `null`. The same name twice is listed twice: that is what is looked for.
     *
     * @return list<array{name: string, attributes: list<array{0: string, 1: ?string}>}>
     */
    public static function tags(string $html): array
    {
        $tags = [];
        foreach (self::tokens($html) as $token) {
            if ($token['type'] === 'tag') {
                $tags[] = ['name' => $token['name'], 'attributes' => $token['attributes']];
            }
        }

        return $tags;
    }

    /**
     * The words a person reads on the page, one per text node (the entities already decoded, the blanks trimmed).
     * A text with no letters at all (an arrow, a number, a symbol) is not included: there is nothing to translate.
     *
     * @return list<string>
     */
    public static function visibleTexts(string $html): array
    {
        $texts = [];
        foreach (self::tokens($html) as $token) {
            if ($token['type'] === 'text') {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($token['text'], ENT_QUOTES | ENT_HTML5)));
                if (preg_match('/\p{L}{2,}/u', $text) === 1) {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * The texts hidden in attributes (`alt`, `title`, `aria-label`, `placeholder`, `data-tip`, `label`, the `value` of a button),
     * as ['<img alt>' => 'text'] pairs: the tag and the attribute say where it is.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function textAttributes(string $html): array
    {
        $found = [];
        foreach (self::tags($html) as $tag) {
            $isButtonInput = $tag['name'] === 'input' && in_array(self::valueOf($tag, 'type'), ['submit', 'button', 'reset'], true);
            foreach ($tag['attributes'] as [$name, $value]) {
                $isText = in_array($name, self::TEXT_ATTRIBUTES, true) || ($name === 'value' && $isButtonInput);
                if ($isText && $value !== null && preg_match('/\p{L}{2,}/u', $value) === 1) {
                    $found[] = ["<{$tag['name']} {$name}>", html_entity_decode($value, ENT_QUOTES | ENT_HTML5)];
                }
            }
        }

        return $found;
    }

    /** The first value of attribute `$name` in `$tag` (null if it is missing or has no value). */
    public static function valueOf(array $tag, string $name): ?string
    {
        foreach ($tag['attributes'] as [$attribute, $value]) {
            if ($attribute === $name) {
                return $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    /** How many times `$name` is written in `$tag`. */
    public static function countOf(array $tag, string $name): int
    {
        return count(array_filter($tag['attributes'], fn ($attribute) => $attribute[0] === $name));
    }

    /** @return list<array<string, mixed>> the tags and the texts between them, in order. */
    private static function tokens(string $html): array
    {
        $html = self::withoutScriptsAndComments($html);
        $attribute = '[^\s=>\/"\']+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+))?';
        $pattern = '/<([a-zA-Z][\w:-]*)((?:\s+'.$attribute.')*)\s*\/?>|<\/[\w:-]+\s*>/';

        $tokens = [];
        $position = 0;
        preg_match_all($pattern, $html, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            [$whole, $start] = $match[0];
            $tokens[] = ['type' => 'text', 'text' => substr($html, $position, $start - $position)];
            $tokens[] = isset($match[1]) && $match[1][1] !== -1
                ? ['type' => 'tag', 'name' => strtolower($match[1][0]), 'attributes' => self::attributesOf($match[2][0] ?? '')]
                : ['type' => 'closing'];
            $position = $start + strlen($whole);
        }
        $tokens[] = ['type' => 'text', 'text' => substr($html, $position)];

        return $tokens;
    }

    /** @return list<array{0: string, 1: ?string}> */
    private static function attributesOf(string $inside): array
    {
        preg_match_all('/([^\s=>\/"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', $inside, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        return array_map(fn ($match) => [strtolower($match[1]), $match[2] ?? $match[3] ?? $match[4] ?? null], $found);
    }
}
