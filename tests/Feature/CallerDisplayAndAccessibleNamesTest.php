<?php

use Illuminate\Support\Facades\Blade;

// ── alert and toast respect the `display:none` of the caller ───────────────────
it('the alert does not write display on the root unless it is closing', function () {
    expect(Blade::render('<x-dbl::feedback.alert message="x" />'))
        ->toContain("if (!open) \$root.parentElement.style.display = 'none'")
        ->not->toContain("open ? ''");
});

it('the toast does not write display on the root unless it is closing', function () {
    expect(Blade::render('<x-dbl::feedback.toast message="x" />'))
        ->toContain("if (!visible) \$root.parentElement.style.display = 'none'")
        ->not->toContain("visible ? ''");
});

// ── buttons that only have an icon have an accessible name ────────────────────
it('the close button of the alert has an accessible name', function () {
    expect(Blade::render('<x-dbl::feedback.alert message="x" />'))->toMatch('/<button[^>]*aria-label=/');
});

it('the mobile menu button of the navbar has an accessible name', function () {
    $html = Blade::render('<x-dbl::navigation.navbar brand="X"><x-slot:mobile>m</x-slot:mobile></x-dbl::navigation.navbar>');

    expect($html)->toMatch('/<button[^>]*lg:hidden[^>]*aria-label=/s');
});

it('the close buttons of the modal and of both toasts have an accessible name', function () {
    expect(Blade::render('<x-dbl::actions.modal id="m">b</x-dbl::actions.modal>'))->toMatch('/<button[^>]*aria-label=/');
    expect(Blade::render('<x-dbl::feedback.toast message="x" />'))->toMatch('/<button[^>]*aria-label=/');
    expect(Blade::render('<x-dbl::feedback.toast />'))->toMatch('/<button[^>]*aria-label=/');
});

// ── modal: a dialog with a name ───────────────────────────────────────────────
it('the modal says it is a dialog, modal, and named by its title', function () {
    $html = Blade::render('<x-dbl::actions.modal id="m" title="Delete">b</x-dbl::actions.modal>');

    expect($html)->toContain('role="dialog"')->toContain('aria-modal="true"')
        ->toContain('aria-labelledby="m-title"')->toContain('id="m-title"');
});

// ── sidebar: closed, its links leave the keyboard order ───────────────────────
it('the sidebar is inert while it is closed', function () {
    expect(Blade::render('<x-dbl::navigation.sidebar>x</x-dbl::navigation.sidebar>'))->toContain(':inert="!open"');
});

// ── tabs: the first tab without a label is the number 1 ───────────────────────
it('names the first tab without label "Tab 1"', function () {
    expect(Blade::render('<x-dbl::navigation.tabs :items="[[\'content\' => \'a\']]" />'))->toContain('Tab 1')->not->toContain('Tab 0');
});

// ── swap: the buttons inside the label click the checkbox ─────────────────────
it('the buttons of the swap click the checkbox themselves (a button inside a label does not pass the click on)', function () {
    $html = Blade::render('<x-dbl::actions.swap />');

    expect(substr_count($html, "onclick=\"this.closest('label').querySelector('input[type=checkbox]').click()\""))->toBe(2)
        ->and(substr_count($html, 'tabindex="-1"'))->toBe(2);
});

// ── navigation that dispatches events has an Alpine scope of its own ──────────
it('pagination, dock and menu give an x-data to the buttons that $dispatch, not to the root that is the caller\'s', function () {
    $pagination = Blade::render('<x-dbl::navigation.pagination :meta="[\'current_page\' => 2, \'last_page\' => 5, \'total\' => 50]" />');
    $dock = Blade::render('<x-dbl::navigation.dock :items="[[\'label\' => \'A\', \'icon\' => \'heroicon-o-home\', \'action\' => \'go\']]" />');
    $menu = Blade::render('<x-dbl::navigation.menu :items="[[\'label\' => \'A\', \'action\' => \'go\']]" />');

    expect($pagination)->toMatch('/<button type="button" x-data @click="\$dispatch\(/')
        ->and($dock)->toMatch('/x-data @click="\$dispatch\(/')
        ->and($menu)->toMatch('/x-data @click="\$dispatch\(/')
        ->and($pagination)->not->toMatch('/<div x-data/')
        ->and($menu)->not->toMatch('/<ul[^>]*x-data/');
});
