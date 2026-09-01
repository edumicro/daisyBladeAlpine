<?php

use Illuminate\Support\Facades\File;

/**
 * Estos tests publican de verdad dentro del esqueleto de testbench, así que **tienen que limpiar
 * lo que dejan**.
 *
 * Una copia publicada en `resources/views/vendor/daisyblade` TAPA las vistas del paquete: Laravel
 * resuelve antes lo publicado que lo que registra `loadViewsFrom`. Sin la limpieza, la copia
 * sobrevive a la ejecución y las siguientes renderizan la versión de ANTES del último cambio. Eso
 * produce el patrón «falla una vez y a la segunda pasa» —el arreglo aparece con una ejecución de
 * retraso— y, peor, puede dar por bueno un cambio roto porque lo que se probó fue la copia vieja.
 *
 * Lo mismo con la config: `mergeConfigFrom` solo fusiona un nivel, así que una `daisyblade.php`
 * publicada reemplaza entera cualquier clave anidada que el paquete añada después.
 */
it('publishes the configuration file', function () {
    $destino = config_path('daisyblade.php');
    File::delete($destino);

    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-config'])
         ->assertSuccessful();

    expect(file_exists($destino))->toBeTrue();

    File::delete($destino);
});

it('publishes views with vendor:publish', function () {
    $destino = resource_path('views/vendor/daisyblade');
    File::deleteDirectory($destino);

    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-views', '--force' => true])
         ->assertSuccessful();

    expect(is_dir($destino))->toBeTrue();

    File::deleteDirectory($destino);
});

it('installs with --vite flag', function () {
    $this->artisan('daisyblade:install', ['--vite' => true])
         ->assertSuccessful();

    expect(file_exists(resource_path('js/daisyblade.js')))->toBeTrue();
});

it('installs with --public flag', function () {
    $this->artisan('daisyblade:install', ['--public' => true])
         ->assertSuccessful();

    expect(file_exists(public_path('vendor/daisyblade/daisyblade.js')))->toBeTrue();
});
