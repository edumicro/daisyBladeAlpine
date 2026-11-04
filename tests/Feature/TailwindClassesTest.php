<?php

use Illuminate\Support\Facades\Blade;

/**
 * Tailwind generates a class only if it finds the WHOLE name written somewhere in the files it scans.
 * `class="checkbox-{$color}"` is never found, so `color="success"` painted nothing unless the host happened
 * to use `checkbox-success` somewhere else. Every component now maps the value to a name written out in full.
 */

/** The values every DaisyUI color prop accepts. */
const SEMANTIC_COLORS = ['neutral', 'primary', 'secondary', 'accent', 'info', 'success', 'warning', 'error'];

it('builds no class name at run time in any view', function () {
    $offenders = [];

    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views'));
    foreach ($views as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        // A utility prefix followed by "{$x}", "{{ $x }}" or "{{ $x[...] }}": checkbox-{$color}, bg-{{ $color }}, toast-{{ $position }}...
        $found = preg_match_all(
            '/(?<![\w])(?:bg|text|border|btn|badge|alert|toast|step|steps|checkbox|radio|toggle|progress|tabs|grid-cols|indicator|menu|select|input|textarea|loading|tooltip|kbd|mask|ring)-(?:\{\{\s*\$|\{\$)/',
            file_get_contents($file->getPathname()),
            $matches
        );
        if ($found) {
            $offenders[$file->getFilename()] = $matches[0];
        }
    }

    // card.blade.php is the one left: its `background` and `border-color` props take any Tailwind color
    // (`base-200`, `primary/10`...), so they cannot be a closed list without refusing values people use.
    expect(array_keys($offenders))->toBe(['card.blade.php']);
});

it('paints each color of checkbox, radio and toggle with its class, and an unknown one with the default', function (string $component, string $extra) {
    foreach (SEMANTIC_COLORS as $color) {
        expect(Blade::render("<x-dbl::form.{$component} name=\"x\" color=\"{$color}\" {$extra} />"))->toContain("{$component}-{$color}");
    }

    $unknown = Blade::render("<x-dbl::form.{$component} name=\"x\" color=\"magenta\" {$extra} />");
    expect($unknown)->toContain("{$component}-primary")->not->toContain("{$component}-magenta");
})->with([
    'checkbox' => ['checkbox', ''],
    'radio' => ['radio', ':options="[\'a\' => \'A\']"'],   // the only one with options
    'toggle' => ['toggle', ''],
]);

it('paints the avatar with the class of its color', function () {
    foreach ([...SEMANTIC_COLORS, 'base-100', 'base-200', 'base-300'] as $color) {
        expect(Blade::render("<x-dbl::display.avatar initials=\"AB\" color=\"{$color}\" />"))->toContain("bg-{$color}");
    }
    expect(Blade::render('<x-dbl::display.avatar initials="AB" color="magenta" />'))->toContain('bg-neutral')->not->toContain('bg-magenta')
        ->and(Blade::render('<x-dbl::display.avatar initials="AB" />'))->toContain('bg-neutral');
});

it('paints the rotating text and its dots with the class of its color', function () {
    $html = Blade::render('<x-dbl::display.text-rotate :texts="[\'a\', \'b\']" color="success" />');
    expect($html)->toContain('text-success')->toContain("'bg-success w-8'");

    $unknown = Blade::render('<x-dbl::display.text-rotate :texts="[\'a\']" color="magenta" />');
    expect($unknown)->toContain('text-primary')->not->toContain('magenta');
});

it('positions and colors the toast with classes written out in full', function () {
    foreach (['start', 'center', 'end'] as $position) {
        foreach (['top', 'middle', 'bottom'] as $vertical) {
            expect(Blade::render("<x-dbl::feedback.toast message=\"Hi\" position=\"{$position}\" vertical=\"{$vertical}\" />"))
                ->toContain("toast-{$position}")->toContain("toast-{$vertical}");
        }
    }
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        expect(Blade::render("<x-dbl::feedback.toast message=\"Hi\" type=\"{$type}\" />"))->toContain("alert-{$type}");
    }

    // The event-driven toast too (no message): same classes for its position.
    expect(Blade::render('<x-dbl::feedback.toast position="start" vertical="bottom" />'))->toContain('toast-start')->toContain('toast-bottom')
        ->and(Blade::render('<x-dbl::feedback.toast message="Hi" type="nope" position="nope" vertical="nope" />'))
        ->toContain('alert-info')->toContain('toast-end')->toContain('toast-top')->not->toContain('nope');
});

