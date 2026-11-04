<?php

/*
 * display.flowchart, calendar, map (real MapLibre), node-graph, carousel.
 * Findings: ronda 3 (FC, CAL, CALRACE, MAP, NG, CAR).
 */

// ── Flowchart ──────────────────────────────────────────────────────────────

/** One flowchart with 3 nodes (C is muted) and 1 arrow, plus a recorder of the events it announces. */
function flowchartPage(string $name): mixed
{
    $page = visit(browserPage($name, <<<'BLADE'
        <x-dbl::display.flowchart name="f" editable fit
            :nodes="[['id' => 1, 'label' => 'A'], ['id' => 2, 'label' => 'B'], ['id' => 3, 'label' => 'C', 'muted' => true]]"
            :edges="[['id' => 'e1', 'from' => 1, 'to' => 2]]" />
        <x-dbl::feedback.toast />
        BLADE, ['modules' => ['flowchart']]));
    readPage($page, "document.querySelectorAll('[data-node]').length", fn ($v) => $v > 0);
    $page->script("(() => { window.ev = []; for (const n of ['dbl-flowchart-node-move-requested', 'dbl-flowchart-edge-requested', 'dbl-flowchart-node-clicked', 'dbl-flowchart-edge-clicked']) window.addEventListener(n, e => window.ev.push([n, e.detail.id ?? e.detail.fromId, e.detail.x, e.detail.y])) })()");

    return $page;
}

it('flowchart: draws A and B and hides the muted C', function () {
    $page = flowchartPage('flowchart-render');

    expect($page->script("[...document.querySelectorAll('[data-node]')].map(n => n.dataset.node)"))->toBe(['1', '2']);
});

it('flowchart: dragging a box announces node-move-requested', function () {
    $page = flowchartPage('flowchart-drag-node');

    $page->drag('[data-node="1"]', '[data-node="2"]');

    $moves = readPage($page, "window.ev.filter(e => e[0] === 'dbl-flowchart-node-move-requested')", fn ($v) => count($v) > 0);
    expect($moves)->not->toBeEmpty();
});

it('flowchart: dragging the handle of a box onto another announces edge-requested and draws the arrow', function () {
    $page = flowchartPage('flowchart-drag-handle');

    $page->drag('[data-node="2"] .dbl-flowchart-handle', '[data-node="1"]');

    $requests = readPage($page, "window.ev.filter(e => e[0] === 'dbl-flowchart-edge-requested')", fn ($v) => count($v) > 0);
    expect($requests)->toHaveCount(1)
        ->and($page->script("document.querySelectorAll('svg g[role=button]').length"))->toBe(2);
});

it('flowchart: the keyboard announces one move per arrow key released', function () {
    $page = flowchartPage('flowchart-keyboard');

    $page->keys('[data-node="2"]', ['ArrowRight', 'ArrowRight']);

    expect(readPage($page, "window.ev.filter(e => e[0] === 'dbl-flowchart-node-move-requested').length", fn ($v) => $v >= 2))->toBe(2);
});

it('flowchart: the order dbl-flowchart-remove-node removes the box', function () {
    $page = flowchartPage('flowchart-remove-node');

    $page->script("window.dispatchEvent(new CustomEvent('dbl-flowchart-remove-node', { detail: { name: 'f', id: 2 } }))");

    expect(readPage($page, "[...document.querySelectorAll('[data-node]')].map(n => n.dataset.node)", fn ($v) => $v === ['1']))->toBe(['1']);
});

// ── Calendar ───────────────────────────────────────────────────────────────

function calendarPage(string $name): mixed
{
    $page = visit(browserPage($name, '<x-dbl::display.calendar load-url="/cal" initial-date="2026-05-10" initial-view="month" />', ['modules' => ['calendar']]));
    readPage($page, "document.querySelectorAll('.dbl-calendar button.truncate').length", fn ($v) => $v >= 2);

    return $page;
}

it('calendar: shows May 2026 and the events the server sent', function () {
    $page = calendarPage('calendar-month');

    expect($page->script("document.querySelector('h2').textContent"))->toMatch('/mayo|may/i')
        ->and($page->script("document.querySelectorAll('.dbl-calendar button.truncate').length"))->toBeGreaterThanOrEqual(2);
});

it('calendar: after changing month, one day is reachable with Tab (roving tabindex)', function () {
    $page = calendarPage('calendar-roving-tabindex');

    $page->script("document.querySelector('button[aria-label]').click()");
    pause($page, 0.4);

    expect($page->script("document.querySelectorAll('button[data-day][tabindex=\"0\"]').length"))->toBe(1);
});

it('calendar: a late failure of April does not spoil May, which was already loaded', function () {
    $page = calendarPage('calendar-race');
    $page->script("window.__d = Alpine.\$data(document.querySelector('[x-data^=\"dblCalendar\"]'))");
    $aprilStart = $page->script("(() => { const d = window.__d, c = d.cursor; d.cursor = new Date(2026, 3, 1); const k = d.grid[0].date; d.cursor = c; return k })()");
    $page->script("window.__net.delay['start=$aprilStart'] = 500; window.__net.fail['start=$aprilStart'] = true");

    $page->script('window.__d.prev()');
    pause($page, 0.05);
    $page->script('window.__d.next()');
    pause($page, 0.9);

    $state = $page->script('({ error: window.__d.error, loading: window.__d.loading, n: window.__d.events.length })');
    expect($state['error'])->toBeFalse()->and($state['n'])->toBeGreaterThanOrEqual(2);
});

