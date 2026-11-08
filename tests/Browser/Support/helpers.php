<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/*
|--------------------------------------------------------------------------
| Helpers of the browser tests
|--------------------------------------------------------------------------
|
| Every browser test follows the same three steps:
|
|   1. `browserPage('name', '<x-dbl::... />')` registers ONE page that renders that Blade with the
|      real views of the package, wrapped in a small layout with the real daisyblade.js, the real
|      Alpine, Tailwind and DaisyUI (all served from `node_modules`, so no network is needed).
|   2. `visit($url)` opens it in a real Chromium (Pest browser plugin + Playwright).
|   3. The test clicks, types or fires events and asserts what the user would see.
|
| Calls to the server are answered by the fake endpoints of `registerBrowserEndpoints()`.
*/

/** Absolute path of a file of the package or of `node_modules`. */
function browserPath(string $relative): string
{
    return dirname(__DIR__, 3).'/'.ltrim($relative, '/');
}

/**
 * Registers a page for a browser test and returns its URL.
 *
 * @param  string  $name  Unique name of the page (it is the route: /page/{name}).
 * @param  string  $template  Blade written as in an application: `<x-dbl::form.select ... />`.
 * @param  array<string, string>  $options  `modules`: extra ES modules to register with Alpine, e.g. ['upload', 'map'].
 */
function browserPage(string $name, string $template, array $options = []): string
{
    registerBrowserEndpoints();

    Route::get('/page/'.$name, function () use ($template, $options) {
        View::share('errors', new ViewErrorBag);

        return response(browserLayout(Blade::render($template), $options['modules'] ?? []))
            ->header('Content-Type', 'text/html; charset=utf-8');
    });

    return '/page/'.$name;
}

/**
 * Like `browserPage()`, but for HTML that is already painted (what a component of the catalog gave): it is not Blade any more,
 * so it must not go through the compiler again (`@click` and `{{` mean something else to it).
 */
function browserPageOfHtml(string $name, string $html, array $options = []): string
{
    registerBrowserEndpoints();

    Route::get('/page/'.$name, fn () => response(browserLayout($html, $options['modules'] ?? []))
        ->header('Content-Type', 'text/html; charset=utf-8'));

    return '/page/'.$name;
}

/**
 * The HTML shell of every page: daisyblade.js (module) before Alpine (defer), as the README asks.
 * `registerXxx(Alpine)` of the optional components runs on `alpine:init`, as an application would do it.
 *
 * @param  list<string>  $modules  Names of optional components: calendar, flowchart, map, upload, signature.
 */
function browserLayout(string $body, array $modules = []): string
{
    $imports = '';
    $registers = '';
    foreach ($modules as $module) {
        $function = 'register'.Str::studly($module);
        $imports .= "import { {$function} } from '/_assets/js/components/{$module}.js'\n";
        // The optional components that need a library get it injected, as an application does.
        $registers .= match ($module) {
            'map' => "{$function}(window.Alpine, { maplibregl: withMapsRecorded(maplibregl) })\n",
            'rich-text' => "{$function}(window.Alpine, tiptap)\n",
            default => "{$function}(window.Alpine)\n",
        };
    }
    if (in_array('rich-text', $modules, true)) {
        $imports .= "import * as tiptap from '/_assets/tiptap.js'\n";
    }
    if (in_array('map', $modules, true)) {
        $imports .= "import * as maplibregl from '/_assets/node/maplibre-gl/dist/maplibre-gl.mjs'\n";
        // The maps the page creates, in `window.__maps`, so that a test can ask one its zoom or its bounds (the component keeps its own out of reach).
        $imports .= "const withMapsRecorded = ns => ({ ...ns, Map: class extends ns.Map { constructor(options) { super(options); (window.__maps ??= []).push(this) } } })\n";
    }

    // Fake network (see `window.__net`): records every fetch and lets a test delay or fail one by URL.
    $net = <<<'JS'
window.__net = { log: [], delay: {}, fail: {} }
// A page must never run an `alert()` that a text of the user wrote: the catalog test looks at this flag.
window.alert = () => { window.__alerted = true }
const realFetch = window.fetch.bind(window)
window.fetch = async (input, init) => {
    const url = typeof input === 'string' ? input : input.url
    window.__net.log.push(url)
    const rule = key => Object.keys(window.__net[key]).find(part => url.includes(part))
    if (rule('delay')) await new Promise(r => setTimeout(r, window.__net.delay[rule('delay')]))
    if (rule('fail')) return new Response('boom', { status: 500 })
    return realFetch(input, init)
}
JS;

    return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/_assets/node/daisyui/daisyui.css">
<link rel="stylesheet" href="/_assets/node/maplibre-gl/dist/maplibre-gl.css">
<script src="/_assets/node/@tailwindcss/browser/dist/index.global.js"></script>
<script>{$net}</script>
<script type="module">
import '/_assets/js/daisyblade.js'
{$imports}document.addEventListener('alpine:init', () => {
{$registers}})
</script>
<script defer src="/_assets/node/alpinejs/dist/cdn.min.js"></script>
</head><body x-data>{$body}</body></html>
HTML;
}

