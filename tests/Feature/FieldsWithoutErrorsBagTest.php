<?php

use Illuminate\Support\Facades\Blade;

/**
 * Every other form view checks `isset($errors)`; `form.fields` in form mode called `$errors->has()` without checking, so
 * rendering it where the session middleware has not shared the bag (a mail, a console render, a test) threw
 * "Undefined variable $errors" instead of drawing the fields.
 */
it('draws form.fields in form mode even when no error bag was shared', function () {
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\'], \'b\' => [\'type\' => \'textarea\'], \'c\' => [\'type\' => \'select\', \'options\' => [\'1\' => \'One\']]]" />');

    expect($html)->toContain('name="a"')->toContain('name="b"')->toContain('name="c"');
});
