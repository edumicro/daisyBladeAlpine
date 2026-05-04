<?php

namespace Edumicro\DaisyBlade\Tests;

use Edumicro\DaisyBlade\DaisyBladeServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            DaisyBladeServiceProvider::class,
        ];
    }
}
