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

    $es = json_decode(file_get_contents(__DIR__.'/../../resources/lang/es.json'), true);

    expect(array_values(array_diff(array_unique($claves), array_keys($es))))->toBe([]);
});
