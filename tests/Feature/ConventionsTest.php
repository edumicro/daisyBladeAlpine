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

// ── 7. `class` and the other attributes go to the main element of the component ──
//
// The main element of a component is its root, except in a form control: there it is the control itself
// (the <input>, the <textarea>, the <select>...), and the box around it takes `wrapper-class`.

/** The form controls: the components that paint one named field, with its label, hint and error around it. */
const CONVENTION_CONTROLS = [
    'form/input.blade.php', 'form/textarea.blade.php', 'form/select.blade.php', 'form/checkbox.blade.php', 'form/radio.blade.php',
    'form/toggle.blade.php', 'form/rich-text.blade.php', 'form/signature.blade.php', 'form/upload.blade.php',
];

it('7. has no view that declares class or containerClass as a prop, or reads $class', function () {
    // `class` is not a prop: it stays in $attributes and Blade merges it into the main element, as in any Laravel component.
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
    // Every prop that ends in Class is `<part>Class` (kebab-case in the tag: `wrapper-class`, `canvas-class`, `table-class`...).
    $names = [];

    foreach (conventionViewProps() as $props) {
        foreach (array_keys($props) as $name) {
            if (str_ends_with($name, 'Class')) {
                $names[$name] = true;
            }
        }
    }

    expect(array_keys($names))->toContain('wrapperClass', 'canvasClass', 'tableClass')
        ->and(array_filter(array_keys($names), fn ($name) => preg_match('/^[a-z]+(?:[A-Z][a-z]+)*Class$/', $name) !== 1))->toBe([]);
});

it('7. gives every form control a wrapper-class for its box, and no <control>-class: the class of the control is class', function () {
    $without = array_values(array_diff(CONVENTION_CONTROLS, conventionViewsWithProp('wrapperClass')));
    $old = [];

    foreach (['inputClass', 'textareaClass', 'selectClass', 'checkboxClass', 'radioClass', 'toggleClass', 'dropzoneClass'] as $name) {
        $old = [...$old, ...conventionViewsWithProp($name)];
    }

    expect($without)->toBe([])->and($old)->toBe([]);
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
        'display/data-table.blade.php' => 'load-url="/r"',
        'display/flowchart.blade.php' => '',
        'display/resource-details.blade.php' => 'load-url="/r"',
        'form/kv-editor.blade.php' => 'name="k"',
        'form/list-editor.blade.php' => 'name="l"',
        'form/validator.blade.php' => 'message="Mal"',
        'form/rich-text.blade.php' => 'name="r"',
        'form/radio.blade.php' => ':options="[\'a\' => \'A\']"',
        'form/select.blade.php' => ':options="[\'a\' => \'A\']"',
        'form/upload.blade.php' => 'upload-url="/u"',
        'import/spreadsheet.blade.php' => 'upload-url="/u"',
        'layout/indicator.blade.php' => 'label="1"',
        'navigation/sidebar-tree.blade.php' => ':items="[[\'label\' => \'A\']]"',
        'sections/auto-form.blade.php' => 'submit-url="/s"',
        'sections/wizard.blade.php' => 'submit-url="/s"',
    ];
}

/** The view components that cannot be rendered as a tag with no content. */
function conventionTagOf(string $path): string
{
    return 'dbl::'.str_replace('/', '.', preg_replace('/\.blade\.php$/', '', $path));
}

/** Every opening tag of `$html` that is not a <style>, <script> or <template>. A `>` inside a quoted attribute (the `=>` of an x-data) does not end a tag. */
function conventionOpeningTags(string $html): array
{
    preg_match_all('/<(?!\/|!|style\b|script\b|template\b)[a-z](?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', $html, $tags);

    return $tags[0];
}

it('7. puts class and every other attribute on the root element of each component that is not a form control', function () {
    $this->withoutVite();   // the layouts call @vite, and testbench has no compiled manifest
    $minimal = conventionMinimalProps();
    $offenders = [];

    foreach (array_keys(conventionViews()) as $path) {
        if (in_array($path, CONVENTION_CONTROLS, true)) {
            continue;
        }

        $tag = conventionTagOf($path);
        $html = Illuminate\Support\Facades\Blade::render("<x-{$tag} ".($minimal[$path] ?? '').' class="probe-class" data-probe="yes">x</x-'.$tag.'>');
        $root = conventionOpeningTags($html)[0] ?? '(no element)';

        if (! str_contains($root, 'data-probe="yes"') || ! str_contains($root, 'probe-class')) {
            $offenders[$path] = $root;
        }
    }

    expect($offenders)->toBe([]);
});

