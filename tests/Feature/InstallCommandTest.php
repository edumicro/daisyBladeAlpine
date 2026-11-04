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

    // daisyblade.js imports ./http.js: without it beside it, the published copy cannot even load.
    expect(file_exists(resource_path('js/daisyblade.js')))->toBeTrue()
        ->and(file_exists(resource_path('js/http.js')))->toBeTrue();
});

it('installs the components folder too, with the same layout it has in the package', function () {
    $this->artisan('daisyblade:install', ['--vite' => true])->assertSuccessful();

    // The components import ../http.js, so the folder has to sit next to daisyblade.js and http.js.
    foreach (['calendar.js', 'map.js', 'upload.js', 'rich-text.js', 'signature.js', 'flowchart.js', 'map/maplibre.js', 'flowchart/geometry.js'] as $file) {
        expect(file_exists(resource_path('js/components/'.$file)))->toBeTrue("components/{$file}");
    }

    File::deleteDirectory(resource_path('js/components'));
    $this->artisan('daisyblade:install', ['--public' => true])->assertSuccessful();

    expect(file_exists(public_path('vendor/daisyblade/components/calendar.js')))->toBeTrue()
        ->and(file_exists(public_path('vendor/daisyblade/components/map/google.js')))->toBeTrue();
});

it('publishes the components with the daisyblade-assets tag', function () {
    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-assets', '--force' => true])->assertSuccessful();

    expect(file_exists(public_path('vendor/daisyblade/daisyblade.js')))->toBeTrue()
        ->and(file_exists(public_path('vendor/daisyblade/components/upload.js')))->toBeTrue();
});

it('publishes the translations with the daisyblade-lang tag, and reads the JSON ones from there first', function () {
    $destination = lang_path('vendor/daisyblade');
    File::deleteDirectory($destination);

    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-lang', '--force' => true])->assertSuccessful();

    expect(file_exists($destination.'/es.json'))->toBeTrue()
        ->and(file_exists($destination.'/ca.json'))->toBeTrue()
        ->and(file_exists($destination.'/es/upload.php'))->toBeTrue();

    // The published copy wins: the application edits its text there.
    $texts = json_decode(file_get_contents($destination.'/es.json'), true);
    $texts['Search...'] = 'Buscad...';
    file_put_contents($destination.'/es.json', json_encode($texts));
    app('translator')->setLocale('es');

    expect(__('Search...'))->toBe('Buscad...');

    File::deleteDirectory($destination);
});

it('installs with --public flag', function () {
    $this->artisan('daisyblade:install', ['--public' => true])
         ->assertSuccessful();

    expect(file_exists(public_path('vendor/daisyblade/daisyblade.js')))->toBeTrue()
        ->and(file_exists(public_path('vendor/daisyblade/http.js')))->toBeTrue();
});
