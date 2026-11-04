<?php

use Illuminate\Support\Facades\Blade;

/**
 * 2.4.1. `required` is a prop of every form control: form.checkbox writes it on its <input>, but form.toggle and
 * form.radio only had the prop and never wrote it, so the browser let the form go with the toggle off or no radio chosen.
 */
it('writes required on the toggle input, as the checkbox does', function () {
    $html = Blade::render('<x-dbl::form.toggle name="on" label="On" required />');

    expect($html)->toMatch('/<input[^>]*type="checkbox"[^>]*\srequired(?:=|[\s>\/])/s');
});

it('writes required on every radio of a required group', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" :options="[\'a\' => \'A\', \'b\' => \'B\']" required />');

    preg_match_all('/<input[^>]*type="radio"[^>]*>/s', $html, $radios);

    expect($radios[0])->toHaveCount(2);
    foreach ($radios[0] as $radio) {
        expect($radio)->toMatch('/\srequired(?:=|[\s>\/])/');
    }
});

it('does not write required on a toggle or a radio that is not required', function () {
    $html = Blade::render('<x-dbl::form.toggle name="on" /><x-dbl::form.radio name="kind" :options="[\'a\' => \'A\']" />');

    expect($html)->not->toContain('required');
});

/** The checkbox takes its `id` from `name`; the toggle did not. */
it('gives the toggle the id of its name, and the id the caller writes wins and is written once', function () {
    expect(Blade::render('<x-dbl::form.toggle name="on" label="On" />'))->toMatch('/<input[^>]*type="checkbox"[^>]*\sid="on"/s');

    $html = Blade::render('<x-dbl::form.toggle name="on" id="mine" />');
    expect($html)->toContain('id="mine"')->not->toContain('id="on"');
    expect(substr_count($html, ' id='))->toBe(1);
});

/** "Every attribute goes to each radio" turned one `id` into N equal ids: invalid HTML. */
it('puts the id the caller writes on the first radio only, and the other attributes on every radio', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" id="kind" data-x="1" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    preg_match_all('/\sid="([^"]*)"/', $html, $ids);

    expect($ids[1])->toBe(['kind']);
    expect(substr_count($html, 'data-x="1"'))->toBe(2);
});