it('paints the current step with the class of its color', function () {
    $steps = "[['label' => 'A'], ['label' => 'B']]";
    foreach (SEMANTIC_COLORS as $color) {
        expect(Blade::render("<x-dbl::navigation.steps :steps=\"{$steps}\" color=\"{$color}\" />"))->toContain("'step-{$color}': current ===");
    }
    expect(Blade::render("<x-dbl::navigation.steps :steps=\"{$steps}\" color=\"magenta\" />"))->toContain("'step-primary': current ===");
});

it('maps the color of badge, progress, loading and radial progress to classes written out in full', function () {
    foreach ([...SEMANTIC_COLORS, 'ghost'] as $color) {
        expect(Blade::render("<x-dbl::display.badge label=\"X\" color=\"{$color}\" />"))->toContain("badge-{$color}");
    }
    expect(Blade::render('<x-dbl::display.badge label="X" variant="warning" />'))->toContain('badge-warning')
        ->and(Blade::render('<x-dbl::display.badge label="X" />'))->toContain('badge-primary')
        ->and(Blade::render('<x-dbl::display.badge label="X" color="magenta" />'))->not->toContain('magenta');

    foreach (SEMANTIC_COLORS as $color) {
        expect(Blade::render("<x-dbl::feedback.progress :value=\"5\" color=\"{$color}\" />"))->toContain("progress-{$color}")
            ->and(Blade::render("<x-dbl::feedback.loading color=\"{$color}\" />"))->toContain("text-{$color}")
            ->and(Blade::render("<x-dbl::display.radial-progress :value=\"5\" color=\"{$color}\" />"))->toContain("text-{$color}");
    }
});

it('maps the tabs style, the menu badge color, the indicator position and the skeleton columns', function () {
    foreach (['lifted', 'bordered', 'boxed', 'lift', 'border', 'box'] as $style) {
        expect(Blade::render("<x-dbl::navigation.tabs :items=\"[['label' => 'A', 'content' => 'a']]\" style=\"{$style}\" />"))->toContain("tabs-{$style}")
            ->and(Blade::render("<x-dbl::sections.tabs :tabs=\"[['label' => 'A', 'load_url' => '/a']]\" style=\"{$style}\" />"))->toContain("tabs-{$style}");
    }
    expect(Blade::render('<x-dbl::navigation.tabs :items="[[\'label\' => \'A\', \'content\' => \'a\']]" />'))->toContain('tabs-lifted');

    expect(Blade::render('<x-dbl::navigation.menu :items="[[\'label\' => \'A\', \'badge\' => 3, \'badgeColor\' => \'error\']]" />'))->toContain('badge-error')
        ->and(Blade::render('<x-dbl::navigation.menu :items="[[\'label\' => \'A\', \'badge\' => 3]]" />'))->toContain('badge-primary');

    foreach (['start', 'center', 'end'] as $horizontal) {
        expect(Blade::render("<x-dbl::layout.indicator label=\"1\" horizontal=\"{$horizontal}\" />"))->toContain("indicator-{$horizontal}");
    }
    foreach (['top', 'middle', 'bottom'] as $vertical) {
        expect(Blade::render("<x-dbl::layout.indicator label=\"1\" vertical=\"{$vertical}\" />"))->toContain("indicator-{$vertical}");
    }

    expect(Blade::render('<x-dbl::feedback.skeleton type="grid" :width="40" />'))->toContain('grid-cols-2')
        ->and(Blade::render('<x-dbl::feedback.skeleton type="grid" :width="60" />'))->toContain('grid-cols-3')
        ->and(Blade::render('<x-dbl::feedback.skeleton type="grid" :width="100" />'))->toContain('grid-cols-4');
});
