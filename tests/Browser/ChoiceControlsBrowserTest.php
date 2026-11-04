<?php

/* Checkbox, radio and toggle with the user's x-model and `required`. Findings: fable-daisy3 CB, RD, TG. */

it('checkbox: checked follows the x-model and clicking it updates the model', function () {
    $page = visit(browserPage('choice-checkbox', <<<'BLADE'
        <div id="scope" x-data="{ agree: true }"><x-dbl::form.checkbox name="agree" label="Agree" x-model="agree" /></div>
        BLADE));

    expect($page->script("document.querySelector('input[type=checkbox]').checked"))->toBeTrue();

    $page->click('input[type=checkbox]');

    expect($page->script(alpineData('#scope', 'agree')))->toBeFalse();
    $page->assertNoJavaScriptErrors();
});

it('radio: the x-model picks the radio and the id the caller wrote is unique', function () {
    $page = visit(browserPage('choice-radio', <<<'BLADE'
        <div x-data="{ kind: 'b' }"><x-dbl::form.radio name="kind" :options="['a' => 'A', 'b' => 'B']" x-model="kind" id="kind" /></div>
        BLADE));

    expect($page->script("[...document.querySelectorAll('input[type=radio]')].map(r => r.checked)"))->toBe([false, true])
        ->and($page->script('document.querySelectorAll(\'[id="kind"]\').length'))->toBe(1);
});

it('required toggle and radio: the form is not valid while they are empty', function () {
    $page = visit(browserPage('choice-required', <<<'BLADE'
        <form id="f">
            <x-dbl::form.toggle name="on" label="On" required />
            <x-dbl::form.radio name="kind" :options="['a' => 'A']" required />
        </form>
        BLADE));

    expect($page->script("document.getElementById('f').checkValidity()"))->toBeFalse();
});
