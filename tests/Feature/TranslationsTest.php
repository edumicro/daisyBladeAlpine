<?php

use Illuminate\Support\Facades\App;

it('translates component strings when the app locale is Spanish', function () {
    App::setLocale('es');

    // El botón de filtros solo se pinta si el componente recibe filtros
    $this->blade('<x-dbl::display.data-table load-url="/x" :columns="[[\'key\' => \'a\', \'label\' => \'A\']]" :filters="[[\'key\' => \'a\', \'label\' => \'A\']]" />')
         ->assertSee('Buscar…')
         ->assertSee('Filtros');
});

it('keeps the English strings when the locale is English', function () {
    App::setLocale('en');

    $this->blade('<x-dbl::display.data-table load-url="/x" :columns="[[\'key\' => \'a\', \'label\' => \'A\']]" :filters="[[\'key\' => \'a\', \'label\' => \'A\']]" />')
         ->assertSee('Search...')
         ->assertSee('Filters');
});

it('has a Spanish translation for every string used by the components', function () {
    $claves = [];
    foreach (glob(__DIR__.'/../../resources/views/daisyblade/*/*.blade.php') as $vista) {
        preg_match_all("/__\('([^']+)'/", file_get_contents($vista), $coincidencias);
        $claves = array_merge($claves, $coincidencias[1]);
    }

    // Las claves con espacio de nombres (`daisyblade::map.title`) viven en los ficheros PHP de idioma
    // de cada componente; las comprueba el test siguiente.
    $claves = array_filter($claves, fn (string $clave) => ! str_contains($clave, '::'));

    $es = json_decode(file_get_contents(__DIR__.'/../../resources/lang/es.json'), true);

    expect(array_values(array_diff(array_unique($claves), array_keys($es))))->toBe([]);
});

it('has every namespaced component string in es, ca and en', function () {
    $claves = [];
    foreach (glob(__DIR__.'/../../resources/views/daisyblade/*/*.blade.php') as $vista) {
        preg_match_all("/__\\('daisyblade::([^']+)'/", file_get_contents($vista), $coincidencias);
        $claves = array_merge($claves, $coincidencias[1]);
    }

    foreach (['es', 'ca', 'en'] as $idioma) {
        App::setLocale($idioma);
        foreach (array_unique($claves) as $clave) {
            expect(trans('daisyblade::'.$clave))->not->toBe('daisyblade::'.$clave, "Falta '$clave' en $idioma");
        }
    }
});

it('has a Catalan file with exactly the keys of the Spanish and English ones, all filled in', function () {
    $keysOf = fn (string $file) => array_keys(json_decode(file_get_contents(__DIR__.'/../../resources/lang/'.$file), true, flags: JSON_THROW_ON_ERROR));
    $ca = json_decode(file_get_contents(__DIR__.'/../../resources/lang/ca.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($keysOf('ca.json'))->toEqualCanonicalizing($keysOf('es.json'))
        ->and($keysOf('en.json'))->toEqualCanonicalizing($keysOf('es.json'))
        ->and(array_filter($ca, fn ($text) => trim($text) === ''))->toBe([]);
});

it('translates to Catalan the texts that come from the JSON files', function () {
    App::setLocale('ca');

    $this->blade('<x-dbl::display.data-table load-url="/x" :columns="[[\'key\' => \'a\', \'label\' => \'A\']]" />')
         ->assertSee('Cerca…')
         ->assertSee('Sense dades');
});

// ── Texts that used to be fixed in Spanish or English ─────────────────────────

it('translates the fixed texts of the key-value editor', function (string $locale, array $expected) {
    App::setLocale($locale);

    $this->blade('<x-dbl::form.kv-editor name="p" />')->assertSee($expected, false);
})->with([
    'es' => ['es', ['Clave', 'Valor', 'Tipo', 'placeholder="Campo"', 'Añadir campo', 'title="Eliminar"']],
    'ca' => ['ca', ['Clau', 'Valor', 'Tipus', 'placeholder="Camp"', 'Afegeix un camp', 'title="Elimina"']],
    'en' => ['en', ['Key', 'Value', 'Type', 'placeholder="Field"', 'Add field', 'title="Remove"']],
]);

it('shows no Spanish in the key-value editor when the locale is English', function () {
    App::setLocale('en');

    $this->blade('<x-dbl::form.kv-editor name="p" />')
         ->assertDontSee('clave')->assertDontSee('Añadir')->assertDontSee('Eliminar');
});

it('translates the default placeholder of the list editor, and respects one that is given', function () {
    App::setLocale('es');
    $this->blade('<x-dbl::form.list-editor name="t" />')->assertSee('Escribe y pulsa Enter o coma…');
    $this->blade('<x-dbl::form.list-editor name="t" placeholder="Mi texto" />')->assertSee('Mi texto')->assertDontSee('Escribe y pulsa');

    App::setLocale('en');
    $this->blade('<x-dbl::form.list-editor name="t" />')->assertSee('Type and press Enter or comma…');
});

it('translates the texts of the node graph', function (string $locale, string $empty, array $withNode) {
    App::setLocale($locale);

    $this->blade('<x-dbl::display.node-graph :nodes="[]" />')->assertSee($empty, false);
    // A node without label is shown as "Node <id>"; the editable one has buttons with a title.
    $this->blade('<x-dbl::display.node-graph :nodes="[[\'id\' => 5, \'parent\' => null]]" editable />')->assertSee($withNode, false);
})->with([
    'es' => ['es', 'Sin nodos', ['Nodo 5', 'title="Añadir hijo"']],
    'ca' => ['ca', 'Sense nodes', ['Node 5', 'title="Afegeix un fill"']],
    'en' => ['en', 'No nodes', ['Node 5', 'title="Add child"']],
]);

it('hands the translated error texts to the javascript of the components that load or send data', function () {
    App::setLocale('es');

    $this->blade('<x-dbl::display.data-table load-url="/x" :columns="[]" />')->assertSee('No se han podido cargar los datos', false);
    $this->blade('<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load-url\' => \'/a\']]" />')->assertSee('No se ha podido cargar el contenido', false);
    $this->blade('<x-dbl::form.select name="x" options-url="/o" />')->assertSee('No se han podido cargar las opciones', false);
    $this->blade('<x-dbl::display.resource-details load-url="/r" />')->assertSee('Error al cargar el recurso', false);
    $this->blade('<x-dbl::import.spreadsheet upload-url="/u" />')->assertSee('La subida ha fallado', false);
    $this->blade('<x-dbl::sections.auto-form action="/s" />')->assertSee('Ha ocurrido un error', false);
    $this->blade('<x-dbl::sections.wizard :steps="[]" action="/s" />')->assertSee('Ha ocurrido un error', false);
});

it('gives the map a translated message for when it cannot start, in the three languages', function () {
    foreach (['es', 'ca', 'en'] as $locale) {
        App::setLocale($locale);
        $config = json_decode(html_entity_decode(
            preg_match('/data-config="([^"]*)"/', Illuminate\Support\Facades\Blade::render('<x-dbl::display.map />'), $found) ? $found[1] : '{}'
        ), true);

        expect($config['labels']['init_failed'])->toBe(trans('daisyblade::map.init_failed'))
            ->and($config['labels']['init_failed'])->not->toBe('daisyblade::map.init_failed');
    }
});
