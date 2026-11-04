<?php

use Illuminate\Support\Facades\Blade;

/**
 * Cause 7: the configuration of a view and the one of its factory used to be compared by nobody. The sidebar never received
 * `collapsible`, and `collapsible="false"` did nothing.
 *
 * Now every factory declares, at its top, the keys it reads and the default of each one (`withConfig()` in factoryConfig.js),
 * and this test compares that declaration with what each view passes in `x-data="dblName(@js($config))"`:
 *   - no key that the view passes and the factory does not declare (it would be read by nobody);
 *   - no key that the factory declares and the view does not pass (it would stay at its default for ever), except the ones the
 *     factory marks as `optional` because only JavaScript can give them (a callback such as `onSuccess`).
 */

/** What every factory declares: { dblName: { defaults: {...}, optional: [...] } }, read by node from the real files. */
function declaredFactories(): array
{
    $root = realpath(__DIR__.'/../../resources/js');
    $files = ['daisyblade', 'components/calendar', 'components/flowchart', 'components/map', 'components/rich-text', 'components/signature', 'components/upload'];

    // One `import * as m0 from '...'` per file; then every export whose name starts with `dbl` and that declares its configuration.
    $imports = collect($files)->map(fn ($file, $index) => "import * as m{$index} from '{$root}/{$file}.js'; ")->implode('');
    $modules = collect(array_keys($files))->map(fn ($index) => "m{$index}")->implode(', ');
    $script = $imports."const out = {}; for (const module of [{$modules}]) for (const [name, factory] of Object.entries(module)) "
        .'if (name.startsWith("dbl") && factory?.declared) out[name] = factory.declared; console.log(JSON.stringify(out))';

    $output = shell_exec('node --input-type=module -e '.escapeshellarg($script).' 2>/dev/null');
    expect($output)->not->toBeEmpty('node could not read the factories');

    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

/** For every factory of the package, some Blade that renders its view (the more ways to render it, the more keys are seen). */
function viewsOfTheFactories(): array
{
    return [
        'dblModal'            => ['<x-dbl::actions.modal id="m">x</x-dbl::actions.modal>'],
        'dblToast'            => ['<x-dbl::feedback.toast />'],
        'dblToastSingle'      => ['<x-dbl::feedback.toast message="x" />'],
        'dblTabs'             => ['<x-dbl::navigation.tabs :items="[[\'label\' => \'A\']]" />'],
        'dblSteps'            => ['<x-dbl::navigation.steps :steps="[[\'label\' => \'A\']]" />'],
        'dblCarousel'         => ['<x-dbl::display.carousel :items="[[\'image\' => \'/a.png\']]" />'],
        'dblTextRotate'       => ['<x-dbl::display.text-rotate :texts="[\'a\', \'b\']" />'],
        'dblRepeater'         => ['<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\']]" />'],
        'dblSelectSearch'     => ['<x-dbl::form.select name="c" searchable :options="[1 => \'x\']" />'],
        'dblCharacterCounter' => ['<x-dbl::form.textarea name="a" label="A" max-length="10" />'],
        'dblSelectAnnouncer'  => ['<x-dbl::form.select name="c" :options="[1 => \'x\']" />'],
        'dblNavbar'           => ['<x-dbl::navigation.navbar />'],
        'dblSidebar'          => ['<x-dbl::navigation.sidebar>x</x-dbl::navigation.sidebar>', '<x-dbl::navigation.sidebar :collapsible="false">x</x-dbl::navigation.sidebar>'],
        'dblSidebarTree'      => ['<x-dbl::navigation.sidebar-tree :items="[[\'label\' => \'A\', \'children\' => [[\'label\' => \'B\']]]]" />'],
        'dblSelectRemote'     => ['<x-dbl::form.select name="c" load-url="/o" />', '<x-dbl::form.select name="c" load-url="/o" depends-on="d" multiple value="1" disabled />'],
        'dblResourceDetails'  => ['<x-dbl::display.resource-details load-url="/r" :fields="[\'a\']" />'],
        'dblTabsLazy'         => ['<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/t\']]" />'],
        'dblSpreadsheetImport' => ['<x-dbl::import.spreadsheet upload-url="/i" accept=".csv" chunk-size="100" />'],
        'dblDataTable'        => ['<x-dbl::display.data-table load-url="/r" :columns="[[\'key\' => \'id\', \'label\' => \'Id\']]" />'],
        'dblAutoForm'         => ['<x-dbl::sections.auto-form submit-url="/s" :fields="[\'name\' => [\'type\' => \'text\']]" />'],
        'dblWizard'           => ['<x-dbl::sections.wizard submit-url="/s" :steps="[[\'label\' => \'A\', \'schema\' => [\'name\' => [\'type\' => \'text\']]]]" />'],
        'dblKvEditor'         => ['<x-dbl::form.kv-editor name="k" />'],
        'dblListEditor'       => ['<x-dbl::form.list-editor name="l" />'],
        'dblCalendar'         => ['<x-dbl::display.calendar load-url="/c" />'],
        'dblMap'              => ['<x-dbl::display.map />', '<x-dbl::display.map picker :picker-value="[1, 2]" geocode-url="/g" fit :layers="[[\'id\' => \'a\', \'label\' => \'A\', \'load_url\' => \'/a\']]" />'],
        'dblFlowchart'        => ['<x-dbl::display.flowchart :nodes="[]" :edges="[]" />'],
        'dblUpload'           => ['<x-dbl::form.upload name="f" upload-url="/u" />', '<x-dbl::form.upload name="f" upload-url="/u" multiple accept="image/*" :max-files="2" :max-size="3" delete-url="/d/{id}" reorder-url="/o" :existing="[[\'id\' => 1, \'name\' => \'a\']]" />'],
        'dblRichText'         => ['<x-dbl::form.rich-text name="b" />', '<x-dbl::form.rich-text name="b" label="B" placeholder="p" :max-length="9" minimal upload-url="/u" />'],
        'dblSignature'        => ['<x-dbl::form.signature name="s" />', '<x-dbl::form.signature name="s" required />'],
    ];
}

it('has a sample render for every factory that a view of the package starts', function () {
    $started = [];
    foreach (viewsOfThePackage() as $source) {
        preg_match_all('/x-data="(dbl\w+)\(/', $source, $found);
        $started = array_merge($started, $found[1]);
    }

    expect(array_values(array_diff(array_unique($started), array_keys(viewsOfTheFactories()))))->toBe([]);
});

it('passes to each factory exactly the keys it declares', function () {
    $declared = declaredFactories();
    $problems = [];

    foreach (viewsOfTheFactories() as $factory => $samples) {
        $passed = [];
        foreach ($samples as $sample) {
            $html = Blade::render($sample);
            expect(str_contains($html, $factory.'('))->toBeTrue("the sample of {$factory} does not start it");
            $passed = array_merge($passed, array_keys(xDataConfig($html, $factory)));
        }
        $passed = array_unique($passed);

        $knows = array_keys($declared[$factory]['defaults'] ?? []);
        $onlyJavaScript = $declared[$factory]['optional'] ?? [];

        $extra = array_values(array_diff($passed, $knows));
        $forgotten = array_values(array_diff($knows, $passed, $onlyJavaScript));

        if ($extra !== []) {
            $problems[$factory]['the view passes keys that the factory does not read'] = $extra;
        }
        if ($forgotten !== []) {
            $problems[$factory]['the factory reads keys that the view does not pass'] = $forgotten;
        }
    }

    expect($problems)->toBe([]);
});

it('gives a collapsible="false" sidebar to the factory, which pins it open', function () {
    expect(xDataConfig(Blade::render('<x-dbl::navigation.sidebar :collapsible="false">x</x-dbl::navigation.sidebar>'), 'dblSidebar'))->toBe(['collapsible' => false])
        ->and(xDataConfig(Blade::render('<x-dbl::navigation.sidebar>x</x-dbl::navigation.sidebar>'), 'dblSidebar'))->toBe(['collapsible' => true]);
});
