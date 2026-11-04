<?php

/**
 * The header of the map view named the event `map-picked`; the map dispatches `dbl-map-picked` (all the events of the
 * package carry the prefix). Someone who copied the name from the header listened to an event that never comes.
 */
it('names in the header of the map view the event that the map really dispatches', function () {
    $view = file_get_contents(__DIR__.'/../../resources/views/daisyblade/display/map.blade.php');
    $script = file_get_contents(__DIR__.'/../../resources/js/components/map.js');

    expect($script)->toContain("\$dispatch('dbl-map-picked'")
        ->and($view)->toContain('Evento: `dbl-map-picked`')
        ->and(preg_match('/Evento: `map-picked`/', $view))->toBe(0);
});
