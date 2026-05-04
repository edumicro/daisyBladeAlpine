<?php

namespace Edumicro\DaisyBlade;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;

class DaisyBladeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'daisyblade');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'daisyblade');

        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade) {
            // Prefer published views, fallback to vendor views
            $path = is_dir(resource_path('views/vendor/daisyblade'))
                ? resource_path('views/vendor/daisyblade')
                : realpath(__DIR__.'/../resources/views/daisyblade');

            $blade->anonymousComponentNamespace($path, 'db');
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/daisyblade'),
            ], 'daisyblade-views');

            $this->publishes([
                __DIR__.'/../config/daisyblade.php' => config_path('daisyblade.php'),
            ], 'daisyblade-config');

            $this->publishes([
                __DIR__.'/../resources/js/daisyblade.js' => public_path('vendor/daisyblade/daisyblade.js'),
            ], 'daisyblade-assets');

            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeDaisyBladeComponent::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/daisyblade.php', 'daisyblade');
    }
}