/**
 * The fake server of the browser tests. Registered once per test (routes live in the app of the test).
 * Every endpoint answers JSON, as the package's contract asks (see docs).
 */
function registerBrowserEndpoints(): void
{
    if (Route::has('browser.assets')) {
        return;
    }

    $serve = fn (string $file, string $type) => response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'no-store']);
    $typeOf = fn (string $file) => match (pathinfo($file, PATHINFO_EXTENSION)) {
        'css' => 'text/css',
        'json' => 'application/json',
        default => 'text/javascript',
    };

    // The package's own JavaScript, and the third-party libraries from node_modules.
    Route::get('/_assets/js/{path}', fn (string $path) => $serve(browserPath('resources/js/'.$path), $typeOf($path)))
        ->where('path', '.*')->name('browser.assets');
    Route::get('/_assets/node/{path}', fn (string $path) => $serve(browserPath('node_modules/'.$path), $typeOf($path)))
        ->where('path', '.*');

    // Tiptap in ONE module, built from tests/Browser/Support/tiptap-entry.js (see `tiptapBundle()`).
    Route::get('/_assets/tiptap.js', fn () => $serve(tiptapBundle(), 'text/javascript'));

    // A 1x1 picture for every image of the catalog, a resource and two fragments of lazy tabs.
    Route::get('/pixel.png', fn () => response(
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        200,
        ['Content-Type' => 'image/png'],
    ));
    Route::get('/res', fn () => response()->json(['name' => 'Ana', 'email' => 'ana@example.test']));
    Route::get('/frag{suffix?}', fn () => response('<p>Fragment</p>', 200, ['Content-Type' => 'text/html']))->where('suffix', '[0-9]*');

    // Select options: `{data: [...]}` is not needed here, the select accepts a plain list too.
    Route::get('/options', fn () => response()->json([['value' => '1', 'label' => 'One'], ['value' => '2', 'label' => 'Two']]));
    // Server search of the select (`search-url`): a bare list of rows, filtered by `q`; and one that fails.
    Route::get('/medicines', fn () => response()->json(collect([
        ['id' => 1, 'text' => 'Ibuprofen', 'lab' => 'Acme'],
        ['id' => 2, 'text' => 'Ibuprofen 600', 'lab' => 'Zeta'],
        ['id' => 3, 'text' => 'Paracetamol', 'lab' => 'Acme'],
    ])->filter(fn ($row) => str_contains(strtolower($row['text']), strtolower((string) request('q'))))->values()));
    // The same, paginated by `page` and `limit`, and one that depends on another field (`family`) and one that answers a map.
    Route::get('/drugs', fn () => response()->json(collect(range(1, 5))->map(fn ($n) => ['id' => $n, 'text' => 'Drug '.$n.' '.request('family', '-')])
        ->slice((max(1, (int) request('page', 1)) - 1) * (int) request('limit', 20), (int) request('limit', 20))->values()));
    Route::get('/drugs-map', fn () => response()->json([12 => 'Paracetamol', 7 => 'Aspirin']));
    Route::get('/medicines-fail', fn () => response()->json(['message' => 'boom'], 500));
    // Table rows. `/rowsA`, `/rowsB`: two tables on one page.
    Route::get('/rows{suffix?}', fn () => response()->json(['data' => [['id' => 7]], 'meta' => []]))->where('suffix', '[A-Za-z]*');
    // A session that expired: the login page, with a 200.
    Route::any('/upload-html', fn () => response('<html>login</html>', 200, ['Content-Type' => 'text/html']));
    // Calendar events.
    Route::get('/cal', fn () => response()->json([
        ['id' => 1, 'title' => 'Ev'.request('start'), 'start' => '2026-05-15'],
        ['id' => 2, 'title' => 'Edge', 'start' => '2026-05-31T23:30:00Z'],
    ]));
    // Map layers (GeoJSON) and a 1x1 PNG as the only tile.
    Route::get('/geo/a', fn () => response()->json(['type' => 'FeatureCollection', 'features' => [
        ['type' => 'Feature', 'id' => 'p1', 'geometry' => ['type' => 'Point', 'coordinates' => [-0.37, 39.47]], 'properties' => ['title' => 'P1', 'category' => 'x', 'url' => '/p1']],
        ['type' => 'Feature', 'id' => 'p2', 'geometry' => ['type' => 'Point', 'coordinates' => [-0.36, 39.48]], 'properties' => ['title' => 'P2', 'category' => 'y', 'number' => 7]],
    ]]));
    Route::get('/geo/b', fn () => response()->json(['type' => 'FeatureCollection', 'features' => [
        ['type' => 'Feature', 'id' => 'q1', 'geometry' => ['type' => 'Point', 'coordinates' => [-0.2, 39.4]], 'properties' => ['title' => 'Q1']],
    ]]));
    Route::get('/tile/{z}/{x}/{y}.png', fn () => response(
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
        200,
        ['Content-Type' => 'image/png'],
    ));
}

