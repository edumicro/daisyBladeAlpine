<?php

/*
 * A component that measures its element must work when it STARTS hidden (a closed tab, modal, collapse or wizard step, where every size
 * is 0 px) and is shown later. They all learn their size through resources/js/observeSize.js. Each test mounts the component hidden,
 * shows it, and checks that it works. Two ways of hiding, because browsers hide in more than one way.
 *
 * The signature is the one that needed the helper. The map (MapLibre only fires `load`, and so frames the points, when its container
 * has a size) and the flowchart (it watches its view with the same helper) cope with it; their tests keep it that way.
 */

/** The two ways of hiding something that the tests use: [markup that opens, markup that closes, what to click]. */
dataset('ways of hiding', [
    'display none (x-show, a tab or a modal)' => [fn (string $inside) => '<div x-data="{ open: false }"><button id="show" @click="open = true">show</button><div x-show="open" style="display:none">'.$inside.'</div></div>', "document.querySelector('#show').click()"],
    'a closed <details> (a collapse)' => [fn (string $inside) => '<details id="d"><summary>show</summary>'.$inside.'</details>', "document.querySelector('#d').open = true"],
]);

it('signature: a pad that starts hidden has a size once it is shown, and can be signed', function (Closure $hide, string $show) {
    $page = visit(browserPage('hidden-signature-'.md5($show), PAD_SCRIPT.$hide('<form><x-dbl::form.signature name="sig" /></form>'), ['modules' => ['signature']]));
    pause($page, 0.4);

    $page->script($show);
    readPage($page, "document.querySelector('canvas').width", fn ($width) => $width > 0);
    drawStroke($page);
    $value = readPage($page, "document.querySelector('input[name=sig]').value", fn ($v) => str_starts_with((string) $v, 'data:image/png'));

    expect($page->script("document.querySelector('canvas').width"))->toBeGreaterThan(0)
        ->and($value)->toStartWith('data:image/png');
})->with('ways of hiding');

it('signature: the strokes stay when the pad is hidden again and shown again', function () {
    $page = visit(browserPage('hidden-signature-again', PAD_SCRIPT.'<div x-data="{ open: true }"><button id="toggle" @click="open = !open">toggle</button><div x-show="open"><form><x-dbl::form.signature name="sig" /></form></div></div>', ['modules' => ['signature']]));
    pause($page, 0.4);
    drawStroke($page);
    readPage($page, "document.querySelector('input[name=sig]').value", fn ($v) => str_starts_with((string) $v, 'data:image/png'));

    $page->script("document.querySelector('#toggle').click()");
    pause($page, 0.3);
    $page->script("document.querySelector('#toggle').click()");
    pause($page, 0.4);

    // The field still holds the signature and the pad still knows it is signed (Clear is enabled).
    expect($page->script("document.querySelector('input[name=sig]').value"))->toStartWith('data:image/png')
        ->and($page->script("document.querySelector('button.btn-error').disabled"))->toBeFalse();
});

it('map: a map with fit that starts hidden frames all its points once it is shown', function (Closure $hide, string $show) {
    $page = visit(browserPage('hidden-map-'.md5($show), $hide('<x-dbl::display.map :center="[0, 0]" :zoom="2" fit :layers="[[\'id\' => \'a\', \'label\' => \'A\', \'load_url\' => \'/geo/a\'], [\'id\' => \'b\', \'label\' => \'B\', \'load_url\' => \'/geo/b\']]" />'), ['modules' => ['map']]));
    pause($page, 1.0);

    $page->script($show);
    // Points of the two layers: (-0.37, 39.47), (-0.36, 39.48) and (-0.2, 39.4). A map that framed them at 0 px stays at zoom 2.
    $framed = readPage($page, <<<'JS'
        (() => {
            const map = window.__maps?.[0]
            if (!map || map.getZoom() < 6) return null
            const bounds = map.getBounds()
            return [[-0.37, 39.47], [-0.36, 39.48], [-0.2, 39.4]].every(([lng, lat]) => bounds.contains([lng, lat]))
        })()
        JS);

    expect($framed)->toBeTrue();
})->with('ways of hiding');

it('flowchart: a chart with fit that starts hidden is framed in its view once it is shown', function (Closure $hide, string $show) {
    $page = visit(browserPage('hidden-flowchart-'.md5($show), $hide('<x-dbl::display.flowchart name="f" fit :nodes="[[\'id\' => \'a\', \'label\' => \'A\'], [\'id\' => \'b\', \'label\' => \'B\'], [\'id\' => \'c\', \'label\' => \'C\']]" :edges="[[\'from\' => \'a\', \'to\' => \'b\'], [\'from\' => \'b\', \'to\' => \'c\']]" />'), ['modules' => ['flowchart']]));
    pause($page, 0.5);

    $page->script($show);
    $fits = readPage($page, <<<'JS'
        (() => {
            const viewport = document.querySelector('[x-ref=viewport]') ?? document.querySelector('.overflow-auto')
            const chart = Alpine.$data(viewport.closest('[x-data]'))
            if (!viewport.clientWidth || !chart.size.w) return null
            return chart.scale > 0 && chart.size.w * chart.scale <= viewport.clientWidth + 1 && chart.size.h * chart.scale <= viewport.clientHeight + 1
        })()
        JS);

    expect($fits)->toBeTrue();
})->with('ways of hiding');
