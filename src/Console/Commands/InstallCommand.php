<?php

namespace Edumicro\DaisyBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'daisyblade:install
                            {--vite   : Publish JS to resources/js/ for Vite compilation}
                            {--public : Publish JS to public/vendor/daisyblade/ for script tag}';

    protected $description = 'Install DaisyBlade: publish views, config, and JS assets';

    public function handle(): int
    {
        $this->info('Installing DaisyBlade...');

        $this->callSilent('vendor:publish', ['--tag' => 'daisyblade-views', '--force' => true]);
        $this->callSilent('vendor:publish', ['--tag' => 'daisyblade-config']);
        $this->info('  ✓ Views and config published.');

        // daisyblade.js imports ./http.js (the one function every component uses to call the server),
        // so both files are published together, side by side.
        $jsSources = [
            realpath(__DIR__.'/../../../resources/js/daisyblade.js'),
            realpath(__DIR__.'/../../../resources/js/http.js'),
        ];

        match ($this->resolveJsTarget()) {
            'vite'   => $this->publishVite($jsSources),
            'public' => $this->publishPublic($jsSources),
        };

        $this->newLine();
        $this->info('DaisyBlade installed successfully.');

        return self::SUCCESS;
    }

    protected function resolveJsTarget(): string
    {
        if ($this->option('vite'))   return 'vite';
        if ($this->option('public')) return 'public';

        return $this->choice(
            'Where do you want to publish daisyblade.js?',
            [
                'vite'   => 'resources/js/daisyblade.js  (Vite — import in app.js)',
                'public' => 'public/vendor/daisyblade/   (script tag / CDN-style)',
            ],
            'vite'
        );
    }

    /** @param string[] $sources  daisyblade.js and http.js */
    protected function publishVite(array $sources): void
    {
        $this->copyAll($sources, resource_path('js'));
        $this->info('  ✓ JS → resources/js/daisyblade.js and resources/js/http.js');
        $this->line("    Add to app.js: <fg=gray>import './daisyblade.js'</>");
    }

    /** @param string[] $sources  daisyblade.js and http.js */
    protected function publishPublic(array $sources): void
    {
        $this->copyAll($sources, public_path('vendor/daisyblade'));
        $this->info('  ✓ JS → public/vendor/daisyblade/daisyblade.js and http.js');
        // type="module": daisyblade.js is an ES module (it imports ./http.js and exports its functions).
        $this->line("    Add to layout: <fg=gray><script type=\"module\" src=\"{{ asset('vendor/daisyblade/daisyblade.js') }}\"></script></>");
    }

    /** Copy each file into `$directory`, keeping its name. */
    protected function copyAll(array $sources, string $directory): void
    {
        File::ensureDirectoryExists($directory);
        foreach ($sources as $source) {
            File::copy($source, $directory.'/'.basename($source));
        }
    }
}
