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
            $views[str_replace(realpath(__DIR__.'/../../resources/views').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
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
