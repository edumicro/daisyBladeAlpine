<?php

it('publishes the configuration file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-config'])
         ->assertSuccessful();

    expect(file_exists(config_path('daisyblade.php')))->toBeTrue();
});

it('publishes views with vendor:publish', function () {
    $this->artisan('vendor:publish', ['--tag' => 'daisyblade-views', '--force' => true])
         ->assertSuccessful();

    expect(is_dir(resource_path('views/vendor/daisyblade')))->toBeTrue();
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
