<?php

use Illuminate\Support\Facades\Blade;

/**
 * Cause 5: output that is not escaped. node-graph wrote `{$status}` into HTML it built by hand, chat-bubble printed the message
 * of a chat with `{!! !!}` (a message is text from other people), and hero wrote `url({{ $image }})` without quotes.
 *
 * Two rules for every view of the package, so the next one cannot slip in:
 *   1. `{!! ... !!}` (output without escaping) only where the list below says so, each one with its reason;
 *   2. no CSS `url(` without quotes around its address.
 */

/** Every `{!! !!}` of the views, as 'path' => [expression, ...]. */
function unescapedOutputsOfTheViews(): array
{
    $outputs = [];
    foreach (viewsOfThePackage() as $path => $source) {
        $withoutComments = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        if (preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $withoutComments, $found)) {
            $outputs[str_replace('daisyblade/', '', $path)] = $found[1];
        }
    }

    return $outputs;
}

it('writes {!! !!} only in the places that are on the list, each with its reason', function () {
    $allowed = [
        // HTML that the DEVELOPER writes in the data of the component (documented as HTML), not the text of a user:
        'navigation/tabs.blade.php'     => ['$item[\'content\'] ?? \'\''],     // the content of a tab
        'display/accordion.blade.php'   => ['$item[\'content\']'],          // the content of a panel
        'display/collapse.blade.php'    => ['$content'],                    // the content of the collapse
        'display/card.blade.php'        => ['$body'],                       // the body of the card
        'display/timeline.blade.php'    => ['$item[\'content\']'],          // the content of an event
        'display/carousel.blade.php'    => ['$item[\'content\']'],          // the content of a slide
        // HTML that the view itself builds in PHP, escaping every piece of text (htmlspecialchars / e()):
        'display/node-graph.blade.php'  => ['$renderNode($root)'],
        'display/tree.blade.php'        => ['$renderTree($items)'],
    ];

    ksort($allowed);

    expect(unescapedOutputsOfTheViews())->toBe($allowed);
});

it('never writes the address of a CSS url() without quotes', function () {
    $offenders = [];
    foreach (viewsOfThePackage() as $path => $source) {
        // `url(` that is the PHP function (`{{ url('/') }}`, `->url(`, `$url(`) is not CSS: it is preceded by a word character, `>` or `$`.
        if (preg_match_all('/(?<![\w>$])url\((?![\'"])/', preg_replace('/\{\{--.*?--\}\}/s', '', $source), $found)) {
            $offenders[$path] = count($found[0]);
        }
    }

    expect($offenders)->toBe([]);
});

it('node-graph escapes the status of a node', function () {
    $html = Blade::render('<x-dbl::display.node-graph :nodes="[[\'id\' => 1, \'label\' => \'A\', \'status\' => \'<img src=x onerror=alert(1)>\']]" />');

    expect($html)->not->toContain('<img src=x')->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('chat-bubble prints the message as text, never as HTML', function () {
    $html = Blade::render('<x-dbl::display.chat-bubble message="<script>alert(1)</script>" />');

    expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('hero puts the image inside quotes in url(), and an address with a quote or a parenthesis cannot get out of it', function () {
    $plain = Blade::render('<x-dbl::layout.hero bg-image="/img/a.jpg" />');
    $nasty = Blade::render('<x-dbl::layout.hero :bg-image="$image" />', ['image' => "/a.jpg'); background: red; x: url('"]);

    expect($plain)->toContain("background-image: url(&#039;/img/a.jpg&#039;)");
    // The quote of the address is escaped with a backslash for CSS: the text `\'` stays inside the quoted address.
    expect($nasty)->toContain("url(&#039;/a.jpg\\&#039;); background: red; x: url(\\&#039;&#039;)");
});

it('hero keeps the style of the caller next to its image', function () {
    $html = Blade::render('<x-dbl::layout.hero bg-image="/a.jpg" style="color: red" />');

    expect($html)->toContain('color: red')->toContain('background-image');
});
