<?php

use Illuminate\Support\Facades\Blade;

/**
 * Some views put a prop inside a JavaScript text of their `x-data`: `loadUrl: '{{ $loadUrl }}'`. `{{ }}` writes the
 * apostrophe of `/rows?q=it's` as `&#039;`, the browser turns it back into `'` when it reads the attribute, and Alpine
 * receives `loadUrl: '/rows?q=it's'`: a SyntaxError and a dead component. `@js(...)` writes a JavaScript string that
 * is safe in any attribute.
 */

/** The x-data of the first element that has one, as the JavaScript Alpine receives (entities already decoded). */
function xDataOf(string $html): string
{
    preg_match('/x-data="([^"]*)"/s', $html, $match);

    return html_entity_decode($match[1], ENT_QUOTES);
}

/** True if `$javascript` is a valid JavaScript expression (checked by Node, as Alpine would evaluate it). */
function isValidJavaScriptExpression(string $javascript): bool
{
    $file = tempnam(sys_get_temp_dir(), 'xdata').'.js';
    file_put_contents($file, "const expression = ({$javascript})\n");
    exec('node --check '.escapeshellarg($file).' 2>&1', $output, $code);
    unlink($file);

    return $code === 0;
}

$awkwardUrl = '/rows?q=it\'s&name="Ann"&back=\\\\';

it('keeps the x-data valid when a URL carries an apostrophe, quotes or a backslash', function (string $view, string $props, string $evidence) use ($awkwardUrl) {
    $html = Blade::render("<x-dbl::{$view} {$props} />", ['url' => $awkwardUrl]);
    $javascript = xDataOf($html);

    // The apostrophe must not reach the JavaScript raw, inside a string delimited by apostrophes.
    expect($javascript)->not->toContain("'/rows?q=it's")
        ->and(isValidJavaScriptExpression($javascript))->toBeTrue("Not valid JavaScript:\n{$javascript}")
        ->and($javascript)->toContain($evidence);
})->with([
    'data-table' => ['display.data-table', ':load-url="$url" :columns="[]"', 'dblDataTable'],
    'resource-details' => ['display.resource-details', ':load-url="$url"', 'dblResourceDetails'],
    'spreadsheet upload and template' => ['import.spreadsheet', ':upload-url="$url" :template-url="$url"', 'dblSpreadsheetImport'],
    'wizard' => ['sections.wizard', ':submit-url="$url" :steps="[]"', 'dblWizard'],
    'auto-form' => ['sections.auto-form', ':submit-url="$url" :fields="[]"', 'dblAutoForm'],
    'remote select' => ['form.select', 'name="city" :load-url="$url"', 'dblSelectRemote'],
]);

it('passes the plain URL of the data-table to the factory unchanged', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/api/users" :columns="[]" />');

    expect(xDataConfig($html, 'dblDataTable')['loadUrl'])->toBe('/api/users');
});

it('hands the awkward URL to the factory exactly as it is', function () use ($awkwardUrl) {
    $html = Blade::render('<x-dbl::display.data-table :load-url="$url" :columns="[]" />', ['url' => $awkwardUrl]);

    expect(xDataConfig($html, 'dblDataTable')['loadUrl'])->toBe($awkwardUrl);
});

it('does not break the data-table x-data when the URL carries an apostrophe', function () {
    $html = Blade::render('<x-dbl::display.data-table load-url="/rows?q=it\'s" :columns="[]" />');

    expect(xDataOf($html))->not->toContain("'/rows?q=it's'");
});

it('gives the wizard the same text for its storage key as before, whatever the type of userId', function () {
    // The key of the saved progress is built from userId and formId. `{{ }}` always wrote them as text (false was
    // the empty text); @js on a bare false would give the JavaScript `false` and a different key.
    $withFalse = xDataConfig(Blade::render('<x-dbl::sections.wizard submit-url="/save" :steps="[]" :user-id="false" form-id="alta" />'), 'dblWizard');
    $withNumber = xDataConfig(Blade::render('<x-dbl::sections.wizard submit-url="/save" :steps="[]" :user-id="7" :form-id="12" />'), 'dblWizard');

    expect($withFalse['userId'])->toBe('')
        ->and($withNumber['userId'])->toBe('7')
        ->and($withNumber['formId'])->toBe('12');
});
