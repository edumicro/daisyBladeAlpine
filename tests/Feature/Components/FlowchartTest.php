<?php

use Illuminate\Support\Facades\Blade;

/** La configuración que el Blade pasa a `dblFlowchart(@js($config))`. */
function flowchartConfig(string $html): array
{
    return xDataConfig($html, 'dblFlowchart');
}

$nodes = [['id' => 'a', 'label' => 'Pedido'], ['id' => 'b', 'label' => 'Envío', 'x' => 300, 'y' => 40]];
$edges = [['id' => 'e1', 'from' => 'a', 'to' => 'b', 'label' => 'genera']];

it('renders the flowchart with its nodes and edges as config', function () use ($nodes, $edges) {
    $html = Blade::render('<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" />', compact('nodes', 'edges'));
    $c = flowchartConfig($html);

    expect($html)->toContain('x-data="dblFlowchart(JSON.parse(')
        ->and($c['nodes'])->toHaveCount(2)
        ->and($c['nodes'][1]['x'])->toBe(300)
        ->and($c['edges'][0]['label'])->toBe('genera')
        ->and($c['editable'])->toBeFalse()
        ->and($c['grid'])->toBe(0)
        ->and($html)->toContain('overflow-auto')
        ->and($html)->toContain('height: 28rem')
        ->and($html)->not->toContain('wire:')
        ->and($html)->not->toContain(' @click')
        // Sin editar no hay asa para crear flechas ni ayuda de teclado.
        ->and($html)->not->toContain('linkDown')
        ->and($html)->not->toContain('-help');
});

it('exposes editable, name, grid and show-muted to the component', function () use ($nodes, $edges) {
    $html = Blade::render(
        '<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" editable name="flujo" :grid="20" show-muted height="40vh" />',
        compact('nodes', 'edges'),
    );
    $c = flowchartConfig($html);

    expect($c['editable'])->toBeTrue()
        ->and($c['name'])->toBe('flujo')
        ->and($c['grid'])->toBe(20)
        ->and($c['showMuted'])->toBeTrue()
        ->and($html)->toContain('linkDown($event, n)')
        ->and($html)->toContain('height: 40vh')
        ->and($html)->toContain('x-on:dbl-flowchart-set.window="onSet($event.detail)"')
        ->and($html)->toContain('x-on:dbl-flowchart-focus.window="onFocus($event.detail)"')
        ->and($html)->toContain('x-on:dbl-flowchart-remove-edge.window="onRemoveEdge($event.detail)"')
        ->and($html)->toContain('x-on:dbl-flowchart-remove-node.window="onRemoveNode($event.detail)"');
});

it('ignores a height that is not a plain CSS length', function () {
    $html = Blade::render('<x-dbl::display.flowchart height="1px; background:url(x)" />');

    expect($html)->toContain('height: 28rem')->not->toContain('url(x)');
});

it('forwards the listeners of the parent to the root element', function () use ($nodes, $edges) {
    $html = Blade::render(
        '<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" x-on:dbl-flowchart-node-clicked="picked = $event.detail.id" class="mt-4" />',
        compact('nodes', 'edges'),
    );

    expect($html)->toContain('x-on:dbl-flowchart-node-clicked="picked = $event.detail.id"')->toContain('mt-4');
});

