<?php

use Illuminate\Support\Facades\Blade;

/**
 * `form.kv-editor` with a `true` in its value: the view wrote `(string) true`, which is "1", with the row type `bool`,
 * and the editor reads a bool by comparing with the word 'true'. So every `true` came back as `false` when the form was
 * saved without touching the row. A bool is now written as the word `true` or `false`.
 */
it('writes a bool as the word true or false, which is what the editor reads back', function () {
    $html = Blade::render('<x-dbl::form.kv-editor name="cfg" :value="[\'active\' => true, \'off\' => false, \'n\' => 3]" />');

    // The rows travel inside the x-data as JSON text, with the quotes written as \u0022; put them back to read it.
    $xData = str_replace('\u0022', '"', $html);

    expect($xData)->toContain('"value":"true"')
        ->and($xData)->toContain('"value":"false"')
        ->and($xData)->toContain('"value":"3"');
});

/**
 * `form.kv-editor` with an object inside its value (a stdClass from `json_decode` without assoc, or a model cast to `object`):
 * the view cast it to text and stopped with "Object of class stdClass could not be converted to string". It is a nested value,
 * written as JSON, like an array.
 */
it('draws a nested object as JSON instead of failing', function () {
    $value = ['meta' => json_decode('{"a":1,"b":[true]}')];

    $html  = Blade::render('<x-dbl::form.kv-editor name="cfg" :value="$value" />', ['value' => $value]);
    $xData = str_replace('\u0022', '"', $html);

    expect($xData)->toContain('"type":"object"')
        ->and($xData)->toContain('{\"a\":1,\"b\":[true]}');
});
