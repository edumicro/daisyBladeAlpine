<?php

/**
 * One test (or a few) per convention of the 3.x line. Their job is to stop the package from going back
 * to a 2.x name or to a second way of doing something: each one scans the sources, not a rendering,
 * so it also catches the view nobody has a rendering test for.
 *
 * The numbers are those of the "una sola convención" list in CHANGELOG.md, section [3.0.0].
 */

/** Every Blade view of the package, as [path => source]. */
function conventionViews(): array
{
    $views = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php')) {
            $views[str_replace(realpath(__DIR__.'/../../resources/views/daisyblade').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($views);

    return $views;
}

/** Every JavaScript file of the package, as [path => source]. */
function conventionScripts(): array
{
    $scripts = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/js'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.js')) {
            $scripts[str_replace(realpath(__DIR__.'/../../resources/js').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($scripts);

    return $scripts;
}

/**
 * The entries of `$sources` whose text matches `$pattern`, as [path => matches]. An empty result is the pass.
 *
 * @param  array<string, string>  $sources
 */
function conventionOffenders(array $sources, string $pattern): array
{
    $offenders = [];

    foreach ($sources as $path => $source) {
        if (preg_match_all($pattern, $source, $matches)) {
            $offenders[$path] = array_values(array_unique($matches[0]));
        }
    }

    return $offenders;
}

// ── 1. One prefix: dbl ─────────────────────────────────────────────────────

it('1. names every Alpine factory with the dbl prefix, in the views and in the scripts', function () {
    // dbSelectRemote, dbCalendar... are the 2.x names. `dbl` followed by an upper-case letter is the new one.
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb[A-Z]\w*/'))->toBe([]);
});

it('1. names every own CSS class with the dbl prefix', function () {
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb-[\w-]+/'))->toBe([]);
});

it('1. names every localStorage key with the dbl prefix', function () {
    // `db_` followed by a letter is a 2.x key. The one left in readStored() ends in a quote: it is the migration.
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb_[a-z]\w*/'))->toBe([]);
});

/**
 * The props a view declares in its @props([...]), as [name => default as written]. Reads the source, so it
 * needs no rendering and works for the views that need a slot or a parent to render.
 *
 * @return array<string, string>
 */
function conventionProps(string $source): array
{
    // Multi-line form: ends at a line that is just `])`. Single-line form: `@props(['name', 'x' => 1])`.
    if (preg_match('/@props\(\[(.*?)\n\]\)/s', $source, $block) !== 1 && preg_match('/@props\(\[([^\n]*)\]\)/', $source, $block) !== 1) {
        return [];
    }

    $props = [];

    // A prop is a quoted name at the start of a line (or after the opening bracket), then `=>` and its default, or a comma.
    preg_match_all('/(?:^|\n|\[)\s*\'([A-Za-z][A-Za-z0-9_-]*)\'\s*(?:=>\s*([^\n]*?))?(?:,\s*(?:\/\/[^\n]*)?)?(?=\n|$)/', $block[1], $found, PREG_SET_ORDER);

    foreach ($found as $match) {
        $props[$match[1]] = $match[2] ?? '';
    }

    return $props;
}

/**
 * Every view with the props it declares, as [path => [name => default]].
 *
 * @return array<string, array<string, string>>
 */
function conventionViewProps(): array
{
    return array_map('conventionProps', conventionViews());
}

/** The paths of the views that declare a prop with this name. */
function conventionViewsWithProp(string $prop): array
{
    return array_keys(array_filter(conventionViewProps(), fn (array $props) => array_key_exists($prop, $props)));
}

// ── 2. URL props: load-url, submit-url, upload-url, geocode-url ────────────

it('2. names the URL props after what the URL is for', function () {
    // Every prop that ends in Url, whatever the view. A new one has to be added here on purpose.
    $allowed = [
        'loadUrl',     // where the component reads its data from
        'submitUrl',   // where it sends what the user filled in
        'uploadUrl',   // where the files go
        'geocodeUrl',  // the address search of the map
        'deleteUrl',   // upload: URL template to delete one file
        'reorderUrl',  // upload: where the new order is sent
        'templateUrl', // spreadsheet: link to download the template
        'baseUrl',     // pagination: the page links
        'applyUrl',    // form.filter, until it is folded into form.filters (convention 9)
    ];

    $found = [];
    foreach (conventionViewProps() as $path => $props) {
        foreach (array_keys($props) as $name) {
            if (str_ends_with($name, 'Url') && ! in_array($name, $allowed, true)) {
                $found[$path][] = $name;
            }
        }
    }

    expect($found)->toBe([]);
});

it('2. has no prop called action, url or optionsUrl, the 2.x names', function () {
    $offenders = [];
    foreach (['action', 'url', 'optionsUrl'] as $old) {
        if ($views = conventionViewsWithProp($old)) {
            $offenders[$old] = $views;
        }
    }

    expect($offenders)->toBe([]);
});

it('2. gives load-url to every component that reads its data from the server', function () {
    expect(conventionViewsWithProp('loadUrl'))->toBe([
        'display/calendar.blade.php',
        'display/data-table.blade.php',
        'display/resource-details.blade.php',
        'form/select.blade.php',
    ]);
});

it('2. gives submit-url to the components that send what the user filled in, and upload-url to those that send files', function () {
    expect(conventionViewsWithProp('submitUrl'))->toBe(['sections/auto-form.blade.php', 'sections/wizard.blade.php'])
        ->and(conventionViewsWithProp('uploadUrl'))->toBe(['form/rich-text.blade.php', 'form/upload.blade.php', 'import/spreadsheet.blade.php'])
        ->and(conventionViewsWithProp('geocodeUrl'))->toBe(['display/map.blade.php']);
});

it('2. writes the URL of a lazy tab and of a map layer as load_url, in snake_case', function () {
    // The keys of a configuration array are snake_case; a kebab-case key ('load-url') was the 2.x spelling.
    $sources = conventionViews();

    expect(conventionOffenders($sources, '/[\'"]load-url[\'"]\s*(?:=>|\])/'))->toBe([]);

    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/a\']]" />');
    expect($html)->toContain('/a');
});

it('2. gives the scripts one name for each URL in their configuration', function () {
    $offenders = conventionOffenders(conventionScripts(), '/config\.(?:action|optionsUrl|url)\b/');

    expect($offenders)->toBe([]);
});
