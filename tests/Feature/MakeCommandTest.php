<?php

use Illuminate\Support\Facades\File;

/**
 * `daisyblade:make` writes a view the developer then edits. The view it starts from has to follow the
 * conventions of the package, or every new component begins by breaking them.
 */
afterEach(function () {
    File::deleteDirectory(resource_path('views/vendor/daisyblade'));
});

beforeEach(function () {
    // Published views win over the package's: make the command write into a folder we can throw away.
    File::ensureDirectoryExists(resource_path('views/vendor/daisyblade'));
});

it('creates a static view that takes the attributes in its root', function () {
    $this->artisan('daisyblade:make', ['name' => 'display/my-card'])->assertSuccessful();

    $view = File::get(resource_path('views/vendor/daisyblade/display/my-card.blade.php'));

    expect($view)->toContain('<div {{ $attributes }}>')->not->toContain('class=');
});

it('creates a local-state view whose x-data carries no PHP data', function () {
    $this->artisan('daisyblade:make', ['name' => 'display/my-card', '--type' => 2])->assertSuccessful();

    $view = File::get(resource_path('views/vendor/daisyblade/display/my-card.blade.php'));

    expect($view)->toContain('x-data="{ /* local state')->toContain('{{ $attributes }}');
});

it('creates a server view that starts its factory with @js($config), as the components of the package do', function () {
    $this->artisan('daisyblade:make', ['name' => 'display/my-card', '--type' => 3])->assertSuccessful();

    $view = File::get(resource_path('views/vendor/daisyblade/display/my-card.blade.php'));

    expect($view)->toContain('x-data="dblMyCard(@js($config))"')->toContain('{{ $attributes }}')
        ->toContain("'loadUrl' => \$loadUrl")
        ->not->toContain('axios');
});
