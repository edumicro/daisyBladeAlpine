<?php

namespace Edumicro\DaisyBlade;

use Edumicro\DaisyBlade\Geo\Geocoder;
use Edumicro\DaisyBlade\Geo\GeocoderManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class DaisyBladeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views/daisyblade', 'daisyblade');
        // Los componentes usan claves JSON — __('Search...') —, así que hacen falta las
        // traducciones JSON. Con loadTranslationsFrom() a secas, es.json no se aplicaba nunca.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'daisyblade');
        // The copy published with `--tag=daisyblade-lang` (lang/vendor/daisyblade) is read after the package's own,
        // so the texts the application edits there win. (The namespaced PHP files already work this way in Laravel.)
        $this->loadJsonTranslationsFrom($this->app->langPath('vendor/daisyblade'));

        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade) {
            // Prefer published views, fallback to package views
            $path = is_dir(resource_path('views/vendor/daisyblade'))
                ? resource_path('views/vendor/daisyblade')
                : realpath(__DIR__.'/../resources/views/daisyblade');

            $blade->anonymousComponentPath($path, 'dbl');
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views/daisyblade' => resource_path('views/vendor/daisyblade'),
            ], 'daisyblade-views');

            $this->publishes([
                __DIR__.'/../config/daisyblade.php' => config_path('daisyblade.php'),
            ], 'daisyblade-config');

            // daisyblade.js imports ./http.js, and the components import ../http.js: they travel together,
            // with the same folders they have in the package.
            $this->publishes([
                __DIR__.'/../resources/js/daisyblade.js' => public_path('vendor/daisyblade/daisyblade.js'),
                __DIR__.'/../resources/js/http.js'       => public_path('vendor/daisyblade/http.js'),
                __DIR__.'/../resources/js/components'    => public_path('vendor/daisyblade/components'),
            ], 'daisyblade-assets');

            // The texts: lang/vendor/daisyblade/{es,ca,en}.json and {es,ca,en}/*.php
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/daisyblade'),
            ], 'daisyblade-lang');

            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\MakeDaisyBladeComponent::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/daisyblade.php', 'daisyblade');

        $this->app->singleton(GeocoderManager::class);
        $this->app->bind(Geocoder::class, GeocoderManager::class);
    }
}
