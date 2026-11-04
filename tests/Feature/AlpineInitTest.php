<?php

/**
 * Alpine calls the `init()` method of an `x-data` object by itself, right after creating it
 * (Alpine 3.17.5, `directive("data")`: `reactiveData["init"] && evaluate(el, reactiveData["init"])`).
 * `x-init` is a different directive that evaluates its expression on top of that, so a view that also
 * writes `x-init="init()"` runs `init()` twice: a data table asked its server twice, a lazy tab loaded twice.
 *
 * `x-init` stays valid for other things (the wizard uses it to apply the initial values), just not to call `init()`.
 */
it('has no view that calls init() from x-init', function () {
    $offenders = [];

    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views'));
    foreach ($views as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        // `x-init="init()"`, `x-init="init(); ..."` and the multi-line form where init() is the first statement.
        if (preg_match('/x-init="\s*init\s*\(\s*\)/', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('hands the initial values of the wizard to its factory in the configuration, and has no x-init that could overwrite the saved progress', function () {
    $html = Illuminate\Support\Facades\Blade::render(
        '<x-dbl::sections.wizard :steps="[[\'label\' => \'A\', \'values\' => [\'name\' => \'Ana\']], [\'label\' => \'B\', \'values\' => [\'city\' => \'Vigo\']]]" submit-url="/save" />'
    );

    expect(xDataConfig($html, 'dblWizard')['values'])->toBe(['name' => 'Ana', 'city' => 'Vigo'])
        ->and($html)->not->toContain('x-init');
});