it('7. puts class and every other attribute of a form control on the control, and wrapper-class on its box', function () {
    $minimal = conventionMinimalProps();
    // The element that must carry them: a native control, or (rich-text, signature, upload) the box that is the control.
    $control = [
        'form/input.blade.php' => '<input', 'form/textarea.blade.php' => '<textarea', 'form/select.blade.php' => '<select',
        'form/checkbox.blade.php' => '<input', 'form/radio.blade.php' => '<input', 'form/toggle.blade.php' => '<input',
        'form/rich-text.blade.php' => '<div', 'form/signature.blade.php' => '<div', 'form/upload.blade.php' => '<div',
    ];
    $offenders = [];

    foreach (CONVENTION_CONTROLS as $path) {
        $tag = conventionTagOf($path);
        $html = Illuminate\Support\Facades\Blade::render("<x-{$tag} name=\"f\" ".($minimal[$path] ?? '').' class="probe-class" wrapper-class="probe-wrapper" data-probe="yes" x-model="form.f" />');
        $tags = conventionOpeningTags($html);
        $root = $tags[0];
        $carrying = array_values(array_filter($tags, fn ($t) => str_contains($t, 'data-probe="yes"')));

        $ok = count($carrying) === 1
            && str_starts_with($carrying[0], $control[$path])
            && $carrying[0] !== $root
            && str_contains($carrying[0], 'probe-class') && str_contains($carrying[0], 'x-model="form.f"')
            && str_contains($root, 'probe-wrapper') && ! str_contains($root, 'probe-class') && ! str_contains($root, 'x-model');

        if (! $ok) {
            $offenders[$path] = ['root' => $root, 'carrying' => $carrying];
        }
    }

    expect($offenders)->toBe([]);
});

it('7. gives the attributes of a select to its <select> in every mode, and to the text box in the searchable one', function () {
    $options = ':options="[\'a\' => \'A\']"';

    foreach (['' => '<select', 'load-url="/o"' => '<select', 'searchable' => '<input'] as $mode => $expected) {
        $html = Illuminate\Support\Facades\Blade::render("<x-dbl::form.select name=\"f\" {$options} {$mode} class=\"probe-class\" data-probe=\"yes\" />");
        $carrying = array_values(array_filter(conventionOpeningTags($html), fn ($t) => str_contains($t, 'data-probe="yes"')));

        expect($carrying)->toHaveCount(1, "mode [{$mode}]")->and($carrying[0])->toStartWith($expected)->toContain('probe-class');
    }
});

it('7. lets a number input keep its min, max, step and autocomplete, as in Breeze', function () {
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::form.input name="qty" type="number" min="0" max="9" step="0.5" autocomplete="off" x-model="qty" />');

    expect($html)->toMatch('/<input[^>]*type="number"[^>]*min="0"[^>]*max="9"[^>]*step="0.5"[^>]*autocomplete="off"[^>]*x-model="qty"/s');
});

it('7. links the label to the id the caller writes on a control, or to its name', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    foreach (['input', 'textarea'] as $control) {
        expect($render("<x-dbl::form.{$control} name=\"a\" label=\"A\" />"))->toContain('for="a"')->toContain('id="a"')
            ->and($render("<x-dbl::form.{$control} name=\"a\" label=\"A\" id=\"mine\" />"))->toContain('for="mine"')->toContain('id="mine"')->not->toContain('id="a"');
    }
});

it('7. gives the box of a control its own class', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect($render('<x-dbl::form.input name="x" wrapper-class="mi-caja" class="mi-control" />'))
        ->toMatch('/<div class="w-full mi-caja">/')->toMatch('/<input[^>]*mi-control/')
        ->and($render('<x-dbl::display.flowchart canvas-class="mi-lienzo" class="mi-raiz" />'))
        ->toMatch('/<div[^>]*mi-raiz/')->toMatch('/<div[^>]*mi-lienzo/');
});

it('7. leaves the editors (kv-editor, list-editor, repeater) with their attributes on the root, which is their main element', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    foreach (['kv-editor', 'list-editor', 'repeater'] as $editor) {
        expect(conventionOpeningTags($render("<x-dbl::form.{$editor} name=\"e\" class=\"probe-class\" />"))[0])->toContain('probe-class');
    }
});

