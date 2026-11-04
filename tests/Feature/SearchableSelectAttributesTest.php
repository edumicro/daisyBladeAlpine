<?php

use Illuminate\Support\Facades\Blade;

/**
 * The searchable mode of the select (the text box takes the attributes of the caller). The text box used to carry the
 * template's own `@input` and `@focus` and then `$attributes`: a caller who wrote `@input="..."` or `@focus="..."` (the usual
 * Alpine spelling) got the attribute twice on the same element and the browser kept the first one, so theirs was dropped
 * with no error. (Chrome: the caller's handlers ran 0 times; written as `x-on:input` they ran, which proves it was the
 * duplicate.) The component's own listeners now sit on the box around the text box, where the events bubble to.
 */
it('does not write @input or @focus twice on the text box of a searchable select', function () {
    $html = Blade::render('<x-dbl::form.select name="city" searchable :options="[\'1\' => \'One\']" @input="mine()" @focus="opened()" />');

    preg_match('/<input[^>]*type="text"[^>]*>/s', $html, $input);

    expect($input)->not->toBeEmpty();
    expect(preg_match_all('/\s@input=/', $input[0]))->toBe(1, 'two @input on the same <input>: '.$input[0]);
    expect(preg_match_all('/\s@focus=/', $input[0]))->toBe(1, 'two @focus on the same <input>: '.$input[0]);
    expect($input[0])->toContain('@input="mine()"')->toContain('@focus="opened()"');
});

it('puts the text box OUTSIDE the scope of the component, so the x-ref and the data of the caller are theirs', function () {
    $html = Blade::render('<x-dbl::form.select name="city" searchable :options="[\'1\' => \'One\']" x-ref="city" />');

    // The text box comes before the element that opens the scope of dblSelectSearch, and the scope closes after its own list.
    expect(strpos($html, 'type="text"'))->toBeLessThan(strpos($html, 'x-data="dblSelectSearch'))
        ->and($html)->toContain('x-ref="city"');
    // The x-ref of the caller is on the text box, the one of the component (the hidden input) is inside the scope.
    preg_match('/<input[^>]*type="text"[^>]*>/s', $html, $input);
    expect($input[0])->toContain('x-ref="city"');
});

it('sends the x-model to the box with x-modelable (it holds the chosen key) and the rest of the attributes to the text box', function () {
    $html = Blade::render('<x-dbl::form.select name="c" searchable :options="[\'1\' => \'One\']" x-model.number="form.c" placeholder="Pick" autocomplete="off" @input="mine()" />');

    preg_match('/<span[^>]*x-modelable[^>]*>/s', $html, $root);
    preg_match('/<input[^>]*type="text"[^>]*>/s', $html, $input);

    expect($root[0])->toContain('x-modelable="chosen"')->toContain('x-model.number="form.c"')
        ->and($input[0])->not->toContain('x-model')
        ->and($input[0])->toContain('placeholder="Pick"')->toContain('autocomplete="off"')->toContain('@input="mine()"');
});
