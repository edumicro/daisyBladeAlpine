<?php

use Illuminate\Support\Facades\Blade;

/**
 * The <select> of the remote mode must NOT sit inside the element that carries x-data="dblSelectRemote(...)". Every
 * Alpine expression the caller writes on the <select> is evaluated in the scope of the elements around it, so inside ours
 * the `loading`, `error`, `options`, `selected`, `disabled` and `waiting` of this component would hide the caller's own.
 * (Chrome: `<div x-data="{ loading: true, error: 'outer' }"> <x-dbl::form.select load-url=... ::disabled="loading"
 * ::data-err="error">` ended with the select ENABLED and data-err="" once the options arrived.)
 * The scope stays on an element next to the <select>, which holds the error message.
 */
it('keeps the <select> of a remote select out of the dblSelectRemote scope, so the caller bindings read their own data', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/o" />');

    // The element with x-data, and everything up to its matching close: the <select> must not be in there.
    preg_match('/<(\w+)[^>]*x-data="dblSelectRemote\([^"]*"[^>]*>/s', $html, $scope, PREG_OFFSET_CAPTURE);
    expect($scope)->not->toBeEmpty();

    $start = $scope[0][1];
    $tag = $scope[1][0];
    $depth = 0;
    $end = null;
    preg_match_all('/<\/?'.$tag.'\b[^>]*>/s', $html, $tags, PREG_OFFSET_CAPTURE, $start);
    foreach ($tags[0] as [$text, $offset]) {
        $depth += str_starts_with($text, '</') ? -1 : 1;
        if ($depth === 0) {
            $end = $offset;
            break;
        }
    }

    $inside = substr($html, $start, $end - $start);

    expect($inside)->not->toContain('<select');
});

it('does not leave a <template x-for> inside the remote <select>, which could not see the options of the component', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/o" />');

    preg_match('/<select\b.*?<\/select>/s', $html, $select);

    expect($select[0])->not->toContain('<template');
});
