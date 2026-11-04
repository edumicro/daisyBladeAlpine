<?php

use Illuminate\Support\Facades\File;

uses(Edumicro\DaisyBlade\Tests\TestCase::class)
    ->afterEach(function () {
        File::deleteDirectory(resource_path('views/vendor/daisyblade'));
        File::delete(config_path('daisyblade.php'));
    })
    ->in('Feature', 'Unit');

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
