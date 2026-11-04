<?php

namespace Edumicro\DaisyBlade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDaisyBladeComponent extends Command
{
    protected $signature = 'daisyblade:make
                            {name : Component path, e.g. display/my-card}
                            {--type=1 : 1=static, 2=alpine-local, 3=alpine-axios}';

    protected $description = 'Create a new DaisyBlade anonymous Blade component';

    public function handle(): int
    {
        $name = Str::of($this->argument('name'))->replace('.', '/')->lower()->toString();
        $type = (int) $this->option('type');

        $base = is_dir(resource_path('views/vendor/daisyblade'))
            ? resource_path('views/vendor/daisyblade')
            : realpath(__DIR__.'/../../../resources/views/daisyblade');

        $dest = "{$base}/{$name}.blade.php";

        if (File::exists($dest)) {
            $this->error("Already exists: {$dest}");
            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($dest));
        File::put($dest, $this->stub($name, $type));

        $this->info("  ✓ Created: {$dest}");
        $this->line("    Usage: <x-dbl::{$name} />");

        return self::SUCCESS;
    }

    protected function stub(string $name, int $type): string
    {
        $label = Str::of($name)->afterLast('/')->replace('-', ' ')->title()->toString();
        // The name of the Alpine factory the view starts: display/my-card -> dblMyCard.
        $factory = 'dbl'.Str::of($name)->afterLast('/')->studly()->toString();

        // The three stubs follow the conventions of the package: `class` and the other attributes go to the
        // root (`{{ $attributes }}`), and the configuration reaches the JavaScript as `x-data="dblName(@js($config))"`.
        return match ($type) {
            2 => <<<BLADE
            @props([
                // 'prop' => 'default',
            ])

            <div x-data="{ /* local state, with no data from PHP */ }" {{ \$attributes }}>
                {{-- {$label} --}}
                {{ \$slot }}
            </div>
            BLADE,

            3 => <<<BLADE
            @props([
                'loadUrl' => '',
                // 'prop'  => 'default',
            ])

            @php
            \$config = [
                'loadUrl' => \$loadUrl,
            ];
            @endphp

            {{-- Write the factory in your JavaScript, with the same http.js ladder the other components use:
                 Alpine.data('{$factory}', config => ({ ... })) --}}
            <div x-data="{$factory}(@js(\$config))" {{ \$attributes }}>
                <template x-if="loading"><x-dbl::feedback.loading /></template>
                <template x-if="!loading">
                    {{-- {$label} --}}
                    {{ \$slot }}
                </template>
            </div>
            BLADE,

            default => <<<BLADE
            @props([
                // 'prop' => 'default',
            ])

            <div {{ \$attributes }}>
                {{-- {$label} --}}
                {{ \$slot }}
            </div>
            BLADE,
        };
    }
}
