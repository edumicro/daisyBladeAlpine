<?php

use Illuminate\Support\Facades\Blade;

/**
 * "Every attribute goes to each radio" turned one `id` into N equal ids: invalid HTML, and `document.getElementById`,
 * `aria-describedby` or a `<label for>` only ever reach the first radio.
 * (Chrome: `<x-dbl::form.radio id="kind" :options="[a, b]">` gave 2 elements with id="kind".)
 * The decision: the `id` of the caller goes to the first radio, and the others have none.
 */
it('does not repeat the id the caller writes on every radio of the group', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" id="kind" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    preg_match_all('/\sid="([^"]*)"/', $html, $ids);

    expect(count($ids[1]))->toBe(count(array_unique($ids[1])), 'duplicate ids: '.implode(', ', $ids[1]));
});

it('gives the id the caller writes to the first radio, and no id to the rest', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" id="kind" :options="[\'a\' => \'A\', \'b\' => \'B\', \'c\' => \'C\']" />');

    preg_match_all('/<input[^>]*type="radio"[^>]*>/s', $html, $radios);

    expect($radios[0])->toHaveCount(3)
        ->and($radios[0][0])->toContain('id="kind"')
        ->and($radios[0][1])->not->toContain('id=')
        ->and($radios[0][2])->not->toContain('id=');
});

it('still gives every radio the other attributes of the caller, such as class and x-model', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" id="kind" x-model="kind" class="mine" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    preg_match_all('/<input[^>]*type="radio"[^>]*>/s', $html, $radios);

    foreach ($radios[0] as $radio) {
        expect($radio)->toContain('x-model="kind"')->toContain('mine');
    }
});

it('writes no id at all when the caller writes none', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    expect($html)->not->toMatch('/<input[^>]*\sid=/s');
});
