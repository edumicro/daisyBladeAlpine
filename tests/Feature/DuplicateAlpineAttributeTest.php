<?php

use Illuminate\Support\Facades\Blade;

/**
 * An Alpine attribute that the caller writes on a component goes to the element that receives `$attributes`. If the view
 * had written the same one there, the HTML would have it twice and the browser keeps only the first (the caller's handler
 * ran 0 times in Chrome). The rule for every view is in AlpineAttributeOwnershipTest; these are the cases that were found,
 * with what the caller writes.
 */
function openingTagWith(string $html, string $needle): string
{
    preg_match('/<[a-z]+[^<>]*'.preg_quote($needle, '/').'[^<>]*>/s', $html, $tag);

    return $tag[0] ?? '';
}

it('repeater: an x-on:input of the caller is the only x-on:input of the root', function () {
    $html = Blade::render('<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\']]" x-on:input="mine()" />');

    // The caller's listener is on the outer element (theirs); the scope of the component is the element inside, with its own.
    expect(openingTagWith($html, 'mine()'))->toContain('mine()')->not->toContain('x-data')
        ->and(substr_count(openingTagWith($html, 'mine()'), 'x-on:input='))->toBe(1)
        ->and(openingTagWith($html, 'dblRepeater'))->toContain('x-on:input="notify()"');
});

it('repeater: the rows still tell the component that they changed, from the element inside', function () {
    $html = Blade::render('<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\']]" />');

    expect($html)->toContain('x-on:input="notify()"');
});

it('repeater: an x-effect of the caller is the only x-effect of the root, and the sync into the parent scope still happens', function () {
    $html = Blade::render('<x-dbl::form.repeater name="r" mode="alpine" :fields="[[\'key\' => \'a\']]" x-effect="mine()" />');

    expect(substr_count(openingTagWith($html, 'dblRepeater'), 'x-effect='))->toBe(1)
        ->and($html)->toContain("data['r'] = rows");
});

it('modal: a :class of the caller is the only :class of the root', function () {
    $html = Blade::render('<x-dbl::actions.modal id="m" ::class="{ big: true }">x</x-dbl::actions.modal>');

    expect(preg_match_all('/\s:class=/', openingTagWith($html, 'big: true')))->toBe(1)
        ->and(openingTagWith($html, 'big: true'))->not->toContain('x-data');
});

it('alert: an x-show of the caller is the only x-show of the root', function () {
    $html = Blade::render('<x-dbl::feedback.alert message="a" x-show="mine" />');

    expect(substr_count(openingTagWith($html, 'x-show="mine"'), 'x-show='))->toBe(1)
        ->and(openingTagWith($html, 'x-show="mine"'))->not->toContain('x-data');
});

it('toast: an x-show of the caller is the only x-show of the root', function () {
    $html = Blade::render('<x-dbl::feedback.toast message="a" x-show="mine" />');

    expect(substr_count(openingTagWith($html, 'x-show="mine"'), 'x-show='))->toBe(1)
        ->and(openingTagWith($html, 'x-show="mine"'))->not->toContain('x-data');
});

it('upload: an x-on:drop of the caller is the only x-on:drop of the drop zone', function () {
    $html = Blade::render('<x-dbl::form.upload name="f" upload-url="/up" x-on:drop="mine()" />');

    preg_match('/<div[^<>]*mine\(\)[^<>]*>/s', $html, $zone);
    expect(substr_count($zone[0], 'x-on:drop'))->toBe(1)
        ->and(substr_count($zone[0], 'x-bind:class'))->toBe(0);
});

it('signature: an x-bind:class of the caller is the only one of the pad', function () {
    $html = Blade::render('<x-dbl::form.signature name="s" x-bind:class="mine" />');

    preg_match('/<div[^<>]*mine[^<>]*>/s', $html, $pad);
    expect(substr_count($pad[0], 'x-bind:class'))->toBe(1);
});

it('sidebar, map, flowchart and data-table: the caller can listen to the same order as the component', function () {
    $sidebar = Blade::render('<x-dbl::navigation.sidebar @dbl-sidebar-toggle.window="mine()">x</x-dbl::navigation.sidebar>');
    $map = Blade::render('<x-dbl::display.map x-on:dbl-map-focus.window="mine()" />');
    $flowchart = Blade::render('<x-dbl::display.flowchart x-on:dbl-flowchart-set.window="mine()" />');
    $table = Blade::render('<x-dbl::display.data-table load-url="/r" :columns="[]" @dbl-filters-applied.window="mine()" />');

    // Each one of the caller's is on an element without x-data (theirs); the component's own is on another element.
    expect(substr_count(openingTagWith($sidebar, '@dbl-sidebar-toggle.window="mine()"'), '@dbl-sidebar-toggle.window'))->toBe(1)
        ->and(substr_count(openingTagWith($map, 'x-on:dbl-map-focus.window="mine()"'), 'x-on:dbl-map-focus.window'))->toBe(1)
        ->and(substr_count(openingTagWith($flowchart, 'x-on:dbl-flowchart-set.window="mine()"'), 'x-on:dbl-flowchart-set.window'))->toBe(1)
        ->and(substr_count(openingTagWith($table, '@dbl-filters-applied.window="mine()"'), '@dbl-filters-applied.window'))->toBe(1)
        ->and(openingTagWith($sidebar, 'mine()'))->not->toContain('x-data')
        ->and(openingTagWith($map, 'mine()'))->not->toContain('x-data');
});
