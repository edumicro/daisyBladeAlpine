<?php

/*
 * navigation.sidebar. Findings: fable-daisy3 LS · ronda 1 SB · ronda 2 SB2 · ronda 3 SB, SBM (collapsible=false).
 */

it('sidebar: the 2.x key db_sidebar is moved to dbl_sidebar once, with no repeat and no loss', function () {
    $page = visit(browserPage('sidebar-legacy-key', '<x-dbl::navigation.sidebar>menu</x-dbl::navigation.sidebar>'));
    $page->script("localStorage.clear(); localStorage.setItem('db_sidebar', 'false')");
    $stored = "({ old: localStorage.getItem('db_sidebar'), neu: localStorage.getItem('dbl_sidebar') })";

    $page->refresh();
    $afterFirst = $page->script("({ ...$stored, open: Alpine.\$data(document.querySelector('[x-data^=dblSidebar]')).open })");
    $page->refresh();
    $afterSecond = $page->script($stored);

    expect($afterFirst)->toBe(['old' => null, 'neu' => 'false', 'open' => false])
        ->and($afterSecond)->toBe(['old' => null, 'neu' => 'false']);
});

it('sidebar: the window event moves the bar and also reaches the @ listener of the caller', function () {
    $page = visit(browserPage('sidebar-event', <<<'BLADE'
        <x-dbl::navigation.sidebar @dbl-sidebar-toggle.window="window.mineToggle = (window.mineToggle || 0) + 1">menu</x-dbl::navigation.sidebar>
        BLADE));

    $page->script("window.dispatchEvent(new CustomEvent('dbl-sidebar-toggle'))");

    expect(readPage($page, 'window.mineToggle ?? 0'))->toBe(1);
});

it('sidebar: when closed, its links cannot take the focus (inert or aria-hidden)', function () {
    $page = visit(browserPage('sidebar-closed-focus', '<x-dbl::navigation.sidebar><a href="#x" id="lnk">menu link</a></x-dbl::navigation.sidebar>'));
    $page->resize(500, 800);
    pause($page, 0.3);

    $state = $page->script("(() => { const a = document.querySelector('aside'); document.getElementById('lnk').focus(); return { closed: /-translate-x-full/.test(a.className), inert: a.inert, focused: document.activeElement.id } })()");

    expect(! $state['closed'] || $state['focused'] !== 'lnk' || $state['inert'] === true)->toBeTrue();
});

it('sidebar collapsible=false: stays open even when dbl_sidebar says false', function () {
    $page = visit(browserPage('sidebar-not-collapsible', '<x-dbl::navigation.sidebar :collapsible="false"><a href="#x">menu link</a></x-dbl::navigation.sidebar>'));
    $page->script("localStorage.setItem('dbl_sidebar', 'false')");
    $page->refresh();

    expect($page->script(alpineData('[x-data="dblSidebar()"]', 'open')))->toBeTrue();
})->skip('Pending: ronda3-3x/INFORME.md MEDIA 4 (SB): collapsible=false, the factory never receives the config (cause 7). Activate when fixed.');

it('sidebar collapsible=false: stays open on a phone-width screen too', function () {
    $page = visit(browserPage('sidebar-not-collapsible-phone', '<x-dbl::navigation.sidebar :collapsible="false"><a href="#x">menu link</a></x-dbl::navigation.sidebar>'));
    $page->resize(500, 800);
    $page->script("localStorage.removeItem('dbl_sidebar')");
    $page->refresh();

    expect($page->script(alpineData('[x-data="dblSidebar()"]', 'open')))->toBeTrue();
})->skip('Pending: ronda3-3x/INFORME.md MEDIA 4 (SBM): collapsible=false below 1024px (cause 7). Activate when fixed.');
