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

it('keeps the listeners of the searchable select on the box around the text box, as bubbling events', function () {
    $html = Blade::render('<x-dbl::form.select name="city" searchable :options="[\'1\' => \'One\']" />');

    preg_match('/<div class="relative"[^>]*x-on:input[^>]*>/', $html, $box);

    expect($box)->not->toBeEmpty('the box around the text box has no listener')
        ->and($box[0])->toContain('x-on:input.stop="typed($event.target.value)"')
        ->and($box[0])->toContain('x-on:focusin="open = true"');   // focusin bubbles; focus does not
});

it('sends the x-model to the box with x-modelable (it holds the chosen key) and the rest of the attributes to the text box', function () {
    $html = Blade::render('<x-dbl::form.select name="c" searchable :options="[\'1\' => \'One\']" x-model.number="form.c" placeholder="Pick" autocomplete="off" @input="mine()" />');

    preg_match('/<div x-data="dblSelectSearch[^>]*>/s', $html, $root);
    preg_match('/<input[^>]*type="text"[^>]*>/s', $html, $input);

    expect($root[0])->toContain('x-modelable="chosen"')->toContain('x-model.number="form.c"')
        ->and($input[0])->not->toContain('x-model')
        ->and($input[0])->toContain('placeholder="Pick"')->toContain('autocomplete="off"')->toContain('@input="mine()"');
});
