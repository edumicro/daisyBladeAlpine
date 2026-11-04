<?php

use Illuminate\Support\Facades\Blade;

/**
 * `closable="false"` only hid the X button: Escape and a click on the backdrop still closed the modal. Now neither does;
 * only the order `dbl-modal-close` (the code of the page decides) closes it.
 */
it('does not close with Escape or with the backdrop when it is not closable', function () {
    $html = Blade::render('<x-dbl::actions.modal id="m" :closable="false">x</x-dbl::actions.modal>');

    expect($html)->not->toContain('keydown.escape')
        ->and(preg_match('/modal-backdrop"[^>]*@click/', $html))->toBe(0)
        ->and($html)->toContain('@dbl-modal-close.window');
});

it('closes with Escape, the backdrop and the X when it is closable (the default)', function () {
    $html = Blade::render('<x-dbl::actions.modal id="m">x</x-dbl::actions.modal>');

    expect($html)->toContain('@keydown.escape.window="hide()"')
        ->and(preg_match('/modal-backdrop"[^>]*@click="hide\(\)"/', $html))->toBe(1)
        ->and($html)->toContain('btn-circle');
});