it('never prints labels as markup', function () {
    $evil = '</script><img src=x onerror=alert(1)>"\'&';
    $html = Blade::render(
        '<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" label="<b>t</b>" />',
        [
            'nodes' => [['id' => 'a', 'label' => $evil, 'badge' => $evil], ['id' => 'b', 'label' => 'B']],
            'edges' => [['id' => 'e', 'from' => 'a', 'to' => 'b', 'label' => $evil]],
        ],
    );

    expect($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('</script><img')
        ->and($html)->not->toContain('<b>t</b>')
        ->and($html)->toContain('&lt;b&gt;t&lt;/b&gt;')
        // Los textos del padre solo llegan al DOM con x-text; x-html es solo para los iconos del servidor.
        ->and($html)->not->toContain('x-html="n.')
        ->and($html)->not->toContain('x-html="e.')
        ->and(flowchartConfig($html)['nodes'][0]['label'])->toBe($evil);
});

it('defaults to a vertical layout and accepts a horizontal one', function () {
    expect(flowchartConfig(Blade::render('<x-dbl::display.flowchart />'))['direction'])->toBe('vertical')
        ->and(flowchartConfig(Blade::render('<x-dbl::display.flowchart direction="horizontal" />'))['direction'])->toBe('horizontal')
        ->and(flowchartConfig(Blade::render('<x-dbl::display.flowchart direction="diagonal" />'))['direction'])->toBe('vertical');
});

it('passes shape, group, description and icon of each node through', function () {
    $nodes = [
        ['id' => 1, 'label' => 'Inicio', 'shape' => 'pill', 'group' => 'Calle A', 'description' => 'Primer paso', 'icon' => 'heroicon-o-check-circle'],
        ['id' => 2, 'label' => '¿Sigue?', 'shape' => 'diamond'],
    ];
    $c = flowchartConfig(Blade::render('<x-dbl::display.flowchart :nodes="$nodes" />', compact('nodes')));

    expect($c['nodes'][0])->toMatchArray(['shape' => 'pill', 'group' => 'Calle A', 'description' => 'Primer paso'])
        ->and($c['nodes'][1]['shape'])->toBe('diamond');
});

it('renders node icons on the server with the package icon system', function () {
    $nodes = [['id' => 1, 'label' => 'A', 'icon' => 'heroicon-o-check-circle'], ['id' => 2, 'label' => 'B', 'icon' => 'no-existe-este-icono']];
    $c = flowchartConfig(Blade::render('<x-dbl::display.flowchart :nodes="$nodes" />', compact('nodes')));

    expect($c['icons']['heroicon-o-check-circle'])->toStartWith('<svg')
        ->and($c['icons']['no-existe-este-icono'])->toBe('');
});

it('renders the texts in the app language', function (string $locale, string $muted, string $empty) {
    app()->setLocale($locale);
    $html = Blade::render('<x-dbl::display.flowchart />');

    expect($html)->toContain($muted)->toContain($empty);
})->with([
    ['es', 'Mostrar ocultos', 'No hay elementos'],
    ['ca', 'Mostra els ocults', 'No hi ha elements'],
    ['en', 'Show hidden', 'Nothing to show'],
]);

it('puts the box and arrow aria templates in the config', function () {
    app()->setLocale('es');
    $c = flowchartConfig(Blade::render('<x-dbl::display.flowchart />'));

    expect($c['labels']['node'])->toBe('Caja :label')->and($c['labels']['edge'])->toBe('Flecha de :from a :to');
});

it('passes fit to the component and renders the zoom buttons with translated labels', function () {
    expect(flowchartConfig(Blade::render('<x-dbl::display.flowchart />'))['fit'])->toBeFalse();

    app()->setLocale('es');
    $html = Blade::render('<x-dbl::display.flowchart fit />');

    expect(flowchartConfig($html)['fit'])->toBeTrue()
        ->and($html)->toContain('zoomIn()')->toContain('zoomOut()')->toContain('fit(true)')
        ->and($html)->toContain('aria-label="Acercar"')->toContain('aria-label="Alejar"')
        ->and($html)->toContain('aria-label="Encuadrar todo el diagrama"')
        ->and($html)->toContain('<svg');   // los iconos heroicon de los botones
});

it('shows the link handle only on hover or focus, and always on touch screens', function () {
    $html = Blade::render('<x-dbl::display.flowchart editable />');

    expect($html)->toContain('.dbl-flowchart .dbl-flowchart-handle { opacity: 0')
        ->and($html)->toContain('@media (hover: none)')
        ->and($html)->toContain('dbl-flowchart-handle absolute');
});

it('gives every rendered chart its own help id', function () use ($nodes, $edges) {
    $ids = collect(range(1, 2))->map(function () use ($nodes, $edges) {
        $html = Blade::render('<x-dbl::display.flowchart :nodes="$nodes" :edges="$edges" editable name="igual" />', compact('nodes', 'edges'));
        preg_match('/id="(dbf-[A-Za-z0-9]+)-help"/', $html, $m);

        return $m[1];
    });

    expect($ids[0])->not->toBe($ids[1]);
});

it('flowchart moves the canvas by the centering offset before scaling it', function () {
    $html = Blade::render('<x-dbl::display.flowchart :nodes="[[\'id\' => 1]]" fit />');

    expect($html)->toContain('translate(${offset.x}px,${offset.y}px) scale(${scale})');
});
