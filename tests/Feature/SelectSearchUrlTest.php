<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

/** La configuración que la vista le pasa a `dbCombobox`, ya como array (viene dentro de un JSON.parse('...') de JavaScript). */
function comboConfig(string $html): array
{
    preg_match("/dbCombobox\(JSON\.parse\('(.*?)'\)\)/s", $html, $found);

    return json_decode(stripcslashes(str_replace('\u0022', '"', $found[1])), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * `form.select` con `search-url`: el desplegable pregunta al servidor mientras se teclea, para listas que no caben
 * en la página. Comparte widget (input + hidden + lista) con el modo `searchable`.
 */
it('pinta un campo con el name, y el hidden sin name (el JS se lo pasa) para que sin JS se envíe lo tecleado', function () {
    $html = Blade::render('<x-dbl::form.select name="medicine_id" search-url="/medicines/search" label="Medicamento" />');

    expect($html)->toContain('Medicamento')
        ->and($html)->toContain('role="combobox"')
        ->and($html)->toContain('aria-controls=')
        ->and($html)->toContain('role="listbox"')
        ->and($html)->toContain('role="option"')
        ->and($html)->toContain('dbCombobox(');

    preg_match('/<input\b[^>]*role="combobox"[^>]*>/s', $html, $input);
    preg_match('/<input type="hidden"[^>]*>/s', $html, $hidden);
    expect($input[0])->toContain('name="medicine_id"')
        ->and($hidden[0])->not->toContain('name=');
});

it('vuelve a pintar lo ya elegido (value y text) cuando el formulario regresa con un error', function () {
    $html = Blade::render('<x-dbl::form.select name="medicine_id" search-url="/s" value="42" text="Paracetamol 1 g" />');

    preg_match('/<input\b[^>]*role="combobox"[^>]*>/s', $html, $input);
    preg_match('/<input type="hidden"[^>]*>/s', $html, $hidden);
    expect($input[0])->toContain('value="Paracetamol 1 g"')
        ->and($hidden[0])->toContain('value="42"');
});

it('con allow-free-text y sin text, lo guardado se enseña tal cual', function () {
    $html = Blade::render('<x-dbl::form.select name="brand" search-url="/s" value="Genérico" allow-free-text />');

    preg_match('/<input\b[^>]*role="combobox"[^>]*>/s', $html, $input);
    expect($input[0])->toContain('value="Genérico"');
});

it('lleva la configuración al JS: url, parámetro, límite, mínimo, debounce y los engarces', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/s" query-param="term" :params="[\'country_code\' => \'ES\']" :limit="10" :min-chars="3" :debounce="400" depends-on="lab" option-label="nombre" on-select="alert(row.id)" process-results="data.hits" on-error="status === 401" />');

    expect(comboConfig($html))->toMatchArray([
        'searchUrl' => '/s', 'queryParam' => 'term', 'params' => ['country_code' => 'ES'], 'limit' => 10, 'minChars' => 3,
        'debounce' => 400, 'dependsOn' => 'lab', 'optionLabel' => 'nombre', 'onSelect' => 'alert(row.id)',
        'processResults' => 'data.hits', 'onError' => 'status === 401',
    ]);
    expect($html)->toContain('onDependencyChange($event)');
});

it('por defecto pregunta con q, límite 20, mínimo de 2 caracteres y 250 ms', function () {
    expect(comboConfig(Blade::render('<x-dbl::form.select name="m" search-url="/s" />')))
        ->toMatchArray(['queryParam' => 'q', 'limit' => 20, 'minChars' => 2, 'debounce' => 250]);
});

it('search-url junto a options-url es un error del programador, con mensaje claro', function () {
    Blade::render('<x-dbl::form.select name="m" search-url="/s" options-url="/o" />');
})->throws(ViewException::class, 'cannot be used together');

it('search-url con multiple avisa de que no se admite', function () {
    Blade::render('<x-dbl::form.select name="m" search-url="/s" :multiple="true" />');
})->throws(ViewException::class, 'multiple');

it('pinta el error de validación y la pista, y marca el input con input-error', function () {
    $errors = (new Illuminate\Support\ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['m' => ['Elige uno']]));
    view()->share('errors', $errors);
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/s" hint="Escribe el nombre" />');

    expect($html)->toContain('Elige uno')->toContain('Escribe el nombre')->toContain('input-error');
});

it('los textos de los estados salen traducidos', function () {
    app()->setLocale('es');
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/s" :min-chars="3" />');

    expect(comboConfig($html)['messages'])->toBe([
        'minChars' => 'Escribe 3 o más caracteres', 'searching' => 'Buscando…', 'noResults' => 'No hay nada con eso',
        'searchFailed' => 'No se ha podido buscar',
    ])->and($html)->toContain('Reintentar');
});

it('el modo searchable de siempre sigue filtrando en el navegador y ahora es un combobox accesible', function () {
    $html = Blade::render('<x-dbl::form.select name="person" searchable :options="[\'a1\' => \'Ana\', \'b2\' => \'Pau\']" value="b2" />');

    preg_match('/<input\b[^>]*role="combobox"[^>]*>/s', $html, $input);
    preg_match('/<input type="hidden"[^>]*>/s', $html, $hidden);
    expect($html)->toContain('dbCombobox(')->toContain('role="option"')
        ->and($input[0])->toContain('value="Pau"')->not->toContain('name=')
        ->and($hidden[0])->toContain('name="person"')->toContain('value="b2"');
    expect(comboConfig($html))->toMatchArray(['searchUrl' => '', 'options' => ['a1' => 'Ana', 'b2' => 'Pau']]);
});
