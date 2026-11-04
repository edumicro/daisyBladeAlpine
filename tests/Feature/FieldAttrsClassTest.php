<?php

use Illuminate\Support\Facades\Blade;

/**
 * The `attrs` of a field (form.fields, form.repeater) used to be written BEFORE the component's own `class`. With two
 * `class` attributes the browser keeps the first one, so `['class' => 'my-own']` REPLACED `input w-full`. Whoever uses
 * it to strip the DaisyUI look keeps getting that: 2.4.1 only writes the two attributes as one, it does not mix them.
 */
function inputClassOf(string $html, string $pattern): string
{
    preg_match($pattern, $html, $input);
    preg_match_all('/\sclass="([^"]*)"/', $input[0], $classes);

    return $classes[1] === [] ? '' : implode('|', $classes[1]);
}

it('lets the class of attrs replace the classes of the control in the repeater', function () {
    $html = Blade::render('<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\', \'attrs\' => [\'class\' => \'my-own\']]]" />');

    expect(inputClassOf($html, '/<input[^>]*x-model="row\[[^"]*\]"[^>]*>/s'))->toBe('my-own');
});

it('lets the class of attrs replace the classes of the control in form.fields', function () {
    view()->share('errors', new Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\', \'attrs\' => [\'class\' => \'my-own\']]]" />');

    expect(inputClassOf($html, '/<input[^>]*name="a"[^>]*>/s'))->toBe('my-own');
});

it('writes the classes of the control when attrs has no class, in one single attribute', function () {
    view()->share('errors', new Illuminate\Support\ViewErrorBag);
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\', \'attrs\' => [\'autocomplete\' => \'off\']]]" />');
    preg_match('/<input[^>]*name="a"[^>]*>/s', $html, $input);

    expect(substr_count($input[0], 'class='))->toBe(1)
        ->and($input[0])->toContain('input w-full')->toContain('autocomplete="off"');
});

it('keeps the error class of the control when attrs has no class', function () {
    $errors = (new Illuminate\Support\ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['a' => ['required']]));
    view()->share('errors', $errors);
    $html = Blade::render('<x-dbl::form.fields :fields="[\'a\' => [\'type\' => \'text\']]" />');

    expect($html)->toContain('input-error');
});