// ── Map (real MapLibre) ────────────────────────────────────────────────────

function mapPage(string $name): mixed
{
    $page = visit(browserPage($name, <<<'BLADE'
        <x-dbl::display.map base="{{ url('/tile') }}/{z}/{x}/{y}.png" fit picker
            :layers="[['id' => 'a', 'label' => 'A', 'load_url' => '/geo/a', 'cluster' => true], ['id' => 'b', 'label' => 'B', 'load_url' => '/geo/b']]" />
        BLADE, ['modules' => ['map']]));
    readPage($page, "!!document.querySelector('canvas.maplibregl-canvas') && Alpine.\$data(document.querySelector('[x-data^=\"dblMap\"]')).pointList.length", fn ($v) => $v >= 3);

    return $page;
}

it('map: MapLibre starts with its canvas and shows no error', function () {
    $page = mapPage('map-starts');

    expect($page->script("!!document.querySelector('canvas.maplibregl-canvas')"))->toBeTrue()
        ->and($page->script("document.querySelector('[role=alert][x-text=error]')?.textContent ?? ''"))->toBe('');
});

it('map: the layers and categories are loaded (2 layers, 2 categories, 3 points)', function () {
    $page = mapPage('map-layers');

    $data = $page->script("(() => { const m = Alpine.\$data(document.querySelector('[x-data^=\"dblMap\"]')); return { layers: m.layerState.length, cats: m.categories.map(c => c.value), list: m.pointList.length } })()");

    expect($data['layers'])->toBe(2)->and($data['cats'])->toHaveCount(2)->and($data['list'])->toBe(3);
});

it('map: filtering out a category removes its points from the list', function () {
    $page = mapPage('map-filter');

    $page->script("(() => { const m = Alpine.\$data(document.querySelector('[x-data^=\"dblMap\"]')); m.categories[0].checked = false; m.applyFilters() })()");

    expect(readPage($page, "Alpine.\$data(document.querySelector('[x-data^=\"dblMap\"]')).pointList.length", fn ($v) => $v === 2))->toBe(2);
});

it('map: a click on the map places the marker and fills the lat/lng inputs', function () {
    $page = mapPage('map-picker');

    $page->click('canvas.maplibregl-canvas');

    $pick = readPage($page, "({ lat: document.querySelector('input[name=location_lat]').value, lng: document.querySelector('input[name=location_lng]').value })", fn ($v) => $v['lat'] !== '');
    expect($pick['lat'])->not->toBe('')->and($pick['lng'])->not->toBe('');
});

it('map: focus(layer, id) finds the point and opens its popup', function () {
    $page = mapPage('map-focus');

    $found = $page->script("Alpine.\$data(document.querySelector('[x-data^=\"dblMap\"]')).focus('b', 'q1')");
    $popup = readPage($page, "document.querySelector('.maplibregl-popup')?.textContent ?? ''", fn ($v) => str_contains($v, 'Q1'));

    expect($found)->toBeTrue()->and($popup)->toContain('Q1');
});

// ── Node graph and carousel ────────────────────────────────────────────────

it('node-graph: the click, the + and the × announce events with the id as JSON', function () {
    $page = visit(browserPage('node-graph-events', <<<'BLADE'
        <x-dbl::display.node-graph editable
            :nodes="[['id' => 1, 'label' => 'Root'], ['id' => 2, 'parent' => 1, 'label' => 'Kid', 'status' => 'completed', 'badges' => [['label' => 'b1']]]]" />
        BLADE));
    $page->script("(() => { window.ev = []; for (const n of ['node-clicked', 'badge-clicked', 'edge-requested', 'node-create-requested', 'node-delete-requested']) window.addEventListener('dbl-node-graph-' + n, e => window.ev.push([n, JSON.stringify(e.detail)])) })()");

    $page->script("document.querySelector('button[title*=\"Kid\"]').click()");
    clickText($page, '+');
    $page->script("[...document.querySelectorAll('button')].find(b => b.textContent === '×' && b.title).click()");

    $events = $page->script('window.ev');
    expect($events)->toHaveCount(3)
        ->and(json_decode($events[0][1], true)['id'])->toBe(2);
});

it('carousel: shows one item, goes to the next and wraps around', function () {
    $page = visit(browserPage('carousel-wrap', <<<'BLADE'
        <x-dbl::display.carousel :items="[['content' => 'one'], ['content' => 'two']]" />
        <x-dbl::display.carousel id="c0" :items="[]" />
        BLADE));
    $shown = "[...document.querySelectorAll('.carousel-item')].map(e => getComputedStyle(e).display !== 'none')";

    $first = $page->script($shown);
    clickText($page, '❯');
    $second = readPage($page, $shown, fn ($v) => $v === [false, true]);
    clickText($page, '❯');
    $wrapped = readPage($page, $shown, fn ($v) => $v === [true, false]);

    expect($first)->toBe([true, false])->and($second)->toBe([false, true])->and($wrapped)->toBe([true, false]);
});
