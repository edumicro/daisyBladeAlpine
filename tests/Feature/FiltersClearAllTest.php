<?php

use Illuminate\Support\Facades\Blade;

/**
 * "Clear all" of `form.filters` emptied its state and reloaded the table, but the boxes of the panel kept the text the user
 * had typed, so the screen said one thing and the table showed another. The x-data of the panel is inline in the view, so
 * this test takes it from the rendered HTML and runs it in Node (as Alpine would evaluate it), with a fake panel.
 */

/** Runs `clear()` of the rendered panel over fake controls and returns what they hold afterwards and what was announced. */
function clearTheFilterPanel(array $controls): array
{
    $html = Blade::render('<x-dbl::form.filters :filters="[[\'key\' => \'st\', \'type\' => \'select\', \'options\' => [\'a\' => \'A\']], [\'key\' => \'q\']]" />');
    preg_match('/x-data="(.*?)"\s*\n\s*class=/s', $html, $found);
    $xData = html_entity_decode($found[1], ENT_QUOTES);

    $script = 'const panel = ('.$xData.')'."\n"
        .'const controls = '.json_encode($controls)."\n"
        .'const announced = []'."\n"
        .'panel.$root = { querySelectorAll: () => controls }'."\n"
        .'panel.$dispatch = (name, detail) => announced.push([name, detail])'."\n"
        .'panel.update("q", "abc"); announced.length = 0'."\n"
        .'panel.clear()'."\n"
        .'console.log(JSON.stringify({ controls, announced, filters: panel.filters }))'."\n";

    $file = tempnam(sys_get_temp_dir(), 'filters').'.js';
    file_put_contents($file, $script);
    exec('node '.escapeshellarg($file).' 2>&1', $output, $code);
    unlink($file);

    expect($code)->toBe(0, implode("\n", $output));

    return json_decode($output[0], true);
}

it('empties the boxes of the panel when the user clears all the filters', function () {
    $result = clearTheFilterPanel([
        ['type' => 'select-one', 'value' => 'a'],
        ['type' => 'text', 'value' => 'abc'],
        ['type' => 'checkbox', 'checked' => true, 'value' => 'on'],
        ['type' => 'date', 'value' => '2026-11-04'],
    ]);

    expect($result['controls'][0]['value'])->toBe('')
        ->and($result['controls'][1]['value'])->toBe('')
        ->and($result['controls'][2]['checked'])->toBeFalse()
        ->and($result['controls'][3]['value'])->toBe('');
});

it('still announces the filters as cleared and forgets them', function () {
    $result = clearTheFilterPanel([['type' => 'text', 'value' => 'abc']]);

    expect($result['announced'])->toBe([['dbl-filters-cleared', ['filters' => []]]])
        ->and($result['filters'])->toBe([]);
});