/**
 * Reads something from the page until it satisfies `$done` (or 3 seconds pass) and returns the last value read.
 * Components load their data after the page is shown, so a test cannot read at once; it waits for the
 * answer it expects, and when it never comes, the returned value is what the test reports as wrong.
 *
 * @param  \Pest\Browser\Api\Webpage|\Pest\Browser\Api\AwaitableWebpage  $page
 * @param  string  $expression  JavaScript expression (it may use `await` inside an async IIFE) that gives what to read.
 * @param  (callable(mixed): bool)|null  $done  When omitted, the first truthy value ends the wait.
 */
function readPage($page, string $expression, ?callable $done = null): mixed
{
    $done ??= fn ($value) => (bool) $value;
    $value = null;
    for ($try = 0; $try < 60; $try++) {
        $value = $page->script($expression);
        if ($done($value)) {
            return $value;
        }
        usleep(50_000);
    }

    return $value;
}

/** Waits a fixed time: only for things that must NOT happen (a request that should not be sent). */
function pause($page, float $seconds = 0.4): void
{
    $page->wait($seconds);
}

/** Clicks the first visible `<button>` (or other tag) whose text is `$label`, with a real DOM click. */
function clickText($page, string $label, string $tag = 'button'): void
{
    $page->script('(() => { const l = '.json_encode($label).'; const e = [...document.querySelectorAll('.json_encode($tag).')].find(b => b.textContent.trim() === l || b.textContent.includes(l)); if (!e) throw new Error("no '.$tag.' with text " + l); e.click() })()');
}

/** The Alpine data of the element that matches `$selector`, as JSON-friendly JavaScript for `readPage()`. */
function alpineData(string $selector, string $path = ''): string
{
    return 'Alpine.$data(document.querySelector('.json_encode($selector).'))'.($path === '' ? '' : '.'.$path);
}

