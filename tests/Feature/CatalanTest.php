<?php

/**
 * The `ca` files are standard Catalan (the one of the IEC), not Valencian, and the buttons are written as an order to the user
 * ("Cancel·la", "Elimina", "Desa"), not as an infinitive ("Cancel·lar"). Cause 8 found both mixed in the same files.
 */

/** Every text of the `ca` files, as 'file:key' => text. */
function catalanTexts(): array
{
    $lang = __DIR__.'/../../resources/lang';
    $texts = [];

    foreach (json_decode(file_get_contents("{$lang}/ca.json"), true) as $key => $text) {
        $texts["ca.json:{$key}"] = $text;
    }
    foreach (glob("{$lang}/ca/*.php") as $file) {
        foreach (require $file as $key => $text) {
            $texts[basename($file).":{$key}"] = $text;
        }
    }

    return $texts;
}

it('writes standard Catalan, with none of the Valencian forms that slipped in', function () {
    // Valencian => the standard form: "ací" (aquí), "obri" (obre), "llevar" (treure), "pics" (vinyetes), "xicotet" (petit), "huit" (vuit).
    $valencian = '/\b(ací|ahí|allí|obri|llevar|llevar-lo|xicotet|xicoteta|huit|vosté|pics|mos|vullga|tinga|siga|puga)\b/iu';

    $offenders = [];
    foreach (catalanTexts() as $where => $text) {
        if (preg_match($valencian, $text, $found)) {
            $offenders[$where] = $found[0];
        }
    }

    expect(catalanTexts())->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});

it('writes the buttons in the imperative, not in the infinitive', function () {
    $buttons = [
        'ca.json' => ['Add badge', 'Add child', 'Add field', 'Add row', 'Apply', 'Cancel', 'Clear', 'Clear all', 'Close', 'Collapse', 'Confirm import',
            'Download template', 'Expand', 'Filter', 'Finish', 'Import another file', 'Refresh', 'Remove', 'Remove badge', 'Remove node',
            'Remove row', 'Reset', 'Retry', 'Save', 'Search', 'Select an option', 'Submit', 'Try again', 'View'],
        'calendar.php' => ['retry'],
        'flowchart.php' => ['zoom_in', 'zoom_out', 'fit'],
        'map.php' => ['search', 'open'],
        'rich-text.php' => ['undo', 'redo'],
        'signature.php' => ['clear', 'undo'],
        'upload.php' => ['choose', 'take_photo', 'cancel', 'retry', 'delete', 'move_up', 'move_down'],
    ];

    $texts = catalanTexts();
    $offenders = [];
    foreach ($buttons as $file => $keys) {
        foreach ($keys as $key) {
            expect($texts)->toHaveKey("{$file}:{$key}");
            // The first word of an infinitive ends in -ar, -er, -re or -ir (and its accent forms); an imperative does not.
            $firstWord = preg_split('/[\s\']/u', $texts["{$file}:{$key}"])[0];
            if (preg_match('/(ar|er|re|ir|ur)$/iu', $firstWord) && ! in_array($firstWord, ['Enrere'], true)) {
                $offenders["{$file}:{$key}"] = $texts["{$file}:{$key}"];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('has the same keys in ca as in es and en', function () {
    $lang = __DIR__.'/../../resources/lang';
    $keys = fn (string $code) => array_keys(json_decode(file_get_contents("{$lang}/{$code}.json"), true));

    expect($keys('ca'))->toBe($keys('es'))->and($keys('en'))->toBe($keys('es'));
});
