<?php

/**
 * Cause 8: every visible text of the package goes through `__()`, or it cannot be translated. "Tab 1" and "Slide 1" were
 * written in English in the middle of a view in Spanish.
 *
 * For every view: no text between tags, and no `alt`, `title`, `aria-label` or `placeholder`, written as a literal.
 * A text that comes from data (`{{ $label }}`) or from the translator (`{{ __('Close') }}`) is fine.
 */

/**
 * The words written by hand INSIDE an echo (`{{ $alt ?? 'Slide ' . $n }}`): a quoted string with letters that is not the argument of
 * `__()`. A string that is a key of an array (`$item['label']`) or a bare identifier is not text.
 */
function literalWordsInEchoes(string $text): array
{
    $words = [];
    $literal = '(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';
    preg_match_all('/\{\{(.*?)\}\}/s', $text, $echoes);

    foreach ($echoes[1] as $expression) {
        // Out go the strings that are not text: the argument of a translator or of a helper that looks something up (`__`, `config`,
        // `route`, `$t`...), the key of an array (`$row['name']`, `'number' => 1`).
        $expression = preg_replace('/(?:\b(?:__|trans|trans_choice|config|route|asset|url|old|str_replace)|\$t)\(\s*'.$literal.'/', ' ', $expression);
        $expression = preg_replace('/\[\s*'.$literal.'\s*\]|'.$literal.'\s*=>/', ' ', $expression);

        preg_match_all('/'.$literal.'/u', $expression, $literals);
        foreach ($literals[0] as $quoted) {
            if (preg_match('/\p{L}{2,}/u', $quoted) === 1) {
                $words[] = trim($quoted, '\'"');
            }
        }
    }

    return $words;
}

/** The text of a view that sits between tags, as [line, text], with everything that is not literal text taken out. */
function literalTextsOf(string $source): array
{
    // What is not text: comments, PHP blocks, scripts and styles (they have their own language). Blanked out, keeping the lines.
    $blank = fn (string $pattern, string $text) => preg_replace_callback($pattern, fn ($found) => preg_replace('/[^\n]/', ' ', $found[0]), $text);

    $source = $blank('/\{\{--.*?--\}\}/s', $source);
    $source = $blank('/<!--.*?-->/s', $source);
    $source = $blank('/@php\b.*?@endphp/s', $source);
    $source = $blank('/<(script|style)\b.*?<\/\1>/s', $source);

    // Tag ranges: the opening ones with `openingTagsOf()` of tests/Pest.php, the closing ones with a pattern.
    $ranges = [];
    foreach (openingTagsOf($source) as [, $tag, , $start]) {
        $ranges[$start] = $start + strlen($tag);
    }
    preg_match_all('/<\/[\w:.-]+\s*>/', $source, $closings, PREG_OFFSET_CAPTURE);
    foreach ($closings[0] as [$closing, $start]) {
        $ranges[$start] = $start + strlen($closing);
    }
    ksort($ranges);

    $texts = [];
    $position = 0;
    foreach ($ranges as $start => $end) {
        if ($start >= $position) {
            $texts[] = [$position, substr($source, $position, $start - $position)];
        }
        $position = max($position, $end);
    }
    $texts[] = [$position, substr($source, $position)];

    $found = [];
    foreach ($texts as [$offset, $text]) {
        // A text written by hand inside an echo is text too.
        foreach (literalWordsInEchoes($text) as $word) {
            $found[] = [substr_count($source, "\n", 0, $offset) + 1, $word];
        }
        // Out go the entities (`&rarr;`), the doctype, the echoes and the directives (`@if($x)`, `@error('name')`...): they are not text.
        $text = preg_replace('/&#?\w+;|<!DOCTYPE[^>]*>/i', ' ', $text);
        $text = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $text);
        $text = preg_replace('/@\w+\s*(\((?:[^()]++|(?1))*\))?/s', ' ', $text);

        if (preg_match('/\p{L}{2,}/u', $text) === 1) {
            $found[] = [substr_count($source, "\n", 0, $offset) + 1, trim(preg_replace('/\s+/', ' ', $text))];
        }
    }

    return $found;
}

/** The `alt`, `title`, `aria-label` and `placeholder` of a view whose value is written by hand, as [line, attribute, value]. */
function literalAttributesOf(string $source): array
{
    $source = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($found) => preg_replace('/[^\n]/', ' ', $found[0]), $source);
    $found = [];

    foreach (openingTagsOf($source) as [, $tag, $line]) {
        // The value between quotes, minus the echoes: if letters are left, they were written by hand.
        if (preg_match_all('/\s(alt|title|aria-label|placeholder)\s*=\s*"([^"]*)"/i', $tag, $attributes, PREG_SET_ORDER)) {
            foreach ($attributes as [, $name, $value]) {
                $byHand = preg_match('/\p{L}{2,}/u', preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $value)) === 1;
                if ($byHand || literalWordsInEchoes($value) !== []) {
                    $found[] = [$line, $name, $value];
                }
            }
        }
    }

    return $found;
}

it('has no visible text between tags without __()', function () {
    // The names of the types of the JSON editor are not words to translate but the JSON types themselves, as `true` and `false`.
    $notWords = ['daisyblade/form/kv-editor.blade.php' => ['true', 'false', 'num', 'str', 'bool']];

    $offenders = [];
    foreach (viewsOfThePackage() as $path => $source) {
        foreach (literalTextsOf($source) as [$line, $text]) {
            if (! in_array($text, $notWords[$path] ?? [], true)) {
                $offenders["{$path}:{$line}"] = $text;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('has no alt, title, aria-label or placeholder written by hand', function () {
    $offenders = [];
    foreach (viewsOfThePackage() as $path => $source) {
        foreach (literalAttributesOf($source) as [$line, $name, $value]) {
            $offenders["{$path}:{$line}"] = "{$name}=\"{$value}\"";
        }
    }

    expect($offenders)->toBe([]);
});

it('catches a text and an attribute written by hand (the scanner itself)', function () {
    $view = <<<'BLADE'
        <div title="Close" aria-label="{{ __('Close') }}" placeholder="{{ $hint }}">
            Hello {{ $name }}
            <p>{{ __('Fine') }}</p>
            <img alt="A photo" src="{{ $src }}">
            @if($x > 1) @endif
        </div>
        BLADE;

    expect(array_column(literalTextsOf($view), 1))->toBe(['Hello'])
        ->and(array_column(literalAttributesOf($view), 1))->toBe(['title', 'alt']);

    $inAnEcho = '<img alt="{{ $alt ?? \'Slide \' . $n }}" title="{{ __(\'Close\') }}"> {{ $label ?? \'Tab \' . $n }} {{ $row[\'name\'] }}';
    expect(array_column(literalAttributesOf($inAnEcho), 1))->toBe(['alt'])
        ->and(array_column(literalTextsOf($inAnEcho), 1))->toBe(['Tab ']);
});
