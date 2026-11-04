<?php

it('fields: a class="grid" given by the caller to x-dbl::form.fields is not overridden by the wrapper', function () {
    $page = visit(browserPage('fields-grid', <<<'BLADE'
        <x-dbl::form.fields id="f" class="grid grid-cols-2" :fields="['a' => ['type' => 'text', 'label' => 'A'], 'b' => ['type' => 'text', 'label' => 'B']]" />
        BLADE));

    pause($page, 0.8);

    expect($page->script("getComputedStyle(document.querySelector('#f')).display"))->toBe('grid');
});
