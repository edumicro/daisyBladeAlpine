<?php

use Illuminate\Support\Facades\Blade;

// A `null` in `values` does not cancel the `default` of a field: form.fields reads `$values[$key] ?? default`, and the Alpine mode must start the same.
it('an alpine auto-form treats a null value like form.fields does: the default applies', function () {
    $html = Blade::render('<x-dbl::sections.auto-form submit-url="/save" :values="[\'name\' => null]" :fields="[\'name\' => [\'type\' => \'text\', \'default\' => \'Ana\']]" />');

    expect(xDataConfig($html, 'dblAutoForm')['values']['name'])->toBe('Ana');
});

it('an alpine auto-form keeps a value that is not null over the default', function () {
    $html = Blade::render('<x-dbl::sections.auto-form submit-url="/save" :values="[\'name\' => \'Eva\']" :fields="[\'name\' => [\'type\' => \'text\', \'default\' => \'Ana\']]" />');

    expect(xDataConfig($html, 'dblAutoForm')['values']['name'])->toBe('Eva');
});

it('a wizard treats a null value of a step like the default of its field', function () {
    $html = Blade::render('<x-dbl::sections.wizard submit-url="/save" :steps="[[\'label\' => \'A\', \'schema\' => [\'name\' => [\'type\' => \'text\', \'default\' => \'Ana\']], \'values\' => [\'name\' => null]]]" />');

    expect(xDataConfig($html, 'dblWizard')['values']['name'])->toBe('Ana');
});

// The counter of the textarea follows its x-model: the model changes from outside and an edit form starts with text.
it('the counter of the textarea follows its x-model', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" max-length="20" x-model="form.bio" />');

    expect($html)->toContain('x-effect="dblTypedCharacters = String(form.bio ?? \'\').length"');
});

it('the counter of the textarea has no model effect without an x-model', function () {
    expect(Blade::render('<x-dbl::form.textarea name="bio" max-length="20" />'))->not->toContain('x-effect');
});

// The importer: reachable with the keyboard, and it filters by `accept` also when a file is dropped.
it('the importer drop zone can be reached with the keyboard', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/i" />');

    expect($html)->toContain('tabindex="0"')->toContain('role="button"')
        ->toContain('@keydown.enter.prevent="$refs.fileInput.click()"')->toContain('@keydown.space.prevent="$refs.fileInput.click()"');
});

it('the importer tells the JavaScript which files it accepts', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/i" accept=".csv" />');

    expect(xDataConfig($html, 'dblSpreadsheetImport')['accept'])->toBe('.csv');
});
