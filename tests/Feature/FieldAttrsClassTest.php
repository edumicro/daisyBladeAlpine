<?php

use Illuminate\Support\Facades\Blade;

/**
 * The `attrs` of a field (form.fields, form.repeater) used to be written BEFORE the component's own `class`. With two
 * `class` attributes the browser keeps the first one, so `['class' => 'font-mono']` erased `input input-sm w-full`
 * and left an unstyled box. Now there is one `class` with both.
 */
it('joins the class of attrs to the classes of the control in the repeater', function () {
    $html = Blade::render('<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\', \'attrs\' => [\'class\' => \'font-mono\']]]" />');
    preg_match('/<input[^>]*x-model="row\[[^"]*\]"[^>]*>/s', $html, $input);

    expect(substr_count($input[0], 'class='))->toBe(1)
        ->and($input[0])->toContain('input input-sm w-full font-mono');
});

it('joins the class of attrs to the classes of the control in form.fields', function () {
    view()->share('errors', new Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\', \'attrs\' => [\'class\' => \'font-mono\']]]" />');
    preg_match('/<input[^>]*name="a"[^>]*>/s', $html, $input);

    expect(substr_count($input[0], 'class='))->toBe(1)
        ->and($input[0])->toContain('input w-full')->toContain('font-mono');
});

it('keeps writing the other attrs and the error class of the control', function () {
    $errors = (new Illuminate\Support\ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['a' => ['required']]));
    view()->share('errors', $errors);
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\', \'attrs\' => [\'class\' => \'font-mono\', \'autocomplete\' => \'off\']]]" />');
    preg_match('/<input[^>]*name="a"[^>]*>/s', $html, $input);

    expect($input[0])->toContain('input-error')->toContain('autocomplete="off"')->toContain('font-mono');
});
