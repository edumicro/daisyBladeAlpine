<?php

use Illuminate\Support\Facades\Blade;

/**
 * `required` is a prop of every form control, and the guide says every attribute of a control goes to the
 * control. form.checkbox writes `required` on its <input>; form.toggle and form.radio only painted the asterisk (the
 * toggle not even that), so the browser let the form go with the toggle off or no radio chosen.
 * (Chrome: `form.checkValidity()` was true with a `required` toggle unchecked and a `required` radio group empty.)
 */
it('writes required on the toggle input, as the checkbox does', function () {
    $html = Blade::render('<x-dbl::form.toggle name="on" label="On" required />');

    expect($html)->toMatch('/<input[^>]*type="checkbox"[^>]*\srequired(?:=|[\s>\/])/s');
});

it('paints the asterisk on the label of a required toggle, as the checkbox does', function () {
    expect(Blade::render('<x-dbl::form.toggle name="on" label="On" required />'))->toContain('text-error ml-1">*</span>')
        ->and(Blade::render('<x-dbl::form.toggle name="on" label="On" />'))->not->toContain('*</span>');
});

it('writes required on every radio of a required group', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" :options="[\'a\' => \'A\', \'b\' => \'B\']" required />');

    preg_match_all('/<input[^>]*type="radio"[^>]*>/s', $html, $radios);

    expect($radios[0])->toHaveCount(2);
    foreach ($radios[0] as $radio) {
        expect($radio)->toMatch('/\srequired(?:=|[\s>\/])/');
    }
});

it('writes required on no radio of a group that is not required', function () {
    $html = Blade::render('<x-dbl::form.radio name="kind" :options="[\'a\' => \'A\', \'b\' => \'B\']" />');

    expect($html)->not->toMatch('/<input[^>]*\srequired/s');
});

it('gives the toggle the id of its name, as the guide says of every control', function () {
    // CHANGELOG [3.0.0]: "En un control de formulario, `id` es el del control y la etiqueta apunta a él (a `name` si no escribes `id`)".
    $html = Blade::render('<x-dbl::form.toggle name="on" label="On" />');

    expect($html)->toMatch('/<input[^>]*type="checkbox"[^>]*id="on"/s');
});

it('lets the id the caller writes win over the name on the toggle', function () {
    $html = Blade::render('<x-dbl::form.toggle name="on" label="On" id="mine" />');

    expect($html)->toMatch('/<input[^>]*type="checkbox"[^>]*id="mine"/s')->not->toContain('id="on"');
});
