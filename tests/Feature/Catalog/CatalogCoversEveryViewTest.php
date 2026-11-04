<?php

use Edumicro\DaisyBlade\Tests\Catalog\Catalog;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * The architecture tests of this folder only protect what the catalog paints. So a view without an example is a hole in them:
 * a new component fails here until its example is written in tests/Catalog/Catalog.php.
 */

it('every view of the package has an example in the catalog', function () {
    $withExample = collect(Catalog::examples())->map(fn ($example) => $example->component)->unique()->all();

    expect(array_values(array_diff(Catalog::componentsWithAView(), $withExample)))->toBe([]);
});

it('no example in the catalog is for a view that does not exist', function () {
    $withExample = collect(Catalog::examples())->map(fn ($example) => $example->component)->unique()->all();

    expect(array_values(array_diff($withExample, Catalog::componentsWithAView())))->toBe([]);
});

it('every example paints', function (string $name) {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();

    expect(Catalog::examples()[$name]->render())->toBeString()->not->toBe('');
})->with(fn () => array_map(fn ($name) => [$name], array_combine(array_keys(Catalog::examples()), array_keys(Catalog::examples()))));
