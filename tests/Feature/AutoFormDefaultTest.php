<?php

use Illuminate\Support\Facades\Blade;

/**
 * `form.fields` documents a `default` for each field and uses it in `form` mode. The default mode of `auto-form` and the
 * wizard is `alpine`, where the controls read their value from the `values` object, and there the default never arrived:
 * a text field with `default 'Ana'` was empty and a checkbox with `default true` was unchecked.
 */
it('starts an alpine auto-form from the default of each field', function () {
    $html = Blade::render('<x-dbl::sections.auto-form submit-url="/save" :fields="[\'name\' => [\'type\' => \'text\', \'default\' => \'Ana\'], \'ok\' => [\'type\' => \'checkbox\', \'default\' => true]]" />');

    expect(xDataConfig($html, 'dblAutoForm')['values'])->toBe(['name' => 'Ana', 'ok' => true]);
});

it('lets the values of the caller win over the default of the field', function () {
    $html = Blade::render('<x-dbl::sections.auto-form submit-url="/save" :values="[\'name\' => \'Berta\']" :fields="[\'name\' => [\'type\' => \'text\', \'default\' => \'Ana\']]" />');

    expect(xDataConfig($html, 'dblAutoForm')['values'])->toBe(['name' => 'Berta']);
});

it('leaves a field without default out of the values', function () {
    $html = Blade::render('<x-dbl::sections.auto-form submit-url="/save" :fields="[\'name\' => [\'type\' => \'text\']]" />');

    expect(xDataConfig($html, 'dblAutoForm')['values'])->toBe([]);
});

it('starts the wizard from the default of the fields of its steps, under the values of the step', function () {
    $steps = "[['label' => 'A', 'schema' => ['name' => ['type' => 'text', 'default' => 'Ana'], 'city' => ['type' => 'text', 'default' => 'Valencia']], 'values' => ['city' => 'Madrid']]]";
    $html = Blade::render("<x-dbl::sections.wizard submit-url=\"/save\" :steps=\"{$steps}\" />");

    expect(xDataConfig($html, 'dblWizard')['values'])->toBe(['name' => 'Ana', 'city' => 'Madrid']);
});
