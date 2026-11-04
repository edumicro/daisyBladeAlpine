<?php

use Illuminate\Support\Facades\File;

uses(Edumicro\DaisyBlade\Tests\TestCase::class)
    ->afterEach(function () {
        File::deleteDirectory(resource_path('views/vendor/daisyblade'));
        File::delete(config_path('daisyblade.php'));
    })
    ->in('Feature', 'Unit', 'Browser');

require_once __DIR__.'/Browser/Support/helpers.php';

/**
 * Nada de lo que un test publique puede sobrevivirle.
 *
 * Varios tests ejecutan `vendor:publish` o `daisyblade:install` de verdad, y publican dentro del
 * esqueleto de testbench. El problema es que **una vista publicada TAPA la del paquete**: Laravel
 * resuelve antes `resources/views/vendor/daisyblade` que lo que registra `loadViewsFrom`. Si la
 * copia sobrevive, la siguiente ejecución renderiza la versión de ANTES del último cambio.
 *
 * Eso da el patrón «falla una vez y a la segunda pasa» —el arreglo aparece con una ejecución de
 * retraso— y, mucho peor, puede dar por bueno un cambio roto: lo que se probó fue la copia vieja.
 * Con la config es lo mismo, porque `mergeConfigFrom` solo fusiona un nivel.
 *
 * Va aquí y no en cada test para que valga también para los que se escriban después.
 */

/**
 * The configuration a view hands to an Alpine factory: `x-data="dblName(@js($config))"` is written as
 * `dblName(JSON.parse('{...}'))`, and this reads it back from the rendered HTML as an array.
 */
function xDataConfig(string $html, string $factory): array
{
    preg_match('/'.preg_quote($factory, '/')."\\(JSON\\.parse\\('(.*?)'\\)\\)/s", $html, $found);

    // The JSON travels inside a JavaScript string, where every backslash is doubled: reading it as a JSON string undoes that.
    return json_decode(json_decode('"'.($found[1] ?? '{}').'"'), true, flags: JSON_THROW_ON_ERROR);
}

/** The source of every view of the package, by path. */
function viewsOfThePackage(): array
{
    $views = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../resources/views'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php')) {
            $views[str_replace(realpath(__DIR__.'/../resources/views').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($views);

    return $views;
}

/**
 * The opening tags of a view that are plain HTML (not `<x-...>` Blade components), as [tag name, source of the tag, line, offset].
 * A tag ends at the first `>` that is not inside a quoted value, a `{{ }}` or the parentheses of a Blade directive.
 */
function openingTagsOf(string $source): array
{
    $tags = [];
    $length = strlen($source);
    $offset = 0;

    while (preg_match('/<([a-zA-Z][\w:.-]*)/', $source, $found, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $start = $found[0][1];
        $position = $start + strlen($found[0][0]);
        $quote = null;

        while ($position < $length) {
            $character = $source[$position];
            $rest = substr($source, $position, 4);

            if (str_starts_with($rest, '{{--') || str_starts_with($rest, '{{') || str_starts_with($rest, '{!!')) {
                // A comment, an echo: skip to its end, whatever it holds.
                $end = str_starts_with($rest, '{{--') ? '--}}' : (str_starts_with($rest, '{!!') ? '!!}' : '}}');
                $position = strpos($source, $end, $position) + strlen($end);

                continue;
            }

            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '@' && preg_match('/\G@[a-zA-Z]+\s*\(/', $source, $directive, 0, $position) === 1) {
                // A Blade directive with arguments (`@if($a > 1)`, `@disabled($x)`): skip its parentheses.
                $depth = 0;
                $position += strlen($directive[0]) - 1;
                do {
                    $depth += $source[$position] === '(' ? 1 : ($source[$position] === ')' ? -1 : 0);
                    $position++;
                } while ($depth > 0 && $position < $length);

                continue;
            } elseif ($character === '>') {
                break;
            }

            $position++;
        }

        $tags[] = [$found[1][0], substr($source, $start, $position - $start + 1), substr_count($source, "\n", 0, $start) + 1, $start];
        $offset = $start + strlen($found[0][0]);
    }

    return $tags;
}

// A browser test that waits for something that never comes must fail in 5 seconds, not in the 30 of the default.
pest()->browser()->timeout(5000);
