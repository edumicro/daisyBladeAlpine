<?php

/**
 * One test (or a few) per convention of the 3.x line. Their job is to stop the package from going back
 * to a 2.x name or to a second way of doing something: each one scans the sources, not a rendering,
 * so it also catches the view nobody has a rendering test for.
 *
 * The numbers are those of the "una sola convención" list in CHANGELOG.md, section [3.0.0].
 */

/** Every Blade view of the package, as [path => source]. */
function conventionViews(): array
{
    $views = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.blade.php')) {
            $views[str_replace(realpath(__DIR__.'/../../resources/views/daisyblade').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($views);

    return $views;
}

/** Every JavaScript file of the package, as [path => source]. */
function conventionScripts(): array
{
    $scripts = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/js'));

    foreach ($files as $file) {
        if (str_ends_with($file->getFilename(), '.js')) {
            $scripts[str_replace(realpath(__DIR__.'/../../resources/js').'/', '', $file->getRealPath())] = file_get_contents($file->getRealPath());
        }
    }

    ksort($scripts);

    return $scripts;
}

/**
 * The entries of `$sources` whose text matches `$pattern`, as [path => matches]. An empty result is the pass.
 *
 * @param  array<string, string>  $sources
 */
function conventionOffenders(array $sources, string $pattern): array
{
    $offenders = [];

    foreach ($sources as $path => $source) {
        if (preg_match_all($pattern, $source, $matches)) {
            $offenders[$path] = array_values(array_unique($matches[0]));
        }
    }

    return $offenders;
}

// ── 1. One prefix: dbl ─────────────────────────────────────────────────────

it('1. names every Alpine factory with the dbl prefix, in the views and in the scripts', function () {
    // dbSelectRemote, dbCalendar... are the 2.x names. `dbl` followed by an upper-case letter is the new one.
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb[A-Z]\w*/'))->toBe([]);
});

it('1. names every own CSS class with the dbl prefix', function () {
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb-[\w-]+/'))->toBe([]);
});

it('1. names every localStorage key with the dbl prefix', function () {
    // `db_` followed by a letter is a 2.x key. The one left in readStored() ends in a quote: it is the migration.
    $sources = [...conventionViews(), ...conventionScripts()];

    expect(conventionOffenders($sources, '/\bdb_[a-z]\w*/'))->toBe([]);
});

/**
 * The props a view declares in its @props([...]), as [name => default as written]. Reads the source, so it
 * needs no rendering and works for the views that need a slot or a parent to render.
 *
 * @return array<string, string>
 */
function conventionProps(string $source): array
{
    // Multi-line form: ends at a line that is just `])`. Single-line form: `@props(['name', 'x' => 1])`.
    if (preg_match('/@props\(\[(.*?)\n\]\)/s', $source, $block) !== 1 && preg_match('/@props\(\[([^\n]*)\]\)/', $source, $block) !== 1) {
        return [];
    }

    $props = [];

    // The one-line form lists its props separated by commas: `'name', 'variant' => 'o'`.
    if (! str_contains($block[1], "\n")) {
        foreach (preg_split('/,\s*(?=\')/', trim($block[1])) as $entry) {
            if (preg_match('/^\'([A-Za-z][A-Za-z0-9_-]*)\'\s*(?:=>\s*(.*))?$/s', trim($entry), $match) === 1) {
                $props[$match[1]] = $match[2] ?? '';
            }
        }

        return $props;
    }

    // A prop is a quoted name at the start of a line (or after the opening bracket), then `=>` and its default, or a comma.
    preg_match_all('/(?:^|\n|\[)\s*\'([A-Za-z][A-Za-z0-9_-]*)\'\s*(?:=>\s*([^\n]*?))?(?:,\s*(?:\/\/[^\n]*)?)?(?=\n|$)/', $block[1], $found, PREG_SET_ORDER);

    foreach ($found as $match) {
        $props[$match[1]] = $match[2] ?? '';
    }

    return $props;
}

/**
 * Every view with the props it declares, as [path => [name => default]].
 *
 * @return array<string, array<string, string>>
 */
function conventionViewProps(): array
{
    return array_map('conventionProps', conventionViews());
}

/** The paths of the views that declare a prop with this name. */
function conventionViewsWithProp(string $prop): array
{
    return array_keys(array_filter(conventionViewProps(), fn (array $props) => array_key_exists($prop, $props)));
}

// ── 2. URL props: load-url, submit-url, upload-url, geocode-url ────────────

it('2. names the URL props after what the URL is for', function () {
    // Every prop that ends in Url, whatever the view. A new one has to be added here on purpose.
    $allowed = [
        'loadUrl',     // where the component reads its data from
        'submitUrl',   // where it sends what the user filled in
        'uploadUrl',   // where the files go
        'geocodeUrl',  // the address search of the map
        'deleteUrl',   // upload: URL template to delete one file
        'reorderUrl',  // upload: where the new order is sent
        'templateUrl', // spreadsheet: link to download the template
        'baseUrl',     // pagination: the page links
        'applyUrl',    // form.filter, until it is folded into form.filters (convention 9)
    ];

    $found = [];
    foreach (conventionViewProps() as $path => $props) {
        foreach (array_keys($props) as $name) {
            if (str_ends_with($name, 'Url') && ! in_array($name, $allowed, true)) {
                $found[$path][] = $name;
            }
        }
    }

    expect($found)->toBe([]);
});

it('2. has no prop called action, url or optionsUrl, the 2.x names', function () {
    $offenders = [];
    foreach (['action', 'url', 'optionsUrl'] as $old) {
        if ($views = conventionViewsWithProp($old)) {
            $offenders[$old] = $views;
        }
    }

    expect($offenders)->toBe([]);
});

it('2. gives load-url to every component that reads its data from the server', function () {
    expect(conventionViewsWithProp('loadUrl'))->toBe([
        'display/calendar.blade.php',
        'display/data-table.blade.php',
        'display/resource-details.blade.php',
        'form/select.blade.php',
    ]);
});

it('2. gives submit-url to the components that send what the user filled in, and upload-url to those that send files', function () {
    expect(conventionViewsWithProp('submitUrl'))->toBe(['sections/auto-form.blade.php', 'sections/wizard.blade.php'])
        ->and(conventionViewsWithProp('uploadUrl'))->toBe(['form/rich-text.blade.php', 'form/upload.blade.php', 'import/spreadsheet.blade.php'])
        ->and(conventionViewsWithProp('geocodeUrl'))->toBe(['display/map.blade.php']);
});

it('2. writes the URL of a lazy tab and of a map layer as load_url, in snake_case', function () {
    // The keys of a configuration array are snake_case; a kebab-case key ('load-url') was the 2.x spelling.
    $sources = conventionViews();

    expect(conventionOffenders($sources, '/[\'"]load-url[\'"]\s*(?:=>|\])/'))->toBe([]);

    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/a\']]" />');
    expect($html)->toContain('/a');
});

it('2. gives the scripts one name for each URL in their configuration', function () {
    $offenders = conventionOffenders(conventionScripts(), '/config\.(?:action|optionsUrl|url)\b/');

    expect($offenders)->toBe([]);
});

// ── 3. Value: `value` for one value, `values` for a name => value map ──────

it('3. gives a value prop to every form control', function () {
    $controls = [
        'form/input.blade.php', 'form/checkbox.blade.php', 'form/toggle.blade.php', 'form/radio.blade.php',
        'form/select.blade.php', 'form/textarea.blade.php', 'form/rich-text.blade.php',
        'form/kv-editor.blade.php', 'form/list-editor.blade.php', 'form/repeater.blade.php',
    ];

    $without = array_values(array_diff($controls, conventionViewsWithProp('value')));

    expect($without)->toBe([]);
});

it('3. keeps values for the name => value maps of auto-form and fields only', function () {
    // The wizard takes its maps inside each step (`steps[].values`), not as a prop of its own.
    expect(conventionViewsWithProp('values'))->toBe(['form/fields.blade.php', 'sections/auto-form.blade.php']);
});

// ── 4. Help: `hint`, never `description`, on a form control ────────────────

it('4. gives a hint prop to every form control', function () {
    $controls = [
        'form/input.blade.php', 'form/checkbox.blade.php', 'form/toggle.blade.php', 'form/radio.blade.php',
        'form/select.blade.php', 'form/textarea.blade.php', 'form/rich-text.blade.php', 'form/signature.blade.php',
        'form/upload.blade.php', 'form/kv-editor.blade.php', 'form/list-editor.blade.php', 'form/repeater.blade.php',
    ];

    expect(array_values(array_diff($controls, conventionViewsWithProp('hint'))))->toBe([]);
});

it('4. has no description prop on any form control', function () {
    $offenders = array_values(array_filter(
        conventionViewsWithProp('description'),
        fn (string $path) => str_starts_with($path, 'form/') || $path === 'import/spreadsheet.blade.php',
    ));

    expect($offenders)->toBe([]);
});

// ── 5. Size: always a token, xs | sm | md | lg | xl ────────────────────────

/** The classes each size token turns into, per component: [blade tag => [token => class]]. */
function conventionSizeClasses(): array
{
    $of = fn (string $prefix, array $tokens = ['xs', 'sm', 'md', 'lg', 'xl']) => array_combine($tokens, array_map(fn ($t) => "{$prefix}-{$t}", $tokens));

    return [
        '<x-dbl::actions.button label="X" size="%s" />' => $of('btn'),
        '<x-dbl::actions.fab size="%s" />' => $of('btn'),
        '<x-dbl::actions.swap size="%s" />' => $of('btn'),
        '<x-dbl::actions.modal id="m" size="%s">x</x-dbl::actions.modal>' => ['xs' => 'max-w-xs', 'sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl', 'full' => 'max-w-full'],
        '<x-dbl::form.input name="x" size="%s" />' => $of('input'),
        '<x-dbl::form.textarea name="x" size="%s" />' => $of('textarea'),
        '<x-dbl::form.checkbox name="x" size="%s" />' => $of('checkbox'),
        '<x-dbl::form.toggle name="x" size="%s" />' => $of('toggle'),
        '<x-dbl::form.radio name="x" :options="[\'a\' => \'A\']" size="%s" />' => $of('radio'),
        '<x-dbl::form.select name="x" size="%s" />' => $of('select'),
        '<x-dbl::display.badge label="X" size="%s" />' => $of('badge'),
        '<x-dbl::display.status status="active" position="badge" size="%s" />' => $of('badge'),
        '<x-dbl::display.kbd key="K" size="%s" />' => $of('kbd'),
        '<x-dbl::display.avatar initials="AB" size="%s" />' => ['xs' => 'w-6 h-6', 'sm' => 'w-8 h-8', 'md' => 'w-10 h-10', 'lg' => 'w-16 h-16', 'xl' => 'w-20 h-20'],
        '<x-dbl::display.list :items="[\'a\']" size="%s" />' => ['xs' => 'text-xs', 'sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'],
        '<x-dbl::display.radial-progress :value="5" size="%s" />' => ['xs' => 'w-12 h-12', 'sm' => 'w-16 h-16', 'md' => 'w-24 h-24', 'lg' => 'w-32 h-32', 'xl' => 'w-40 h-40'],
        '<x-dbl::display.text-rotate :texts="[\'a\']" size="%s" />' => ['xs' => 'text-xs', 'sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'],
        '<x-dbl::feedback.loading size="%s" />' => $of('loading'),
        '<x-dbl::feedback.progress :value="5" size="%s" />' => $of('progress'),
        '<x-dbl::feedback.skeleton type="text" size="%s" />' => ['xs' => 'h-2', 'sm' => 'h-3', 'md' => 'h-4', 'lg' => 'h-5', 'xl' => 'h-6'],
        '<x-dbl::navigation.menu :items="[[\'label\' => \'A\']]" size="%s" />' => $of('menu'),
        '<x-dbl::navigation.dock :items="[[\'label\' => \'A\', \'url\' => \'/\']]" icon-size="%s" />' => ['xs' => 'w-4 h-4', 'sm' => 'w-5 h-5', 'md' => 'w-6 h-6', 'lg' => 'w-8 h-8', 'xl' => 'w-10 h-10'],
    ];
}

it('5. turns each size token into its class with a map written out in full, and an unknown token into the default', function () {
    foreach (conventionSizeClasses() as $template => $classes) {
        foreach ($classes as $token => $class) {
            expect(Illuminate\Support\Facades\Blade::render(sprintf($template, $token)))->toContain($class);
        }

        // 'huge' is not a token: the component falls back to its default look and never writes the word.
        expect(Illuminate\Support\Facades\Blade::render(sprintf($template, 'huge')))->not->toContain('huge');
    }
});

it('5. gives every form control a size', function () {
    $controls = ['form/input.blade.php', 'form/textarea.blade.php', 'form/checkbox.blade.php', 'form/toggle.blade.php', 'form/radio.blade.php', 'form/select.blade.php'];

    expect(array_values(array_diff($controls, conventionViewsWithProp('size'))))->toBe([]);
});

it('5. has a token, not a class, as the default of every size prop', function () {
    $offenders = [];

    foreach (conventionViewProps() as $path => $props) {
        foreach (['size', 'iconSize'] as $name) {
            if (isset($props[$name]) && preg_match('/^\'(xs|sm|md|lg|xl)\'/', $props[$name]) !== 1) {
                $offenders[$path] = $props[$name];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('5. never writes the value of size into a class: it always goes through a match', function () {
    // `$size` may appear in a view only as the subject of match($size); `{$size}` or `{{ $size }}` would be a 2.x class name.
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        $source = preg_replace('/@props\(\[.*?\n\]\)/s', '', $source);
        if (preg_match_all('/\$size\b/', $source) !== preg_match_all('/match\(\$size\)/', $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

// ── 2 (second half). Keys of configuration arrays: snake_case ───────────────

it('2. reads the keys of the item arrays in snake_case, as Laravel does', function () {
    // `$item['badge_color']`, not `$item['badgeColor']`. `$config` is excluded: it is the array that is
    // handed to the JavaScript, whose keys are camelCase there.
    $offenders = conventionOffenders(conventionViews(), '/\$(?!config\b)\w+\[\'[a-z]+[A-Z]\w*\'\]/');

    expect($offenders)->toBe([]);
});

it('2. takes the badge color of a menu item from badge_color, and the line number of a diff change from line_number', function () {
    expect(Illuminate\Support\Facades\Blade::render('<x-dbl::navigation.menu :items="[[\'label\' => \'A\', \'badge\' => 3, \'badge_color\' => \'error\']]" />'))->toContain('badge-error')
        ->and(Illuminate\Support\Facades\Blade::render('<x-dbl::display.diff :changes="[[\'type\' => \'add\', \'content\' => \'x\', \'line_number\' => 42]]" />'))->toContain('42')
        ->and(Illuminate\Support\Facades\Blade::render('<x-dbl::display.hover-gallery :images="[[\'src\' => \'/a.jpg\', \'view_url\' => \'/ver/1\']]" />'))->toContain('/ver/1');
});

// ── 6. Color: always a token, never the class ──────────────────────────────

/** The eight colors of DaisyUI. */
const CONVENTION_COLORS = ['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'];

it('6. takes the color of a button, a fab and a swap as a token, and turns it into its class', function () {
    foreach (['<x-dbl::actions.button label="X" color="%s" />', '<x-dbl::actions.fab color="%s" />', '<x-dbl::actions.swap color="%s" />'] as $template) {
        foreach ([...CONVENTION_COLORS, 'ghost', 'link'] as $color) {
            expect(Illuminate\Support\Facades\Blade::render(sprintf($template, $color)))->toContain("btn-{$color}");
        }

        // `btn-secondary` is the 2.x spelling: it is not a token, so it falls back to the default look.
        expect(Illuminate\Support\Facades\Blade::render(sprintf($template, 'btn-secondary')))->not->toContain('btn-secondary');
    }
});

it('6. calls the color prop of every component color, and none of them variant', function () {
    // button, fab, swap, badge, kbd, timeline: `variant` was a color there. chat-bubble (sent | received), the
    // heroicon of components.icon and the `variant` of the nodes of node-graph and flowchart are not colors.
    $withVariant = conventionViewsWithProp('variant');

    expect($withVariant)->toBe(['components/icon.blade.php', 'display/chat-bubble.blade.php']);
});

it('6. has one name for a background: background, with a token', function () {
    expect(conventionViewsWithProp('background'))->toBe(['display/card.blade.php', 'display/collapse.blade.php', 'display/stat.blade.php', 'layout/footer.blade.php'])
        ->and(array_merge(conventionViewsWithProp('backgroundColor'), conventionViewsWithProp('bgClass'), conventionViewsWithProp('borderColor')))->toBe([]);
});

it('6. paints a card, a stat and a footer with the class of the background token, and an unknown one with the default', function () {
    $templates = [
        '<x-dbl::display.card background="%s">x</x-dbl::display.card>',
        '<x-dbl::display.stat title="T" background="%s" />',
        '<x-dbl::layout.footer background="%s">x</x-dbl::layout.footer>',
    ];

    foreach ($templates as $template) {
        foreach ([...CONVENTION_COLORS, 'base-100', 'base-200', 'base-300'] as $background) {
            expect(Illuminate\Support\Facades\Blade::render(sprintf($template, $background)))->toContain("bg-{$background}");
        }

        expect(Illuminate\Support\Facades\Blade::render(sprintf($template, 'bg-primary')))->not->toContain('bg-bg-primary')
            ->and(Illuminate\Support\Facades\Blade::render(sprintf($template, 'magenta')))->not->toContain('magenta');
    }
});

it('6. does not mount bg-{$background} or border-{$borderColor} in the card, and keeps the border in class', function () {
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::display.card class="border border-primary">x</x-dbl::display.card>');

    expect($html)->toContain('border border-primary');
});

it('6. paints the badge, the tooltip, the collapse and the timeline with the class of their color token', function () {
    foreach (CONVENTION_COLORS as $color) {
        expect(Illuminate\Support\Facades\Blade::render("<x-dbl::feedback.tooltip text=\"t\" color=\"{$color}\" />"))->toContain("text-{$color}")
            ->and(Illuminate\Support\Facades\Blade::render("<x-dbl::display.collapse title=\"t\" background=\"{$color}\">x</x-dbl::display.collapse>"))->toContain("bg-{$color}/10")
            ->and(Illuminate\Support\Facades\Blade::render("<x-dbl::display.timeline :items=\"[['title' => 'a', 'color' => '{$color}', 'tags' => [['label' => 'z', 'color' => '{$color}']]]]\" />"))
            ->toContain("bg-{$color}")->toContain("badge-{$color}");
    }
});

it('6. has no card action that takes a class as its color', function () {
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::display.card :actions="[[\'label\' => \'Ver\', \'url\' => \'/x\', \'color\' => \'error\']]">x</x-dbl::display.card>');

    expect($html)->toContain('btn-error')->and(Illuminate\Support\Facades\Blade::render('<x-dbl::display.card :actions="[[\'label\' => \'Ver\']]">x</x-dbl::display.card>'))->toContain('btn-primary');
});

// ── 7. `class` and the other attributes go to the root ─────────────────────

it('7. has no view that declares class or containerClass as a prop, or reads $class', function () {
    // `class` is not a prop: it stays in $attributes and Blade merges it into the root, as in any Laravel component.
    $offenders = [];

    foreach (conventionViewProps() as $path => $props) {
        foreach (['class', 'containerClass', 'htmlClass', 'bgClass', 'tooltipClass'] as $old) {
            if (array_key_exists($old, $props)) {
                $offenders[$path][] = $old;
            }
        }
    }

    expect($offenders)->toBe([])
        ->and(conventionOffenders(conventionViews(), '/\$(?:class|containerClass)\b/'))->toBe([]);
});

it('7. names the class of an inner part <part>-class', function () {
    // Every prop that ends in Class is `<part>Class` (kebab-case in the tag: `input-class`, `dropzone-class`...).
    $names = [];

    foreach (conventionViewProps() as $props) {
        foreach (array_keys($props) as $name) {
            if (str_ends_with($name, 'Class')) {
                $names[$name] = true;
            }
        }
    }

    // The ones the brief names, among the others.
    expect(array_keys($names))->toContain('inputClass', 'canvasClass', 'dropzoneClass')
        ->and(array_filter(array_keys($names), fn ($name) => preg_match('/^[a-z]+(?:[A-Z][a-z]+)*Class$/', $name) !== 1))->toBe([]);
});

it('7. applies $attributes in every view', function () {
    $without = array_keys(array_filter(conventionViews(), fn (string $source) => ! str_contains($source, '$attributes')));

    expect($without)->toBe([]);
});

/** The attributes each view needs to render at all (its required props), by path. Everything else renders with none. */
function conventionMinimalProps(): array
{
    return [
        'components/icon.blade.php' => 'name="home"',
        'display/calendar.blade.php' => 'load-url="/e"',
        'display/flowchart.blade.php' => '',
        'form/kv-editor.blade.php' => 'name="k"',
        'form/list-editor.blade.php' => 'name="l"',
        'form/validator.blade.php' => 'message="Mal"',
        'form/rich-text.blade.php' => 'name="r"',
        'layout/indicator.blade.php' => 'label="1"',
        'navigation/sidebar-tree.blade.php' => ':items="[[\'label\' => \'A\']]"',
    ];
}

/** The view components that cannot be rendered as a tag with no content. */
function conventionTagOf(string $path): string
{
    return 'dbl::'.str_replace('/', '.', preg_replace('/\.blade\.php$/', '', $path));
}

it('7. puts class and every other attribute on the root element of each component', function () {
    $this->withoutVite();   // the layouts call @vite, and testbench has no compiled manifest
    $minimal = conventionMinimalProps();
    $offenders = [];

    foreach (array_keys(conventionViews()) as $path) {
        $tag = conventionTagOf($path);
        $html = Illuminate\Support\Facades\Blade::render("<x-{$tag} ".($minimal[$path] ?? '').' class="probe-class" data-probe="yes">x</x-'.$tag.'>');

        // The first element that is not a <style>, <script> or <template> (some components print their CSS first).
        // (A `>` inside a quoted attribute, such as the `=>` of an x-data, does not end the tag.)
        preg_match('/<(?!\/|!|style\b|script\b|template\b)[a-z](?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', $html, $root);

        if (! isset($root[0]) || ! str_contains($root[0], 'data-probe="yes"') || ! str_contains($root[0], 'probe-class')) {
            $offenders[$path] = $root[0] ?? '(no element)';
        }
    }

    expect($offenders)->toBe([]);
});

it('7. sends the binding of the value (x-model) to the control, and the rest to the root', function () {
    $options = ':options="[\'a\' => \'A\']"';

    foreach (['input' => '', 'textarea' => '', 'checkbox' => '', 'toggle' => '', 'radio' => $options, 'select' => $options] as $control => $extra) {
        $html = Illuminate\Support\Facades\Blade::render("<x-dbl::form.{$control} name=\"f\" {$extra} x-model=\"form.f\" data-probe=\"yes\" class=\"probe-class\" />");

        preg_match_all('/<(?:div|input|textarea|select)\b[^>]*>/', $html, $tags);
        $withModel = array_values(array_filter($tags[0], fn ($t) => str_contains($t, 'x-model="form.f"')));
        $withProbe = array_values(array_filter($tags[0], fn ($t) => str_contains($t, 'data-probe="yes"') && str_contains($t, 'probe-class')));

        expect($withModel)->not->toBe([], "{$control}: x-model not found")
            ->and($withModel[0])->not->toStartWith('<div', "{$control}: x-model on the root")
            ->and($withProbe[0])->toStartWith('<div', "{$control}: class not on the root");
    }
});

it('7. gives a part of a component its own class prop', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect($render('<x-dbl::form.input name="x" input-class="mi-input" class="mi-raiz" />'))
        ->toMatch('/<div[^>]*mi-raiz/')->toMatch('/<input[^>]*mi-input/')
        ->and($render('<x-dbl::form.upload name="f" upload-url="/u" dropzone-class="mi-zona" class="mi-raiz" />'))
        ->toMatch('/<div[^>]*mi-raiz/')->toMatch('/<div[^>]*mi-zona/')
        ->and($render('<x-dbl::display.flowchart canvas-class="mi-lienzo" class="mi-raiz" />'))
        ->toMatch('/<div[^>]*mi-raiz/')->toMatch('/<div[^>]*mi-lienzo/');
});

it('7. gives the attributes of a page layout to its <html>', function () {
    $this->withoutVite();
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::layout.app class="dark-ish" data-x="1" />');

    expect($html)->toMatch('/<html[^>]*class="dark-ish"/')->toMatch('/<html[^>]*data-x="1"/');
});
