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
