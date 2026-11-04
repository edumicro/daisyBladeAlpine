<?php

/*
 * modal, alert, toast, swap, signature. Findings: ronda 1 MO, MO2, AL, TO, SG · ronda 2 SW, AL2, AL3, TO2.
 */

// ── Modal ──────────────────────────────────────────────────────────────────

it('modal closable=false: opens by event, Escape does not close it, dbl-modal-close does', function () {
    $page = visit(browserPage('modal-not-closable', '<x-dbl::actions.modal id="m1" title="T" :closable="false">body</x-dbl::actions.modal>'));
    $isOpen = "document.getElementById('m1').classList.contains('modal-open')";

    $page->script("window.dispatchEvent(new CustomEvent('dbl-modal-open', { detail: { id: 'm1' } }))");
    $opened = readPage($page, $isOpen);
    pressKey($page, 'Escape');
    pause($page, 0.2);
    $afterEscape = $page->script($isOpen);
    $page->script("window.dispatchEvent(new CustomEvent('dbl-modal-close', { detail: { id: 'm1' } }))");
    $afterClose = readPage($page, $isOpen, fn ($v) => $v === false);

    expect($opened)->toBeTrue()->and($afterEscape)->toBeTrue()->and($afterClose)->toBeFalse();
});

it('modal closable: opens by event; Escape and the backdrop close it', function () {
    $page = visit(browserPage('modal-closable', '<x-dbl::actions.modal id="m2" title="T">body</x-dbl::actions.modal>'));
    $isOpen = "document.getElementById('m2').classList.contains('modal-open')";
    $open = fn () => $page->script("window.dispatchEvent(new CustomEvent('dbl-modal-open', { detail: { id: 'm2' } }))");

    $open();
    $opened = readPage($page, $isOpen);
    pressKey($page, 'Escape');
    $afterEscape = readPage($page, $isOpen, fn ($v) => $v === false);
    $open();
    readPage($page, $isOpen);
    $page->script("document.querySelector('#m2 .modal-backdrop').click()");
    $afterBackdrop = readPage($page, $isOpen, fn ($v) => $v === false);

    expect($opened)->toBeTrue()->and($afterEscape)->toBeFalse()->and($afterBackdrop)->toBeFalse();
});

// ── Alert and toast ────────────────────────────────────────────────────────

it('alert: it is shown and disappears when closed', function () {
    $page = visit(browserPage('alert-close', '<x-dbl::feedback.alert message="hola" />'));

    $before = isShown($page, '.alert');
    $page->script("document.querySelector('.alert button').click()");
    pause($page, 0.2);

    expect($before)->toBeTrue()->and(isShown($page, '.alert'))->toBeFalse();
});

it('alert: an x-show="false" of the caller keeps it hidden', function () {
    $page = visit(browserPage('alert-x-show', '<x-dbl::feedback.alert message="hola" x-show="false" id="al2" />'));

    expect(isShown($page, '#al2'))->toBeFalse();
});

it('alert: a style="display:none" of the caller keeps it hidden', function () {
    $page = visit(browserPage('alert-style', '<x-dbl::feedback.alert message="hola" style="display:none" id="al3" />'));

    expect(isShown($page, '#al3'))->toBeFalse();
});

it('toast (fixed): it is shown and disappears when closed', function () {
    $page = visit(browserPage('toast-close', '<x-dbl::feedback.toast message="aviso" :auto-close="false" />'));

    $before = isShown($page, '.toast');
    $page->script("document.querySelector('.toast button').click()");
    pause($page, 0.2);

    expect($before)->toBeTrue()->and(isShown($page, '.toast'))->toBeFalse();
});

it('toast: an x-show="false" of the caller keeps it hidden', function () {
    $page = visit(browserPage('toast-x-show', '<x-dbl::feedback.toast message="aviso" :auto-close="false" x-show="false" id="to2" />'));

    expect(isShown($page, '#to2'))->toBeFalse();
});

// ── Swap ───────────────────────────────────────────────────────────────────

it('swap: pressing the button inside the swap turns it on', function () {
    $page = visit(browserPage('swap-button', '<x-dbl::actions.swap off-label="Dark" on-label="Light" />'));

    $page->script("document.querySelector('.swap-off button').click()");

    expect(readPage($page, "document.querySelector('.swap input').checked"))->toBeTrue();
});

// ── Signature ──────────────────────────────────────────────────────────────

it('signature: without an error the border is base-content/40, not the error one', function () {
    $page = visit(browserPage('signature-border', '<x-dbl::form.signature name="sig" />', ['modules' => ['signature']]));

    $classes = $page->script("document.querySelector('canvas').parentElement.className");

    expect($classes)->toContain('border-base-content/40')->not->toContain('border-error');
});
