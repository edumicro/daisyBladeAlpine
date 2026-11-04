<?php

/*
 * form.signature with the REAL signature_pad (a development dependency now; before, no browser test ever ran the
 * component: `new SignaturePad` was `undefined` in every page).
 */

it('signature: a stroke fills the hidden field with a PNG and Clear empties it', function () {
    $page = visit(browserPage('signature-sig', PAD_SCRIPT.'<form><x-dbl::form.signature name="sig" /></form>', ['modules' => ['signature']]));
    pause($page, 0.4);

    drawStroke($page);
    $signed = readPage($page, "document.querySelector('input[name=sig]').value", fn ($v) => str_starts_with((string) $v, 'data:image/png'));
    $page->script("[...document.querySelectorAll('button')].find(b => b.textContent.includes('Clear') || b.className.includes('btn-error')).click()");
    $cleared = readPage($page, "document.querySelector('input[name=sig]').value", fn ($v) => $v === '');

    expect($signed)->toStartWith('data:image/png')->and($cleared)->toBe('');
});

it('signature: required stops the submit of the form when nothing is signed', function () {
    $page = visit(browserPage('signature-sig-required', PAD_SCRIPT.'<form id="f"><x-dbl::form.signature name="sig" required /><button type="submit" id="go">go</button></form>', ['modules' => ['signature']]));
    pause($page, 0.4);

    // A listener added AFTER the component's own sees whether the component already stopped the submit.
    $page->script("document.querySelector('#f').addEventListener('submit', e => { window.__prevented = e.defaultPrevented; e.preventDefault() })");
    $page->script("document.querySelector('#go').click()");
    pause($page, 0.3);

    expect($page->script('window.__prevented ?? "never submitted"'))->toBeTrue()
        ->and($page->script("document.querySelector('[role=alert]').offsetParent !== null"))->toBeTrue();
});