it('7. gives the attributes of a page layout to its <html>', function () {
    $this->withoutVite();
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::layout.app class="dark-ish" data-x="1" />');

    expect($html)->toMatch('/<html[^>]*class="dark-ish"/')->toMatch('/<html[^>]*data-x="1"/');
});

// ── 8. Events: dbl-<component>-<what happened>, and dbl-<component>-<verb> for orders ──

/**
 * The literal names a source file passes to `$dispatch(...)`, including both sides of a ternary
 * (`$dispatch(empty ? 'a' : 'b', ...)`). A name made at run time (`$dispatch('{{ $item['action'] }}')`) is not literal.
 */
function conventionDispatchedNames(string $source): array
{
    $names = [];

    preg_match_all('/\$dispatch\(([^,)]*)/', $source, $calls);

    foreach ($calls[1] as $argument) {
        if (str_contains($argument, '{{') || str_contains($argument, '@js(')) {
            continue;
        }

        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $argument, $quoted);
        $names = [...$names, ...$quoted[1]];
    }

    return array_values(array_unique($names));
}

it('8. gives every event a dbl- prefix, in the views and in the scripts', function () {
    $offenders = [];

    foreach ([...conventionViews(), ...conventionScripts()] as $path => $source) {
        // $dispatch(...) (also the two sides of a ternary), new CustomEvent('...') and this.emit('...') with a literal name.
        preg_match_all('/(?:new CustomEvent|\bemit)\(\s*[\'"]([^\'"{]+)[\'"]/', $source, $found);
        foreach ([...conventionDispatchedNames($source), ...$found[1]] as $name) {
            if (! str_starts_with($name, 'dbl-')) {
                $offenders[$path][] = $name;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('8. names an announced event after its own component: dbl-<component>-<what happened>', function () {
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        $component = preg_replace('/\.blade\.php$/', '', basename($path));

        foreach (conventionDispatchedNames($source) as $name) {
            if (! str_starts_with($name, "dbl-{$component}-")) {
                $offenders[$path][] = $name;
            }
        }
    }

    // The scripts: calendar.js is the calendar, rich-text.js the rich text... The one order that goes to another component
    // is flowchart's `dbl-toast-show` (to feedback.toast).
    // daisyblade.js holds many factories: the three that announce something are the select, the steps and the repeater.
    $scripts = ['components/calendar.js' => ['calendar'], 'components/map.js' => ['map'], 'components/upload.js' => ['upload'],
        'components/signature.js' => ['signature'], 'components/rich-text.js' => ['rich-text'], 'daisyblade.js' => ['select', 'steps', 'repeater']];

    foreach ($scripts as $path => $components) {
        foreach (conventionDispatchedNames(conventionScripts()[$path]) as $name) {
            if (array_filter($components, fn (string $component) => str_starts_with($name, "dbl-{$component}-")) === []) {
                $offenders[$path][] = $name;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('8. ends the name of an announced event in a participle, never in a bare verb', function () {
    $names = [];

    foreach ([...conventionViews(), ...conventionScripts()] as $source) {
        $names = [...$names, ...conventionDispatchedNames($source)];
        preg_match_all('/\bemit\(\s*[\'"](dbl-[^\'"]+)[\'"]/', $source, $found);
        $names = [...$names, ...$found[1]];
    }

    // The orders (dbl-toast-show, dbl-sidebar-toggle) are verbs: they are not announcements.
    $names = array_values(array_unique(array_diff($names, ['dbl-toast-show', 'dbl-sidebar-toggle'])));

    // changed, clicked, moved, created, deleted, uploaded, removed, signed, cleared, picked: all end in -ed.
    expect(array_values(array_filter($names, fn (string $name) => ! str_ends_with($name, 'ed'))))->toBe([])
        ->and($names)->toContain('dbl-select-changed', 'dbl-pagination-page-changed', 'dbl-steps-step-changed', 'dbl-upload-removed', 'dbl-signature-signed', 'dbl-node-graph-node-clicked', 'dbl-node-graph-edge-requested');
});

it('8. says -requested when the parent decides whether something happens, and keeps the past for what already happened', function () {
    // flowchart: accept / reject. node-graph: the component draws nothing new until the parent answers by changing its nodes.
    $requests = [
        'display/node-graph.blade.php' => ['dbl-node-graph-node-create-requested', 'dbl-node-graph-node-delete-requested',
            'dbl-node-graph-badge-create-requested', 'dbl-node-graph-badge-delete-requested', 'dbl-node-graph-edge-requested'],
    ];
    $happened = ['dbl-node-graph-node-clicked', 'dbl-node-graph-badge-clicked'];

    $names = conventionDispatchedNames(conventionViews()['display/node-graph.blade.php']);
    expect($names)->toContain(...$requests['display/node-graph.blade.php'], ...$happened)
        ->and(array_values(array_filter($names, fn (string $name) => str_ends_with($name, '-created') || str_ends_with($name, '-deleted'))))->toBe([]);

    // The flowchart emits through emit(): the two that carry accept / reject are requests, the clicks are not.
    $script = conventionScripts()['components/flowchart.js'];
    preg_match_all('/\bemit\(\s*\'(dbl-flowchart-[a-z-]+)\'/', $script, $emitted);
    expect(array_unique($emitted[1]))->toContain('dbl-flowchart-node-move-requested', 'dbl-flowchart-edge-requested', 'dbl-flowchart-node-clicked', 'dbl-flowchart-edge-clicked')
        ->and($script)->not->toContain('dbl-flowchart-node-moved')->not->toContain('dbl-flowchart-edge-created');
});

it('8. passes an object (or nothing, which Alpine turns into one) as the detail of every event the views dispatch', function () {
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        // $dispatch('name', X): X has to open an object literal.
        if (preg_match_all('/\$dispatch\(\s*[\'"][^\'"]+[\'"]\s*,\s*([^{\s])/', $source, $found)) {
            $offenders[$path] = $found[1];
        }
    }

    expect($offenders)->toBe([]);
});

it('8. listens to the orders of the window with the dbl-<component>-<verb> names', function () {
    $this->withoutVite();
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect($render('<x-dbl::actions.modal id="m">x</x-dbl::actions.modal>'))
        ->toContain('@dbl-modal-open.window=')->toContain('@dbl-modal-close.window=')
        ->and($render('<x-dbl::feedback.toast />'))->toContain('@dbl-toast-show.window=')
        ->and($render('<x-dbl::navigation.sidebar>x</x-dbl::navigation.sidebar>'))->toContain('@dbl-sidebar-toggle.window=')
        ->and($render('<x-dbl::display.map />'))->toContain('x-on:dbl-map-focus.window=')
        ->and($render('<x-dbl::display.flowchart />'))
        ->toContain('x-on:dbl-flowchart-set.window=')->toContain('x-on:dbl-flowchart-focus.window=')
        ->toContain('x-on:dbl-flowchart-remove-edge.window=')->toContain('x-on:dbl-flowchart-remove-node.window=');
});

it('8. does not listen to a window event without the prefix, unless it is a native one', function () {
    // Names of the browser (keydown, change, resize...) are not ours and keep their name.
    $native = ['keydown', 'keyup', 'change', 'input', 'click', 'resize', 'scroll', 'focus', 'blur'];
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        preg_match_all('/(?:@|x-on:)([a-z][a-z-]*)(?:\.[a-z]+)*\.window=/', $source, $found);
        foreach ($found[1] as $name) {
            if (! str_starts_with($name, 'dbl-') && ! in_array($name, $native, true)) {
                $offenders[$path][] = $name;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('8. makes the native select announce dbl-select-changed with { name, value }, like the searchable and the remote ones', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect($render('<x-dbl::form.select name="a" :options="[1 => \'x\']" />'))->toContain("x-on:change=\"\$dispatch('dbl-select-changed', { name: 'a'")
        ->and($render('<x-dbl::form.select name="a" :options="[1 => \'x\']" searchable />'))->toContain('dblSelectSearch(')->toContain('choose(item)')
        ->and($render('<x-dbl::form.select name="a" load-url="/o" />'))->toContain('dblSelectRemote(')->toContain('\u0022name\u0022:\u0022a\u0022');
});

it('8. lets the dock and the menu dispatch the event the application names, which is not ours to prefix', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect($render('<x-dbl::navigation.menu :items="[[\'label\' => \'A\', \'action\' => \'mi-evento\']]" />'))->toContain("\$dispatch('mi-evento')")
        ->and($render('<x-dbl::navigation.dock :items="[[\'label\' => \'A\', \'action\' => \'dbl-sidebar-toggle\']]" />'))->toContain("\$dispatch('dbl-sidebar-toggle')");
});

// ── 9. One filter component: form.filters ──────────────────────────────────

it('9. has one filter component, x-dbl::form.filters, and neither of the two old ones', function () {
    $paths = array_keys(conventionViews());

    expect(array_values(array_filter($paths, fn (string $path) => str_contains($path, 'filter'))))->toBe(['form/filters.blade.php'])
        ->and(conventionViewsWithProp('filters'))->toBe(['display/data-table.blade.php', 'display/map.blade.php', 'form/filters.blade.php']);
});

it('9. speaks one vocabulary in form.filters: filters[key], with dbl-filters-applied and dbl-filters-cleared, both with { filters }', function () {
    $source = conventionViews()['form/filters.blade.php'];

    expect(conventionProps($source))->toHaveKeys(['filters', 'icon', 'position', 'label'])
        ->and(array_keys(conventionProps($source)))->not->toContain('fields', 'applyUrl', 'method')
        ->and($source)->toContain("\$dispatch('dbl-filters-applied', { filters: this.filters })")
        ->toContain("\$dispatch('dbl-filters-cleared', { filters: {} })");
});

it('9. makes data-table listen to the two events of form.filters and to no other window event', function () {
    $source = conventionViews()['display/data-table.blade.php'];

    preg_match_all('/@([a-z-]+)\.window=/', $source, $listeners);

    expect($listeners[1])->toBe(['dbl-filters-applied', 'dbl-filters-cleared']);
});

it('9. lets the data-table filter with what form.filters announces', function () {
    $this->withoutVite();
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[]" :filters="[[\'key\' => \'status\', \'type\' => \'select\']]" />');

    // The table carries the panel inside it, and listens to what the panel says.
    expect($html)->toContain("\$dispatch('dbl-filters-applied'")->toContain('@dbl-filters-applied.window="applyFilters($event.detail.filters)"');
});

// ── 10. The configuration reaches the JavaScript in one way: x-data="dblName(@js($config))" ──

/** The value of every x-data="..." attribute of a view (the attribute may span several lines). */
function conventionXData(string $source): array
{
    preg_match_all('/\sx-data="([^"]*)"/s', $source, $found);

    return $found[1];
}

it('10. starts a component with a factory and @js($config), or with an inline object that has no PHP data in it', function () {
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        foreach (conventionXData($source) as $value) {
            $factoryCall = preg_match('/^dbl[A-Z]\w*\((?:@js\(.+\))?\)$/s', trim($value)) === 1;
            $inlineWithoutPhp = str_starts_with(trim($value), '{') && ! preg_match('/\{\{|@js|@json|Js::from|<\?php/', $value);

            if (! $factoryCall && ! $inlineWithoutPhp) {
                $offenders[$path][] = trim($value);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('10. has no JSON.parse of a data attribute, no data-config and no Js::from in the views', function () {
    expect(conventionOffenders(conventionViews(), '/dataset\.config|data-config|Js::from|@json/'))->toBe([]);
});

it('10. never glues a PHP value inside quotes into the JavaScript of an attribute', function () {
    // '{{ $key }}' breaks as soon as the value has a quote. Whole numbers and the closed lists of classes of the
    // component itself are safe; what the user writes (keys, ids, URLs, event names) goes through @js().
    $safe = ['num', 'i', 'bgColorClass', 'stepColorClass', 'maxLength'];
    $offenders = [];

    foreach (conventionViews() as $path => $source) {
        // Only inside the value of an Alpine attribute: x-*, :attr, ::attr, @event.
        preg_match_all('/\s(?:x-[\w:.-]+|:{1,2}[\w.-]+|@[\w.:-]+)="([^"]*)"/s', $source, $attributes);

        foreach ($attributes[1] as $javascript) {
            preg_match_all('/[\'`]\{\{\s*\(?(?:int\)\s*)?\$(\w+)/', $javascript, $found);

            foreach ($found[1] as $variable) {
                if (! in_array($variable, $safe, true)) {
                    $offenders[$path][] = $variable;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('10. registers a factory for every dbl factory a view starts', function () {
    $registered = [];

    // daisyblade.js: `const _all = { dblModal, dblToast... }`; the components: Alpine.data('dblCalendar', ...).
    preg_match('/const _all = \{(.*?)\n\}/s', conventionScripts()['daisyblade.js'], $all);
    preg_match_all('/dbl[A-Z]\w*/', $all[1], $names);
    $registered = $names[0];

    foreach (conventionScripts() as $source) {
        preg_match_all('/Alpine\.data\(\'(dbl[A-Z]\w*)\'/', $source, $names);
        $registered = [...$registered, ...$names[1]];
    }

    $started = [];
    foreach (conventionViews() as $path => $source) {
        foreach (conventionXData($source) as $value) {
            if (preg_match('/^(dbl[A-Z]\w*)\(/', trim($value), $factory)) {
                $started[$path] = $factory[1];
            }
        }
    }

    expect(array_values(array_diff($started, $registered)))->toBe([])
        ->and($started)->toHaveCount(count(array_filter($started)));
});

it('10. passes a URL, a key or a text with quotes to the JavaScript without breaking it', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);
    $tricky = "/a'b\"c?x=1&y=2";

    expect(xDataConfig(Illuminate\Support\Facades\Blade::render('<x-dbl::display.data-table :load-url="$url" :columns="[]" />', ['url' => $tricky]), 'dblDataTable')['loadUrl'])->toBe($tricky)
        ->and(xDataConfig($render('<x-dbl::sections.wizard :steps="[]" submit-url="/it\'s" form-id="o\'k" />'), 'dblWizard'))->toMatchArray(['submitUrl' => "/it's", 'formId' => "o'k"])
        ->and(xDataConfig($render('<x-dbl::display.resource-details load-url="/r\'s" />'), 'dblResourceDetails')['loadUrl'])->toBe("/r's");
});

it('10. gives the factories of the static components their configuration the same way', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect(xDataConfig($render('<x-dbl::navigation.steps :steps="[[\'label\' => \'A\'], [\'label\' => \'B\']]" :current-step="2" />'), 'dblSteps'))->toBe(['current' => 2, 'total' => 2])
        ->and(xDataConfig($render('<x-dbl::display.carousel :items="[[\'image\' => \'/a.jpg\'], [\'image\' => \'/b.jpg\']]" />'), 'dblCarousel'))->toBe(['total' => 2])
        ->and(xDataConfig($render('<x-dbl::display.text-rotate :texts="[\'a\', \'b\']" :duration="1000" />'), 'dblTextRotate'))->toBe(['texts' => ['a', 'b'], 'duration' => 1000, 'autoRotate' => true])
        ->and(xDataConfig($render('<x-dbl::feedback.toast message="Hola" :auto-close="true" :duration="2500" />'), 'dblToastSingle'))->toBe(['duration' => 2500, 'autoClose' => true])
        ->and(xDataConfig($render('<x-dbl::form.select name="a" :options="[1 => \'x\']" searchable value="1" />'), 'dblSelectSearch'))->toBe(['name' => 'a', 'search' => 'x', 'options' => [1 => 'x']]);
});

// ── 12. The wizard: the saved progress does not overwrite the values of the Blade ──

it('12. hands the values of every step to the wizard factory, as an object even when there are none', function () {
    $render = fn (string $blade) => Illuminate\Support\Facades\Blade::render($blade);

    expect(xDataConfig($render('<x-dbl::sections.wizard :steps="[]" submit-url="/s" />'), 'dblWizard')['values'])->toBe([])
        ->and($render('<x-dbl::sections.wizard :steps="[]" submit-url="/s" />'))->toContain('\\u0022values\\u0022:{}');
});

// ── 11. Cleaning ───────────────────────────────────────────────────────────

it('11. keeps in the configuration only what the package reads: map and geo', function () {
    expect(array_keys(require __DIR__.'/../../config/daisyblade.php'))->toBe(['map', 'geo']);

    // And nothing reads the keys that were dead: component_map, defaults, toast, sidebar.
    $sources = [...conventionViews(), ...conventionScripts()];
    foreach (glob(__DIR__.'/../../src/{,*/,*/*/}*.php', GLOB_BRACE) as $file) {
        $sources[$file] = file_get_contents($file);
    }

    expect(conventionOffenders($sources, '/daisyblade\.(?:component_map|defaults|toast|sidebar)/'))->toBe([]);
});

it('11. has no dbTabs, the factory nobody used, in any spelling', function () {
    expect(conventionOffenders([...conventionViews(), ...conventionScripts()], '/\bdbTabs\b/'))->toBe([]);
});

it('1. names the ids that the components make with the dbl prefix too', function () {
    // The flowchart builds an id for its keyboard help: `dbl-flowchart-<random>-help`, not the 2.x `dbf-<random>-help`.
    $html = Illuminate\Support\Facades\Blade::render('<x-dbl::display.flowchart :editable="true" />');

    expect($html)->toMatch('/id="dbl-flowchart-[A-Za-z0-9]+-help"/')
        ->and(conventionOffenders(conventionViews(), '/[\'"]db(?!l-)[a-z]?-[\w-]*[\'"]/'))->toBe([]);
});
