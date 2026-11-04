<?php

use Illuminate\Support\Facades\Route;

/*
 * The 422 of a server shows under EVERY kind of field of an auto-form (mode alpine): before, a checkbox, a toggle or a radio
 * kept the error to itself and the user pressed Save with nothing happening. The PHP side (mode form) is in
 * tests/Feature/FieldErrorEveryTypeTest.php.
 */

const FIELD_TYPES_WITH_ERROR = ['text', 'email', 'number', 'textarea', 'select', 'checkbox', 'toggle', 'radio', 'date'];

it('auto-form: a 422 on a field of any type shows its message under that field', function () {
    $fields = [];
    $errors = [];
    foreach (FIELD_TYPES_WITH_ERROR as $type) {
        $fields["f_{$type}"] = ['type' => $type, 'label' => ucfirst($type), 'options' => ['a' => 'A', 'b' => 'B']];
        $errors["f_{$type}"] = ["Wrong {$type}"];
    }
    Route::post('/form-422', fn () => response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422));
    $page = visit(browserPage('field-errors', '<x-dbl::sections.auto-form id="f" submit-url="/form-422" :fields="'.var_export($fields, true).'" />'));

    pause($page, 0.4);
    $page->script("document.querySelector('#f button[type=submit]').click()");
    $shown = readPage($page, "[...document.querySelectorAll('#f [role=alert]')].filter(p => p.offsetParent !== null).map(p => p.textContent.trim())", fn ($messages) => count($messages) >= count(FIELD_TYPES_WITH_ERROR));

    expect($shown)->toEqualCanonicalizing(array_map(fn ($type) => "Wrong {$type}", FIELD_TYPES_WITH_ERROR));
});
