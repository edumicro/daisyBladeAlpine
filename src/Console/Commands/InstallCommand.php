<?php

namespace Edumicro\DaisyBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'daisyblade:install
                            {--vite   : Publish JS to resources/js/ for Vite compilation}
                            {--public : Publish JS to public/vendor/daisyblade/ for script tag}
                            {--force  : Overwrite the JS files that already exist and are different}';

    protected $description = 'Install DaisyBlade: publish views, config, and JS assets';

    /**
     * The JS files this run did not copy because the destination already had a different file, as paths from the
     * base path of the application. They are reported at the end so that nobody has to guess what was left alone.
     *
     * @var string[]
     */
    protected array $skippedFiles = [];

    public function handle(): int
    {
        $this->info('Installing DaisyBlade...');

        $this->callSilent('vendor:publish', ['--tag' => 'daisyblade-views', '--force' => true]);
        $this->callSilent('vendor:publish', ['--tag' => 'daisyblade-config']);
        $this->info('  ✓ Views and config published.');

        // daisyblade.js imports ./http.js (the one function every component uses to call the server) and ./modelBinding.js
        // (the one rule of the controls that have a model), and the
        // optional components (calendar, map, upload, rich-text, signature, flowchart) import ../http.js:
        // everything is published together, in the same folders it has in the package.
        $jsSources = [
            realpath(__DIR__.'/../../../resources/js/daisyblade.js'),
            realpath(__DIR__.'/../../../resources/js/http.js'),
            realpath(__DIR__.'/../../../resources/js/modelBinding.js'),
            realpath(__DIR__.'/../../../resources/js/components'),
        ];

        match ($this->resolveJsTarget()) {
            'vite'   => $this->publishVite($jsSources),
            'public' => $this->publishPublic($jsSources),
        };

        $this->reportSkippedFiles();

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

    /** @param string[] $sources  daisyblade.js, http.js, modelBinding.js and the components folder */
    protected function publishVite(array $sources): void
    {
        $this->copyAll($sources, resource_path('js'));
        $this->info('  ✓ JS → resources/js/daisyblade.js, http.js, modelBinding.js and components/');
        $this->line("    Add to app.js: <fg=gray>import './daisyblade.js'</>");
        $this->line("    Optional components: <fg=gray>import { registerCalendar } from './components/calendar.js'</> (see docs/components/)");
    }

    /** @param string[] $sources  daisyblade.js, http.js, modelBinding.js and the components folder */
    protected function publishPublic(array $sources): void
    {
        $this->copyAll($sources, public_path('vendor/daisyblade'));
        $this->info('  ✓ JS → public/vendor/daisyblade/daisyblade.js, http.js, modelBinding.js and components/');
        // type="module": daisyblade.js is an ES module (it imports ./http.js and exports its functions).
        $this->line("    Add to layout: <fg=gray><script type=\"module\" src=\"{{ asset('vendor/daisyblade/daisyblade.js') }}\"></script></>");
    }

    /**
     * Copy each file, or each folder with all it holds, into `$directory`, keeping its name.
     *
     * `resources/js/` (and `components/` inside it) is where an application keeps its OWN JavaScript, with names like
     * calendar.js or map.js that are also ours. So a file that already exists is never overwritten without `--force`.
     */
    protected function copyAll(array $sources, string $directory): void
    {
        File::ensureDirectoryExists($directory);

        foreach ($sources as $source) {
            if (! File::isDirectory($source)) {
                $this->copyFile($source, $directory.'/'.basename($source));

                continue;
            }

            foreach (File::allFiles($source) as $file) {
                $this->copyFile($file->getPathname(), $directory.'/'.basename($source).'/'.$file->getRelativePathname());
            }
        }
    }

    /** Copy one file, unless the destination already has a different one and `--force` was not given. */
    protected function copyFile(string $source, string $destination): void
    {
        if (File::exists($destination) && ! $this->option('force')) {
            // The same content is not a conflict: there is nothing to protect and nothing to copy.
            if (File::get($destination) !== File::get($source)) {
                $this->skippedFiles[] = Str::after($destination, base_path().'/');
            }

            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);
    }

    /** Tell which files were left as they were, and how to overwrite them. */
    protected function reportSkippedFiles(): void
    {
        if ($this->skippedFiles === []) {
            return;
        }

        $this->newLine();
        $this->warn('  ! '.count($this->skippedFiles).' JS file(s) already exist and are different from the ones in the package, so they were NOT overwritten:');

        foreach ($this->skippedFiles as $path) {
            $this->line("      {$path}");
        }

        $this->line('    If they are your own files, nothing to do. If they are an older copy of DaisyBlade, run it again with <fg=gray>--force</>.');
    }
}
