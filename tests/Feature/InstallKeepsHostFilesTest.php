<?php

use Illuminate\Support\Facades\File;

/**
 * 3.0: `daisyblade:install --vite` copies `components/` into `resources/js/components/`, the folder where most
 * Laravel apps keep their OWN JavaScript components. `File::copyDirectory` merged and overwrote: a host file called
 * calendar.js, map.js, upload.js, signature.js, flowchart.js or rich-text.js was replaced without a word
 * (http.js in resources/js already had this problem since 2.4; components/ makes it likely).
 * Now a file that already exists and is different is left alone unless `--force`, and the command says which.
 *
 * The tests publish for real into the testbench skeleton, so they leave it as they found it.
 */
function cleanInstalledJs(): void
{
    File::deleteDirectory(resource_path('js/components'));
    File::delete(resource_path('js/daisyblade.js'));
    File::delete(resource_path('js/http.js'));
    File::delete(resource_path('js/modelBinding.js'));
    File::delete(resource_path('js/factoryConfig.js'));
    File::delete(resource_path('js/observeSize.js'));
    File::deleteDirectory(public_path('vendor/daisyblade'));
}

beforeEach(fn () => cleanInstalledJs());
afterEach(fn () => cleanInstalledJs());

/** A file of the application with the name of one of ours. */
function hostCalendar(): string
{
    $host = resource_path('js/components/calendar.js');
    File::ensureDirectoryExists(dirname($host));
    File::put($host, '// the application\'s own calendar');

    return $host;
}

it('does not overwrite a file of the host application that has the same name as one of the package', function () {
    $host = hostCalendar();

    $this->artisan('daisyblade:install', ['--vite' => true])->assertSuccessful();

    expect(File::get($host))->toBe('// the application\'s own calendar');
});

it('says which files it left alone, and how to overwrite them', function () {
    hostCalendar();

    $this->artisan('daisyblade:install', ['--vite' => true])
        ->expectsOutputToContain('NOT overwritten')
        ->expectsOutputToContain('resources/js/components/calendar.js')
        ->expectsOutputToContain('--force')
        ->assertSuccessful();
});

it('still copies the files that do not exist next to the one it left alone', function () {
    hostCalendar();

    $this->artisan('daisyblade:install', ['--vite' => true])->assertSuccessful();

    expect(File::exists(resource_path('js/daisyblade.js')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/map.js')))->toBeTrue()
        ->and(File::exists(resource_path('js/components/map/maplibre.js')))->toBeTrue();
});

it('overwrites the files with --force', function () {
    $host = hostCalendar();

    $this->artisan('daisyblade:install', ['--vite' => true, '--force' => true])->assertSuccessful();

    expect(File::get($host))->toBe(File::get(__DIR__.'/../../resources/js/components/calendar.js'));
});

it('does not report as skipped a file that is the same as the one in the package', function () {
    $this->artisan('daisyblade:install', ['--vite' => true])->assertSuccessful();

    // Second run: everything is there and identical. Nothing to protect, nothing to report.
    $this->artisan('daisyblade:install', ['--vite' => true])
        ->doesntExpectOutputToContain('NOT overwritten')
        ->assertSuccessful();
});

it('keeps a different file in public/vendor/daisyblade too, unless --force', function () {
    $this->artisan('daisyblade:install', ['--public' => true])->assertSuccessful();
    $copy = public_path('vendor/daisyblade/http.js');
    File::put($copy, '// changed by hand');

    $this->artisan('daisyblade:install', ['--public' => true])
        ->expectsOutputToContain('public/vendor/daisyblade/http.js')
        ->assertSuccessful();
    expect(File::get($copy))->toBe('// changed by hand');

    $this->artisan('daisyblade:install', ['--public' => true, '--force' => true])->assertSuccessful();
    expect(File::get($copy))->toBe(File::get(__DIR__.'/../../resources/js/http.js'));
});
