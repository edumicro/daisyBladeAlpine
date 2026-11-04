<?php

use Illuminate\Support\Facades\Blade;

/**
 * The `icon` of a flowchart node is drawn on the server and placed in the node with `x-html`. It must be an ICON
 * (blade-icons: `heroicon-o-...` or any other installed set), never any Blade component by name: the nodes can come
 * from data that a person edits, and that person would choose which component gets rendered inside the diagram.
 */

/** The icons the component sends to the JavaScript, by the name given in the node. */
function flowchartIcons(string $nodesPhp): array
{
    $html = Blade::render("<x-dbl::display.flowchart :nodes=\"{$nodesPhp}\" />");

    return xDataConfig($html, 'dblFlowchart')['icons'];
}

it('renders as a node icon only icon components, never any Blade component by name', function () {
    $icons = flowchartIcons("[['id' => 1, 'label' => 'A', 'icon' => 'dbl::feedback.loading']]");

    expect(json_encode($icons))->not->toContain('loading');
});

it('does not render a component of the application or of another package as an icon', function () {
    // Components that exist and print something, none of them an icon.
    foreach (['dbl::feedback.alert', 'dbl::display.badge', 'dynamic-component', 'dbl::display.flowchart'] as $name) {
        $icons = flowchartIcons("[['id' => 1, 'label' => 'A', 'icon' => '{$name}']]");

        expect($icons)->not->toHaveKey($name, "{$name} must not be rendered as an icon");
    }
});

it('still draws the heroicons by their name, with the size classes', function () {
    $icons = flowchartIcons("[['id' => 1, 'label' => 'A', 'icon' => 'heroicon-o-check-circle'], ['id' => 2, 'label' => 'B', 'icon' => 'heroicon-s-bolt']]");

    expect($icons['heroicon-o-check-circle'])->toStartWith('<svg')->toContain('size-4 shrink-0')
        ->and($icons['heroicon-s-bolt'])->toStartWith('<svg');
});

it('omits an icon that does not exist', function () {
    expect(flowchartIcons("[['id' => 1, 'label' => 'A', 'icon' => 'heroicon-o-no-existe']]"))->toBe([]);
});
