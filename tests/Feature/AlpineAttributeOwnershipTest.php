<?php

/**
 * The element of a view that receives `$attributes` belongs to the caller: whatever they write on the tag
 * (`x-on:input`, `:class`, `x-show`, `x-ref`, `@keydown.escape.window`...) goes onto that element. If the view had written
 * the same Alpine attribute there, the HTML would have it twice, and the browser keeps only the FIRST of two equal
 * attributes: one of the two silently disappears, depending on which comes first in the view. It happened to `form.select`,
 * the searchable select, the repeater, the modal, the alert, the toast, the upload, the signature, the data-table, the
 * flowchart, the map, the sidebar, the `rows` of `attrs` and the `lang` of `layout.auth`.
 *
 * The rule, for EVERY view of the package:
 *
 *   1. In the tag that holds `$attributes`, every default (type, name, class, disabled, href...) goes through
 *      `$attributes->merge([...])`, `->class([...])` or `->style([...])`: Laravel adds the `class` and, for the rest, the caller
 *      wins. Nothing else is written on that tag: no literal attribute, no `@if` that writes one, no directive of Alpine.
 *   2. So the scope of a component (`x-data`) is never on that element. It is on an element of its own INSIDE it (the root of a
 *      component) or BESIDE it (a control), and it never goes around the control of the caller, or their `x-ref` and `$root`
 *      would be registered in our scope and not in theirs. The only controls that sit inside the scope are the ones that ARE the
 *      component (upload zone, signature pad, editor box).
 */

/** The Alpine attributes written on a tag (`x-...`, `@event`, `:binding`), without the values and without what is inside `{{ }}`. */
function alpineAttributesOf(string $tag): array
{
    $bare = preg_replace('/\{\{--.*?--\}\}|\{\{.*?\}\}|\{!!.*?!!\}/s', '', $tag);
    $bare = preg_replace('/"[^"]*"|\'[^\']*\'/', '""', $bare);

    // They are written with `=`: `@disabled($x)` and `@checked($x)` are Blade directives, not Alpine, and have no `=`.
    preg_match_all('/(?<![\w@$:-])(x-[\w:.-]+|@[\w:.-]+|:[\w:.-]+)\s*=/', $bare, $found);

    return $found[1];
}

/** True when the tag is one that hands the caller's attributes on: it holds `$attributes` or a bag taken from it (`$boxAttributes`). */
function receivesAttributes(string $tag): bool
{
    return preg_match('/\$\w*[aA]ttributes\b/', $tag) === 1;
}

/**
 * What is written on a tag besides the echoes of the attribute bag: the tag name and `>` are not attributes, nor is the
 * `{{ $attributes->merge([...]) }}`. Whatever remains is an attribute written by hand (or a directive that writes one).
 */
function attributesWrittenByHand(string $tag): string
{
    $bare = preg_replace('/\{\{--.*?--\}\}/s', '', $tag);
    $bare = preg_replace('/\{\{(?:(?!\}\}).)*?\$\w*[aA]ttributes\b.*?\}\}/s', '', $bare);
    $bare = preg_replace('/^<[\w:.-]+|\/?>$/', '', trim($bare));

    return trim(preg_replace('/\s+/', ' ', $bare));
}

it('writes nothing by hand on an element that receives $attributes: every default goes through $attributes->merge() or ->class()', function () {
    $offenders = [];
    $tagsWithAttributes = 0;

    foreach (viewsOfThePackage() as $path => $source) {
        foreach (openingTagsOf($source) as [$name, $tag, $line]) {
            // `<x-...>` is a Blade component: its `:prop` is PHP, and `$attributes` there is only handed on.
            if (str_starts_with($name, 'x-') || ! receivesAttributes($tag)) {
                continue;
            }

            $tagsWithAttributes++;
            $byHand = attributesWrittenByHand($tag);

            if ($byHand !== '') {
                $offenders["{$path}:{$line} <{$name}>"] = $byHand;
            }
        }
    }

    // The scan has to find the elements: if the scanner broke, an empty list of offenders would mean nothing.
    expect($tagsWithAttributes)->toBeGreaterThan(50)
        ->and($offenders)->toBe([]);
});

/**
 * The elements of a view that sit INSIDE an element with `x-data`, as [path:line <tag>] for every tag that receives `$attributes`.
 * It walks the opening and closing tags with a stack; void elements and `<x-...>` components are not containers.
 */
function attributeReceiversInsideAScope(string $source): array
{
    $voidElements = ['input', 'img', 'br', 'hr', 'meta', 'link', 'source', 'path', 'circle', 'rect'];
    // A Blade comment can talk about tags (`<button x-data>`): blank it out, keeping every position and line where it was.
    $source = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($comment) => preg_replace('/[^\n]/', ' ', $comment[0]), $source);
    $inside = [];
    $stack = [];   // [tag name, has x-data]

    // Every tag in order: opening ones come with their full source from openingTagsOf(); closing ones are found by position.
    $events = [];
    foreach (openingTagsOf($source) as [$name, $tag, $line, $position]) {
        $events[$position] = ['open', $name, $tag, $line];
    }
    preg_match_all('/<\/([a-zA-Z][\w:.-]*)\s*>/', $source, $closings, PREG_OFFSET_CAPTURE);
    foreach ($closings[1] as [$name, $position]) {
        $events[$position - 2] = ['close', $name];
    }
    ksort($events);

    foreach ($events as $event) {
        if ($event[0] === 'close') {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i][0] === $event[1]) {
                    array_splice($stack, $i);
                    break;
                }
            }

            continue;
        }

        [, $name, $tag, $line] = $event;
        $isBlade = str_starts_with($name, 'x-');

        // The carrier of the caller's `x-model` in the searchable select (`x-modelable`) is paired with a property of the scope
        // by design, so it has to be inside it.
        if (! $isBlade && receivesAttributes($tag) && ! str_contains($tag, 'x-modelable') && in_array(true, array_column($stack, 1), true)) {
            $inside[] = [$line, $name];
        }

        $selfClosing = str_ends_with(rtrim($tag), '/>') || in_array(strtolower($name), $voidElements, true);
        if (! $isBlade && ! $selfClosing) {
            $stack[] = [$name, in_array('x-data', alpineAttributesOf($tag), true) || preg_match('/\sx-data(\s|=|>)/', $tag) === 1];
        }
    }

    return $inside;
}

it('does not put an x-data around the element that receives $attributes, but in the controls that are the component itself', function () {
    // The upload zone, the signature pad and the editor box ARE the component: all their interface is driven by its scope, so
    // the scope has to enclose them. The caller reaches their data through events, not through x-ref.
    $theComponentItself = ['daisyblade/form/upload.blade.php', 'daisyblade/form/signature.blade.php', 'daisyblade/form/rich-text.blade.php'];

    $offenders = [];
    foreach (viewsOfThePackage() as $path => $source) {
        if (in_array($path, $theComponentItself, true)) {
            continue;
        }
        if ($found = attributeReceiversInsideAScope($source)) {
            $offenders[$path] = $found;
        }
    }

    expect($offenders)->toBe([]);
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
