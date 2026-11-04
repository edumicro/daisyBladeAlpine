<?php

use Illuminate\Support\Facades\Blade;

/**
 * Before 2.4 the `color` of these components went into the class name as it came (`text-{$color}`), so
 * `<x-dbl::feedback.loading color="primary-content" />` painted `text-primary-content`: the usual way to get a spinner
 * that shows on a primary button. 2.4 maps each color to a class written out in full, and the map must keep the colors
 * that worked: the `*-content` ones of DaisyUI and `white`.
 */

/** The colors of DaisyUI that name the content that goes on top of another color. */
const CONTENT_COLORS = [
    'primary-content', 'secondary-content', 'accent-content', 'neutral-content',
    'info-content', 'success-content', 'warning-content', 'error-content', 'base-content',
];

it('keeps the DaisyUI content colors and white on the text- maps of loading, radial-progress and text-rotate', function () {
    foreach ([...CONTENT_COLORS, 'white'] as $color) {
        expect(Blade::render("<x-dbl::feedback.loading color=\"{$color}\" />"))->toContain("text-{$color}")
            ->and(Blade::render("<x-dbl::display.radial-progress :value=\"5\" color=\"{$color}\" />"))->toContain("text-{$color}")
            ->and(Blade::render("<x-dbl::display.text-rotate :texts=\"['a']\" color=\"{$color}\" />"))->toContain("text-{$color}");
    }
});

it('keeps the DaisyUI content colors and white on the bg- maps of avatar and the dots of text-rotate', function () {
    foreach ([...CONTENT_COLORS, 'white'] as $color) {
        expect(Blade::render("<x-dbl::display.avatar initials=\"AB\" color=\"{$color}\" />"))->toContain("bg-{$color}")
            ->and(Blade::render("<x-dbl::display.text-rotate :texts=\"['a']\" color=\"{$color}\" />"))->toContain("'bg-{$color} w-8'");
    }
});

it('does not confuse a content color with its base color', function () {
    $html = Blade::render('<x-dbl::feedback.loading color="primary-content" />');

    expect($html)->toContain('text-primary-content');
    // `text-primary` alone would be a different color; the class must be the whole name.
    expect(preg_match('/\btext-primary\b(?!-)/', $html))->toBe(0);
});

it('still gives the default look to a color that is not in the lists', function () {
    expect(Blade::render('<x-dbl::feedback.loading color="magenta" />'))->toContain('text-primary')->not->toContain('magenta')
        ->and(Blade::render('<x-dbl::display.avatar initials="AB" color="magenta" />'))->toContain('bg-neutral')->not->toContain('magenta');
});
