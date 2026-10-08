<?php

use Illuminate\Support\Facades\Blade;

it('renders the calendar wired to its load url', function () {
    $html = Blade::render('<x-dbl::display.calendar load-url="/api/events" initial-date="2026-10-08" initial-view="list" />');

    expect($html)
        ->toContain('x-data="dbCalendar(')
        ->toContain('\\u0022loadUrl\\u0022:\\u0022')
        ->toContain('2026-10-08')
        ->toContain('aria-live="polite"')
        ->toContain('skeleton')
        ->not->toContain('wire:')
        ->not->toContain(' @click');
});

it('defaults the locale to the app locale and keeps week start', function () {
    app()->setLocale('ca');
    $html = Blade::render('<x-dbl::display.calendar load-url="/e" :week-starts-on="0" />');

    expect($html)->toContain('\\u0022locale\\u0022:\\u0022ca\\u0022')->toContain('\\u0022weekStartsOn\\u0022:0');
});

it('applies the height prop', function () {
    $html = Blade::render('<x-dbl::display.calendar load-url="/e" height="30rem" />');

    expect($html)->toContain('height: 30rem');
});

it('renders states in the app language', function (string $locale, string $empty, string $retry) {
    app()->setLocale($locale);
    $html = Blade::render('<x-dbl::display.calendar load-url="/e" />');

    expect($html)->toContain($empty)->toContain($retry);
})->with([
    ['es', 'No hay eventos', 'Reintentar'],
    ['ca', 'No hi ha esdeveniments', 'Reintenta'],
    ['en', 'No events', 'Retry'],
]);
