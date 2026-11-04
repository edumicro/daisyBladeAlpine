<?php

/*
 * form.upload and import.spreadsheet against a server that answers with a login page (HTML, 200).
 * Findings: ronda 1 UP, UPD, SS1, SS2 · ronda 2 SS (keyboard).
 */

it('upload: an HTML answer to the upload is an error and no id is kept', function () {
    $page = visit(browserPage('upload-html-answer', '<form><x-dbl::form.upload name="files" upload-url="/upload-html" /></form>', ['modules' => ['upload']]));

    chooseFile($page, 'input[type=file]', 'f.csv');

    $state = readPage($page, "({ hidden: [...document.querySelectorAll('input[name=\"files[]\"]')].map(i => i.value), err: document.querySelector('ul li p.text-error')?.textContent })", fn ($v) => ($v['err'] ?? '') !== '');

    expect($state['hidden'])->toBe([])
        ->and($state['err'])->not->toBeEmpty();
});

it('upload: dragging a file over the zone colors it and leaving restores it', function () {
    $page = visit(browserPage('upload-drag', '<form><x-dbl::form.upload name="files" upload-url="/upload-html" /></form>', ['modules' => ['upload']]));
    $zone = "document.querySelector('input[type=file]').parentElement";
    $fire = fn (string $type) => $page->script("$zone.dispatchEvent(new DragEvent('$type', { bubbles: true, cancelable: true }))");

    $fire('dragover');
    $dragging = readPage($page, "$zone.className", fn ($v) => str_contains($v, 'border-primary'));
    $fire('dragleave');
    $after = readPage($page, "$zone.className", fn ($v) => ! str_contains($v, 'border-primary'));

    expect($dragging)->toContain('border-primary')
        ->and($after)->not->toContain('border-primary')->toContain('border-base-300');
});

it('spreadsheet: choosing a file shows its name and does not promise rows', function () {
    $page = visit(browserPage('spreadsheet-choose', '<x-dbl::import.spreadsheet upload-url="/upload-html" />', ['modules' => ['upload']]));

    chooseFile($page, 'input[type=file]', 'f.csv');
    $text = readPage($page, "document.body.innerText.replace(/\\s+/g, ' ')", fn ($v) => str_contains($v, 'f.csv'));

    expect($text)->toContain('f.csv')->toContain('Confirm import')->not->toContain('rows detected');
});

it('spreadsheet: a file of another type chosen in the dialog is refused with a message, as when it is dropped', function () {
    $page = visit(browserPage('spreadsheet-accept', '<x-dbl::import.spreadsheet upload-url="/upload-html" />', ['modules' => ['upload']]));

    chooseFile($page, 'input[type=file]', 'photo.png');
    $text = readPage($page, "document.body.innerText.replace(/\\s+/g, ' ')", fn ($v) => str_contains($v, 'not accepted'));

    expect($text)->toContain('This file type is not accepted')->not->toContain('Confirm import');
});

it('spreadsheet: an HTML answer to the import is a failure, never "Import complete"', function () {
    $page = visit(browserPage('spreadsheet-html-answer', '<x-dbl::import.spreadsheet upload-url="/upload-html" />', ['modules' => ['upload']]));

    chooseFile($page, 'input[type=file]', 'f.csv');
    readPage($page, "document.body.innerText", fn ($v) => str_contains($v, 'Confirm import'));
    clickText($page, 'Confirm import');
    $text = readPage($page, "document.body.innerText.replace(/\\s+/g, ' ')", fn ($v) => str_contains($v, 'Import failed') || str_contains($v, 'Import complete'));

    expect($text)->toContain('Import failed')->not->toContain('Import complete');
});

it('spreadsheet: the keyboard reaches something to choose the file', function () {
    $page = visit(browserPage('spreadsheet-keyboard', '<x-dbl::import.spreadsheet upload-url="/upload-html" />', ['modules' => ['upload']]));

    $reachable = $page->script("[...document.querySelectorAll('a,button,input,select,textarea,[tabindex]')].filter(e => e.tabIndex >= 0 && e.offsetParent !== null && !e.closest('[style*=\"display: none\"]')).length");

    expect($reachable)->toBeGreaterThan(0);
});
