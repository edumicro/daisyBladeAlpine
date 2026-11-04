<?php

use Illuminate\Support\Facades\Blade;

/**
 * `form.kv-editor` with a `true` in its value: the view wrote `(string) true`, which is "1", with the row type `bool`,
 * and the editor reads a bool by comparing with the word 'true'. So every `true` came back as `false` when the form was
 * saved without touching the row. A bool is now written as the word `true` or `false`.
 */
it('writes a bool as the word true or false, which is what the editor reads back', function () {
    $html = Blade::render('<x-dbl::form.kv-editor name="cfg" :value="[\'active\' => true, \'off\' => false, \'n\' => 3]" />');
    $rows = xDataConfig($html, 'dblKvEditor')['rows'];

    expect($rows[0]['value'])->toBe('true')
        ->and($rows[1]['value'])->toBe('false')
        ->and($rows[2]['value'])->toBe('3');
});