/**
 * Writes `$text` into a control and tells the page with an `input` event, in one step.
 * Use it for controls that sit inside a closed dropdown (the browser cannot type into what is not visible);
 * for visible controls, `typeSlowly()` sends real key presses and is closer to the user.
 */
function setField($page, string $selector, string $text, string $event = 'input'): void
{
    $page->script('(() => { const e = document.querySelector('.json_encode($selector).'); e.value = '.json_encode($text).'; e.dispatchEvent(new Event('.json_encode($event).', { bubbles: true })) })()');
}

/** Presses a key with the focus wherever it is (the document), e.g. `pressKey($page, 'Escape')`. */
function pressKey($page, string $key): void
{
    $page->keys('html > body', $key);
}

/** Whether the element that matches `$selector` is shown (its computed `display` is not `none`). */
function isShown($page, string $selector): bool
{
    return (bool) $page->script('getComputedStyle(document.querySelector('.json_encode($selector).')).display !== "none"');
}

/**
 * Chooses a file in an `<input type="file">` the way the browser does: it builds a real `File`, puts it
 * in `input.files` and fires `change`. (`attach()` of the plugin needs a local Playwright server.)
 */
function chooseFile($page, string $selector, string $name = 'f.txt', string $content = 'x'): void
{
    $page->script('(() => { const e = document.querySelector('.json_encode($selector).'); const t = new DataTransfer(); t.items.add(new File(['.json_encode($content).'], '.json_encode($name).')); e.files = t.files; e.dispatchEvent(new Event("change", { bubbles: true })) })()');
}

/** The real signature_pad (a development dependency): a page that has a signature loads it before Alpine starts. */
const PAD_SCRIPT = '<script src="/_assets/node/signature_pad/dist/signature_pad.umd.min.js"></script>';

/** One stroke across a canvas with the pointer events that SignaturePad listens to (the pad must be on the page, and shown). */
function drawStroke($page, string $canvas = 'canvas'): void
{
    $page->script(<<<JS
        (() => {
            const c = document.querySelector('{$canvas}'); const r = c.getBoundingClientRect()
            const at = (type, x, y) => (type === 'pointerup' ? document : c).dispatchEvent(new PointerEvent(type, { bubbles: true, pointerId: 1, pointerType: 'mouse', isPrimary: true, buttons: type === 'pointerup' ? 0 : 1, clientX: r.left + x, clientY: r.top + y }))
            at('pointerdown', 20, 20); for (let i = 1; i < 12; i++) at('pointermove', 20 + i * 10, 20 + i * 5); at('pointerup', 130, 75)
        })()
        JS);
}

/**
 * The bundle of Tiptap for the browser tests, built once with esbuild from `node_modules` (a development dependency) and kept in the
 * temporary folder of the system until the entry file or the installed Tiptap changes. Returns its path.
 */
function tiptapBundle(): string
{
    $entry = browserPath('tests/Browser/Support/tiptap-entry.js');
    $bundle = sys_get_temp_dir().'/daisyblade-tests/tiptap-'.md5($entry.filemtime($entry).filemtime(browserPath('package-lock.json'))).'.js';
    if (is_file($bundle)) {
        return $bundle;
    }

    @mkdir(dirname($bundle), 0777, true);
    $built = Illuminate\Support\Facades\Process::path(browserPath(''))->run([browserPath('node_modules/.bin/esbuild'), $entry, '--bundle', '--format=esm', '--outfile='.$bundle, '--log-level=error']);
    if (! $built->successful()) {
        throw new RuntimeException('esbuild could not build Tiptap for the browser tests (did you run `npm install`?): '.$built->errorOutput());
    }

    return $bundle;
}

/** Types text in the editor like a user: focus it and type with real key presses. */
function typeInEditor($page, string $text): void
{
    $page->script("document.querySelector('.dbl-rich-text-content').focus()");
    $page->typeSlowly('.dbl-rich-text-content', $text, 10);
}
