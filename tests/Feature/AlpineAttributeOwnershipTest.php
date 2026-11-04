<?php

/**
 * The element of a view that receives `$attributes` belongs to the caller: whatever they write on the tag
 * (`x-on:input`, `:class`, `x-show`, `@keydown.escape.window`...) goes onto that element. If the view had written the
 * same Alpine attribute there, the HTML would have it twice, and the browser keeps only the FIRST of two equal attributes:
 * one of the two silently disappears, depending on which comes first in the view. It happened to `form.select`
 * (`x-on:change`, `:disabled`), to the searchable select (`@input`, `@focus`) and then to the repeater, the modal, the alert,
 * the toast, the upload, the signature, the data-table, the flowchart, the map and the sidebar.
 *
 * The rule, for every view of the package: an element that receives `$attributes` carries NO Alpine attribute of the
 * component except `x-data` (the component itself: nobody can write another scope there and still have the component).
 * What the component needs (a listener of the window, a class that follows a state, an `x-show`) goes on an element inside,
 * which can reach the root with `$root` / `$el.parentElement`; or the listener goes on a child, because events rise.
 */

/** The source of every view of the package, by path. */
function viewsOfThePackage(): array
{
    $views = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php')) {
            $views[str_replace(realpath(__DIR__.'/../../resources/views').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($views);

    return $views;
}

/**
 * The opening tags of a view that are plain HTML (not `<x-...>` Blade components), as [tag name, source of the tag, line].
 * A tag ends at the first `>` that is not inside a quoted value, a `{{ }}` or the parentheses of a Blade directive.
 */
function openingTagsOf(string $source): array
{
    $tags = [];
    $length = strlen($source);
    $offset = 0;

    while (preg_match('/<([a-zA-Z][\w:.-]*)/', $source, $found, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $start = $found[0][1];
        $position = $start + strlen($found[0][0]);
        $quote = null;

        while ($position < $length) {
            $character = $source[$position];
            $rest = substr($source, $position, 4);

            if (str_starts_with($rest, '{{--') || str_starts_with($rest, '{{') || str_starts_with($rest, '{!!')) {
                // A comment, an echo: skip to its end, whatever it holds.
                $end = str_starts_with($rest, '{{--') ? '--}}' : (str_starts_with($rest, '{!!') ? '!!}' : '}}');
                $position = strpos($source, $end, $position) + strlen($end);

                continue;
            }

            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '@' && preg_match('/\G@[a-zA-Z]+\s*\(/', $source, $directive, 0, $position) === 1) {
                // A Blade directive with arguments (`@if($a > 1)`, `@disabled($x)`): skip its parentheses.
                $depth = 0;
                $position += strlen($directive[0]) - 1;
                do {
                    $depth += $source[$position] === '(' ? 1 : ($source[$position] === ')' ? -1 : 0);
                    $position++;
                } while ($depth > 0 && $position < $length);

                continue;
            } elseif ($character === '>') {
                break;
            }

            $position++;
        }

        $tags[] = [$found[1][0], substr($source, $start, $position - $start + 1), substr_count($source, "\n", 0, $start) + 1];
        $offset = $start + strlen($found[0][0]);
    }

    return $tags;
}

/** The Alpine attributes written on a tag (`x-...`, `@event`, `:binding`), without the values and without what is inside `{{ }}`. */
function alpineAttributesOf(string $tag): array
{
    $bare = preg_replace('/\{\{--.*?--\}\}|\{\{.*?\}\}|\{!!.*?!!\}/s', '', $tag);
    $bare = preg_replace('/"[^"]*"|\'[^\']*\'/', '""', $bare);

    // They are written with `=`: `@disabled($x)` and `@checked($x)` are Blade directives, not Alpine, and have no `=`.
    preg_match_all('/(?<![\w@$:-])(x-[\w:.-]+|@[\w:.-]+|:[\w:.-]+)\s*=/', $bare, $found);

    return $found[1];
}

it('has no Alpine attribute of the component on an element that receives $attributes, but x-data', function () {
    $offenders = [];
    $tagsWithAttributes = 0;

    foreach (viewsOfThePackage() as $path => $source) {
        foreach (openingTagsOf($source) as [$name, $tag, $line]) {
            // `<x-...>` is a Blade component: its `:prop` is PHP, and `$attributes` there is only handed on.
            if (str_starts_with($name, 'x-') || ! str_contains($tag, '$attributes')) {
                continue;
            }

            $tagsWithAttributes++;
            $own = array_values(array_diff(alpineAttributesOf($tag), ['x-data']));

            if ($own !== []) {
                $offenders["{$path}:{$line} <{$name}>"] = $own;
            }
        }
    }

    // The scan has to find the elements: if the scanner broke, an empty list of offenders would mean nothing.
    expect($tagsWithAttributes)->toBeGreaterThan(50)
        ->and($offenders)->toBe([]);
});

it('catches the attribute twice when a view writes one next to $attributes (the scanner itself)', function () {
    $view = <<<'BLADE'
        <div x-data="a()" @dbl-x.window="go()" {{ $attributes->merge(['class' => 'a > b']) }} :class="open && 'on'" @if($c > 1) x-show="s" @endif>
            <p x-show="inner">fine: it does not receive the attributes</p>
            <input {{ $attributes }} @disabled($d) @checked($e) />
        </div>
        BLADE;

    $report = [];
    foreach (openingTagsOf($view) as [$name, $tag]) {
        if (str_contains($tag, '$attributes')) {
            $report[$name] = array_values(array_diff(alpineAttributesOf($tag), ['x-data']));
        }
    }

    expect($report)->toBe(['div' => ['@dbl-x.window', ':class', 'x-show'], 'input' => []]);
});
