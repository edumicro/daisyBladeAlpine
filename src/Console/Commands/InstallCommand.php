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

        $jsSource = realpath(__DIR__.'/../../../resources/js/daisyblade.js');

        match ($this->resolveJsTarget()) {
            'vite'   => $this->publishVite($jsSource),
            'public' => $this->publishPublic($jsSource),
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

    protected function publishVite(string $source): void
    {
        $dest = resource_path('js/daisyblade.js');
        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);
        $this->info('  ✓ JS → resources/js/daisyblade.js');
        $this->line("    Add to app.js: <fg=gray>import './daisyblade.js'</>");
    }

    protected function publishPublic(string $source): void
    {
        $dest = public_path('vendor/daisyblade/daisyblade.js');
        File::ensureDirectoryExists(dirname($dest));
        File::copy($source, $dest);
        $this->info('  ✓ JS → public/vendor/daisyblade/daisyblade.js');
        $this->line("    Add to layout: <fg=gray><script src=\"{{ asset('vendor/daisyblade/daisyblade.js') }}\"></script></>");
    }
}
