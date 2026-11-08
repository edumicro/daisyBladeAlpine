<?php

namespace Edumicro\DaisyBlade\Tests\Catalog;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The CATALOG OF COMPONENTS: at least one example of every view of the package, with the props that it typically gets.
 *
 * The architecture tests (tests/Feature/Catalog) and the smoke test in the browser (tests/Browser/CatalogBrowserTest.php) paint
 * this list and look at the RESULT, not at the source of the views. A view without an example fails `CatalogCoversEveryViewTest`,
 * so a new component cannot escape the rules: write its example here.
 *
 * Texts that the CALLER writes end with `§` (Example::CALLER_MARK): they are not for the component to translate. Any other text
 * on the page must carry the marker of the translator of the tests (MarkingTranslator).
 */
final class Catalog
{
    /** @return array<string, Example> by name. */
    public static function examples(): array
    {
        $examples = [];
        foreach (self::list() as $example) {
            $examples[$example->name()] = $example;
        }

        return $examples;
    }

    /** The components (`form.input`) that have a view in the package. */
    public static function componentsWithAView(): array
    {
        $root = realpath(__DIR__.'/../../resources/views/daisyblade');
        $components = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $relative = substr($file->getRealPath(), strlen($root) + 1, -strlen('.blade.php'));
                $components[] = str_replace('/', '.', $relative);
            }
        }
        sort($components);

        return $components;
    }

    /** @return list<Example> */
    private static function list(): array
    {
        $user = Example::CALLER_MARK;
        $menu = [
            ['label' => "Home{$user}", 'url' => '/', 'icon' => 'heroicon-o-home', 'active' => true],
            ['label' => "Reports{$user}", 'icon' => 'heroicon-o-chart-bar', 'children' => [['label' => "Monthly{$user}", 'url' => '/m'], ['label' => "Yearly{$user}", 'url' => '/y']]],
            ['label' => "Off{$user}", 'url' => '/off', 'disabled' => true, 'badge' => '3'],
        ];
        $fields = [
            'name' => ['type' => 'text', 'label' => "Name{$user}", 'required' => true, 'placeholder' => "Your name{$user}"],
            'bio' => ['type' => 'textarea', 'label' => "Bio{$user}", 'rows' => 3],
            'age' => ['type' => 'number', 'label' => "Age{$user}", 'attrs' => ['min' => 1]],
            'role' => ['type' => 'select', 'label' => "Role{$user}", 'options' => ['a' => "Admin{$user}", 'u' => "User{$user}"]],
            'active' => ['type' => 'checkbox', 'label' => "Active{$user}"],
        ];
        // The `attrs` of a schema are the bag of attributes of a field: they must reach the control once, and win over the view's own.
        $fieldsWithAttrs = [
            'text' => ['type' => 'textarea', 'label' => "Text{$user}", 'attrs' => ['rows' => 10, 'placeholder' => "mine{$user}", 'class' => 'user-class']],
            'search' => ['type' => 'text', 'label' => "Search{$user}", 'attrs' => ['type' => 'search', 'placeholder' => "mine{$user}"]],
            'amount' => ['type' => 'number', 'label' => "Amount{$user}", 'attrs' => ['step' => '0.5', 'min' => 5, 'max' => 9, 'placeholder' => "mine{$user}"]],
        ];
        $calendarProps = ['loadUrl' => '/cal', 'initialDate' => '2026-05-10'];
        $mapProps = ['center' => [39.47, -0.37], 'zoom' => 11];

        return [
            // ── actions ──
            Example::of('actions.button')->props(['label' => "Save{$user}"])->texts('label'),
            Example::of('actions.button', 'icon and loading')->props(['icon' => 'heroicon-o-plus', 'loading' => true, 'label' => "Add{$user}"])->texts('label'),
            Example::of('actions.button', 'only the slot')->slot("Guardar{$user}"),
            Example::of('actions.button', 'as a link')->props(['href' => '/go', 'label' => "Go{$user}"])->texts('label'),
            Example::of('actions.fab')->props(['icon' => 'heroicon-o-plus', 'tooltip' => "New{$user}"])->texts('tooltip')->slot("<button>Opt{$user}</button>"),
            Example::of('actions.modal')->props(['id' => 'demo-modal', 'title' => "Title{$user}"])->texts('title')->slot("<p>Body{$user}</p>"),
            Example::of('actions.swap')->props(['offLabel' => "Dark{$user}", 'onLabel' => "Light{$user}"])->texts('offLabel', 'onLabel'),

            // ── components ──
            Example::of('components.icon')->props(['name' => 'home']),
            Example::of('components.ui.logo')->props(['href' => '/']),

            // ── display ──
            Example::of('display.accordion')->props(['items' => [['title' => "One{$user}", 'content' => "<p>First{$user}</p>", 'open' => true], ['title' => "Two{$user}", 'content' => "<p>Second{$user}</p>"]]])->texts('items.*.title'),
            Example::of('display.avatar')->props(['src' => '/pixel.png']),
            Example::of('display.avatar', 'initials')->props(['initials' => "AB{$user}", 'online' => true])->texts('initials'),
            Example::of('display.avatar', 'with alt')->props(['src' => '/pixel.png', 'alt' => "Ana{$user}"])->texts('alt'),
            Example::of('display.badge')->props(['label' => "New{$user}"])->texts('label'),
            Example::of('display.badge', 'slot')->slot("Hot{$user}"),
            Example::of('display.calendar')->props($calendarProps)->modules('calendar'),
            // `body` is HTML of the developer (it is on the list of {!! !!} of OutputEscapingTest), so it is not given the hostile text.
            Example::of('display.card')->props(['title' => "Card{$user}", 'subtitle' => "Sub{$user}", 'body' => "Body{$user}", 'image' => '/pixel.png', 'imageAlt' => "Pic{$user}", 'actions' => [['label' => "Open{$user}", 'url' => '/open']]])->texts('title', 'subtitle', 'imageAlt', 'actions.*.label'),
            Example::of('display.card', 'with an image and a slot')->props(['image' => '/pixel.png'])->slot("<p>In{$user}</p>"),
            Example::of('display.carousel')->props(['items' => [['image' => '/pixel.png', 'title' => "One{$user}", 'description' => "First{$user}", 'alt' => "Alt1{$user}"], ['image' => '/pixel.png', 'title' => "Two{$user}"]]])->texts('items.*.title', 'items.*.description', 'items.*.alt'),
            Example::of('display.chat-bubble')->props(['message' => "Hello{$user}", 'sender' => "Ana{$user}", 'timestamp' => '10:00', 'actions' => [['label' => "Reply{$user}", 'url' => '#']]])->texts('message', 'sender', 'actions.*.label'),
            Example::of('display.chat-bubble', 'loading')->props(['loading' => true, 'variant' => 'received']),
            Example::of('display.collapse')->props(['title' => "More{$user}", 'content' => "Details{$user}"])->texts('title'),
            Example::of('display.compare')->props(['leftLabel' => "Before{$user}", 'rightLabel' => "After{$user}", 'rows' => [['label' => "Price{$user}", 'left' => '1', 'right' => '2', 'description' => "Up{$user}"]]])->texts('leftLabel', 'rightLabel', 'rows.*.label', 'rows.*.left', 'rows.*.right', 'rows.*.description'),
            Example::of('display.data-table')->props(['loadUrl' => '/rows', 'columns' => [['key' => 'id', 'label' => "Id{$user}", 'sortable' => true], ['key' => 'name', 'label' => "Name{$user}"]], 'toolbar' => [['label' => "Export{$user}", 'url' => '/x', 'icon' => 'heroicon-o-arrow-down-tray']]])->texts('columns.*.label', 'toolbar.*.label'),
            Example::of('display.diff')->props(['changes' => [['type' => 'added', 'content' => "new line{$user}", 'line_number' => 1], ['type' => 'removed', 'content' => "old line{$user}", 'line_number' => 2]]])->texts('changes.*.content')->technicalTexts('plaintext'), // the default `language`: the name of a syntax, not a word of a language
            Example::of('display.flowchart')->props(['nodes' => [['id' => 'a', 'label' => "Start{$user}"], ['id' => 'b', 'label' => "End{$user}"]], 'edges' => [['from' => 'a', 'to' => 'b']]])->texts('nodes.*.label')->modules('flowchart'),
            Example::of('display.hover-3d-card')->props(['image' => '/pixel.png', 'imageAlt' => "Pic{$user}", 'title' => "Hover{$user}", 'description' => "Desc{$user}", 'tags' => ["tag{$user}"]])->texts('imageAlt', 'title', 'description', 'tags.*'),
            Example::of('display.hover-3d-card', 'default alt')->props(['image' => '/pixel.png', 'title' => "Hover{$user}"]),
            Example::of('display.hover-gallery')->props(['images' => [['src' => '/pixel.png', 'title' => "One{$user}", 'caption' => "Cap{$user}", 'alt' => "Alt{$user}", 'description' => "D{$user}"]]])->texts('images.*.title', 'images.*.caption', 'images.*.alt', 'images.*.description'),
            Example::of('display.kbd')->props(['keys' => ["Ctrl{$user}", "K{$user}"]])->texts('keys.*'),
            Example::of('display.list')->props(['items' => [['title' => "One{$user}", 'description' => "First{$user}"], "Two{$user}"]])->texts('items.0.title', 'items.0.description', 'items.1'),
            Example::of('display.map')->props($mapProps)->modules('map')->notInBrowser('needs the tiles of a map server'),
            Example::of('display.map', 'with layers')->props($mapProps + ['layers' => [['id' => 'a', 'label' => "Places{$user}", 'load_url' => '/geo/a', 'cluster' => true]]])->texts('layers.*.label')->modules('map')->notInBrowser('needs the tiles of a map server'),
            Example::of('display.mask')->props(['src' => '/pixel.png', 'alt' => "Mask{$user}"])->texts('alt'),
            Example::of('display.node-graph')->props(['nodes' => [['id' => 'r', 'label' => "Root{$user}", 'status' => "ok{$user}"], ['id' => 'c', 'parent' => 'r', 'label' => "Child{$user}", 'badges' => [['label' => "b{$user}", 'variant' => 'info']]]]])->texts('nodes.*.label', 'nodes.0.status', 'nodes.1.badges.*.label'),
            Example::of('display.radial-progress')->props(['value' => 40, 'label' => "Done{$user}"])->texts('label'),
            Example::of('display.resource-details')->props(['loadUrl' => '/res', 'fields' => ['name']]),
            Example::of('display.stat')->props(['title' => "Users{$user}", 'value' => '10', 'description' => "Total{$user}", 'unit' => 'k', 'trend' => 'up', 'trendValue' => '5%'])->texts('title', 'description'),
            Example::of('display.status')->props(['status' => 'pending', 'text' => "Waiting{$user}"])->texts('text'),
            Example::of('display.table')->props(['columns' => [['key' => 'name', 'label' => "Name{$user}"]], 'rows' => [['name' => "Ana{$user}"]]])->texts('columns.*.label', 'rows.*.name'),
            Example::of('display.text-rotate')->props(['texts' => ["One{$user}", "Two{$user}"]])->texts('texts.*'),
            Example::of('display.timeline')->props(['items' => [['title' => "Start{$user}", 'description' => "Went{$user}", 'date' => '2026-01-01', 'tags' => [['label' => "t{$user}", 'color' => 'info']]]]])->texts('items.*.title', 'items.*.description', 'items.0.tags.*.label'),
            // A tree is an array whose KEYS are the titles: the text of the user is in the keys.
            Example::of('display.tree')->props(['items' => ["Root{$user}" => ["Leaf{$user}", "Doc{$user}" => "About{$user}"]]])->hostileProps(['items' => [Example::HOSTILE => [Example::HOSTILE, Example::HOSTILE => Example::HOSTILE]]]),

            // ── feedback ──
            Example::of('feedback.alert')->props(['message' => "Careful{$user}", 'title' => "Warning{$user}", 'type' => 'warning'])->texts('message', 'title'),
            Example::of('feedback.loading')->props(['message' => "Wait{$user}"])->texts('message'),
            Example::of('feedback.loading', 'default message')->props([]),
            Example::of('feedback.progress')->props(['value' => 30, 'label' => "Upload{$user}"])->texts('label'),
            Example::of('feedback.skeleton')->props(['type' => 'card']),
            Example::of('feedback.toast')->props(['message' => "Saved{$user}", 'closable' => true])->texts('message'),
            Example::of('feedback.tooltip')->props(['text' => "Help{$user}"])->texts('text')->slot("<span>Hover{$user}</span>"),

            // ── form ──
            Example::of('form.checkbox')->props(['name' => 'terms', 'label' => "Accept{$user}", 'hint' => "Read it{$user}"])->texts('label', 'hint'),
            Example::of('form.fields')->props(['fields' => $fields])->texts('*.label', '*.placeholder'),
            Example::of('form.fields', 'alpine mode')->props(['fields' => $fields, 'mode' => 'alpine'])->texts('*.label', '*.placeholder')->inScope('{ data: {}, errors: {} }'),
            Example::of('form.fields', 'with values')->props(['fields' => $fields, 'values' => ['name' => "Ana{$user}", 'bio' => "Hello{$user}", 'active' => true]]),
            Example::of('form.fields', 'attrs in the schema')->props(['fields' => $fieldsWithAttrs]),
            Example::of('form.fields', 'attrs in the schema, alpine mode')->props(['fields' => $fieldsWithAttrs, 'mode' => 'alpine'])->inScope('{ data: {}, errors: {} }'),
            Example::of('form.filters')->props(['filters' => [['key' => 'q', 'label' => "Search{$user}", 'type' => 'text'], ['key' => 's', 'label' => "State{$user}", 'type' => 'select', 'options' => ['a' => "A{$user}"]]]])->texts('filters.*.label'),
            Example::of('form.input')->props(['name' => 'email', 'label' => "Email{$user}", 'placeholder' => "you@{$user}", 'hint' => "We write{$user}", 'icon' => 'heroicon-o-envelope'])->texts('label', 'placeholder', 'hint'),
            Example::of('form.input', 'with an error')->props(['name' => 'email', 'error' => "Bad{$user}"])->texts('error'),
            Example::of('form.kv-editor')->props(['name' => 'meta', 'label' => "Meta{$user}", 'value' => ['a' => '1']])->texts('label')
                ->technicalTexts('true', 'false', 'str', 'num', 'bool', '{"key": "value"}'),
            Example::of('form.list-editor')->props(['name' => 'tags', 'label' => "Tags{$user}", 'value' => ["x{$user}"]])->texts('label'),
            Example::of('form.radio')->props(['name' => 'size', 'label' => "Size{$user}", 'options' => ['s' => "Small{$user}", 'l' => "Large{$user}"], 'value' => 's'])->texts('label', 'options.*')
                // The same id on every radio would be invalid HTML: the id of the caller goes to the first one only (see the view).
                ->exceptInBag('id'),
            Example::of('form.repeater')->props(['name' => 'lines', 'label' => "Lines{$user}", 'fields' => [['key' => 'item', 'label' => "Item{$user}", 'type' => 'text'], ['key' => 'qty', 'label' => "Qty{$user}", 'type' => 'number', 'step' => '1'], ['key' => 'note', 'label' => "Note{$user}", 'type' => 'textarea']]])->texts('label', 'fields.*.label'),
            Example::of('form.repeater', 'events of the caller')->props(['name' => 'lines', 'fields' => [['key' => 'item', 'label' => "Item{$user}", 'type' => 'text']], 'events' => ['root' => ['keydown.enter.prevent' => 'add()'], 'add' => ['click.stop' => 'noop()']]])->texts('fields.*.label'),
            Example::of('form.repeater', 'attrs in the schema')->props(['name' => 'lines', 'fields' => [
                ['key' => 'amount', 'label' => "Amount{$user}", 'type' => 'number', 'step' => '1', 'field_min' => 0, 'field_max' => 99, 'attrs' => ['step' => '0.5', 'min' => 5, 'max' => 9, 'placeholder' => "mine{$user}"]],
                ['key' => 'note', 'label' => "Note{$user}", 'type' => 'textarea', 'attrs' => ['rows' => 9, 'placeholder' => "mine{$user}"]],
            ]]),
            Example::of('form.rich-text')->props(['name' => 'body', 'label' => "Body{$user}", 'placeholder' => "Write{$user}"])->texts('label', 'placeholder')->notInBrowser('loads the editor from a CDN'),
            Example::of('form.select')->props(['name' => 'city', 'label' => "City{$user}", 'options' => ['v' => "Valencia{$user}"], 'placeholder' => "Pick{$user}"])->texts('label', 'placeholder', 'options.*'),
            Example::of('form.select', 'searchable')->props(['name' => 'city', 'searchable' => true, 'options' => ['v' => "Valencia{$user}"]])->texts('options.*'),
            Example::of('form.select', 'remote')->props(['name' => 'city', 'loadUrl' => '/options', 'placeholder' => "Pick{$user}"])->texts('placeholder'),
            Example::of('form.select', 'remote with fixed params')->props(['name' => 'city', 'loadUrl' => '/options', 'params' => ['country_code' => 'ES']]),
            Example::of('form.select', 'search-url')->props(['name' => 'medicine', 'label' => "Medicine{$user}", 'searchUrl' => '/medicines', 'placeholder' => "Type{$user}", 'hint' => "Name or code{$user}", 'value' => 5, 'text' => "Ibuprofen{$user}"])->texts('label', 'placeholder', 'hint', 'text'),
            Example::of('form.select', 'search-url with every hook')->props(['name' => 'medicine', 'searchUrl' => '/medicines', 'queryParam' => 'term', 'pageParam' => 'p', 'params' => ['country_code' => 'ES'], 'dependsOn' => 'country', 'limit' => 10, 'minChars' => 3, 'debounce' => 400, 'allowFreeText' => true, 'optionLabel' => 'name', 'processResults' => 'response.items', 'listClass' => 'my-list']),
            Example::of('form.signature')->props(['name' => 'sign', 'label' => "Sign{$user}", 'signer' => "Ana{$user}"])->texts('label', 'signer')->notInBrowser('needs the SignaturePad library, which is not in node_modules'),
            Example::of('form.textarea')->props(['name' => 'note', 'label' => "Note{$user}", 'placeholder' => "Type{$user}", 'maxLength' => 100, 'hint' => "Short{$user}"])->texts('label', 'placeholder', 'hint'),
            Example::of('form.toggle')->props(['name' => 'on', 'label' => "On{$user}", 'hint' => "Switch{$user}"])->texts('label', 'hint'),
            Example::of('form.upload')->props(['name' => 'files', 'label' => "Files{$user}", 'uploadUrl' => '/up', 'multiple' => true, 'existing' => [['id' => 1, 'name' => "a.pdf{$user}", 'url' => '/a.pdf']]])->texts('label', 'existing.*.name')->modules('upload'),
            Example::of('form.field-error', 'a message of the caller')->props(['message' => "Wrong{$user}"])->texts('message'),
            Example::of('form.field-error', 'from the 422 of the server')->props(['name' => 'email', 'mode' => 'alpine'])->inScope('{ errors: {} }'),
            Example::of('form.validator')->props(['field' => 'email', 'message' => "Wrong{$user}"])->texts('message'),

            // ── import ──
            Example::of('import.spreadsheet')->props(['uploadUrl' => '/import', 'templateUrl' => '/tpl', 'label' => "Import{$user}"])->texts('label')->technicalTexts('.xlsx,.xls,.csv'),

            // ── layout ──
            Example::of('layout.app')->props(['title' => "Page{$user}"])->texts('title')->slot("<p>Content{$user}</p>")->notInBrowser('it is a whole HTML page'),
            Example::of('layout.auth')->props(['title' => "Sign in{$user}"])->texts('title')->slot("<p>Form{$user}</p>")->notInBrowser('it is a whole HTML page'),
            Example::of('layout.divider')->props(['label' => "Or{$user}"])->texts('label'),
            Example::of('layout.footer')->props(['label' => "Foot{$user}"])->texts('label')->slot("<a href=\"/\">Link{$user}</a>"),
            Example::of('layout.hero')->props(['label' => "Hero{$user}"])->texts('label')->slot("<h1>Big{$user}</h1>"),
            Example::of('layout.indicator')->props(['label' => "Count{$user}"])->texts('label')->slot("<button>Inbox{$user}</button>"),
            Example::of('layout.join')->props(['label' => "Group{$user}"])->texts('label')->slot("<button>A{$user}</button><button>B{$user}</button>"),
            Example::of('layout.section-wrapper')->props(['title' => "Section{$user}", 'description' => "About{$user}"])->texts('title', 'description')->slot("<p>In{$user}</p>"),
            Example::of('layout.stack')->props(['label' => "Pile{$user}"])->texts('label')->slot("<div>1{$user}</div><div>2{$user}</div>"),

            // ── navigation ──
            Example::of('navigation.breadcrumb')->props(['items' => [['label' => "Home{$user}", 'url' => '/'], ['label' => "Users{$user}", 'url' => '/u'], ['label' => "Ana{$user}"]]])->texts('items.*.label'),
            Example::of('navigation.dock')->props(['items' => [['label' => "Home{$user}", 'icon' => 'heroicon-o-home', 'url' => '/'], ['label' => "Inbox{$user}", 'icon' => 'heroicon-o-inbox', 'url' => '/i', 'badge' => '2']]])->texts('items.*.label'),
            Example::of('navigation.menu')->props(['items' => $menu])->texts('items.*.label', 'items.1.children.*.label'),
            Example::of('navigation.navbar')->props(['brand' => "Brand{$user}"])->texts('brand')->slot("<a href=\"/\">Link{$user}</a>"),
            Example::of('navigation.pagination')->props(['meta' => ['current_page' => 2, 'last_page' => 5, 'from' => 16, 'to' => 30, 'total' => 70, 'per_page' => 15], 'baseUrl' => '/list']),
            Example::of('navigation.sidebar')->slot("<a href=\"/\">Home{$user}</a>"),
            Example::of('navigation.sidebar-tree')->props(['items' => [['id' => 'a', 'label' => "Docs{$user}", 'icon' => 'heroicon-o-book-open', 'children' => [['label' => "Intro{$user}", 'url' => '/intro']]], ['id' => 'b', 'label' => "Blog{$user}", 'url' => '/blog']]])->texts('items.*.label', 'items.0.children.*.label'),
            Example::of('navigation.steps')->props(['steps' => [['label' => "One{$user}", 'description' => "First{$user}"], ['label' => "Two{$user}"]], 'currentStep' => 1])->texts('steps.*.label', 'steps.*.description'),
            Example::of('navigation.tabs')->props(['items' => [['label' => "A{$user}", 'content' => "<p>Tab A{$user}</p>", 'badge' => '2'], ['label' => "B{$user}", 'content' => "<p>Tab B{$user}</p>"]]])->texts('items.*.label'),

            // ── sections ──
            Example::of('sections.auto-form')->props(['fields' => $fields, 'submitUrl' => '/save', 'label' => "Profile{$user}"])->texts('label', 'fields.*.label', 'fields.*.placeholder'),
            Example::of('sections.tabs')->props(['tabs' => [['label' => "Lazy{$user}", 'load_url' => '/frag'], ['label' => "Other{$user}", 'load_url' => '/frag2']]])->texts('tabs.*.label'),
            Example::of('sections.wizard')->props(['submitUrl' => '/wiz', 'steps' => [['label' => "Who{$user}", 'description' => "You{$user}", 'schema' => ['name' => ['type' => 'text', 'label' => "Name{$user}"]]], ['label' => "Done{$user}", 'schema' => ['ok' => ['type' => 'checkbox', 'label' => "Sure{$user}"]]]]])->texts('steps.*.label', 'steps.*.description'),
            // ── variants that exercise other branches of the views ──
            Example::of('actions.button', 'every colour and size')->props(['label' => "Go{$user}", 'color' => 'error', 'size' => 'xs', 'disabled' => true, 'iconOnly' => false, 'icon' => 'heroicon-o-trash', 'iconPosition' => 'right'])->texts('label'),
            Example::of('actions.modal', 'not closable')->props(['id' => 'm2', 'title' => "Locked{$user}", 'closable' => false, 'size' => 'full'])->texts('title')->slot("<p>x{$user}</p>"),
            Example::of('display.badge', 'with an icon')->props(['label' => "Ok{$user}", 'icon' => 'heroicon-o-check', 'outline' => true, 'color' => 'success'])->texts('label'),
            Example::of('display.data-table', 'with params')->props(['loadUrl' => '/rows', 'params' => ['status' => 'open'], 'columns' => [['key' => 'id', 'label' => "Id{$user}"]]])->texts('columns.*.label'),
            Example::of('display.data-table', 'with filters and no search')->props(['loadUrl' => '/rows', 'searchable' => false, 'emptyText' => "Nothing{$user}", 'label' => "Rows{$user}", 'columns' => [['key' => 'id', 'label' => "Id{$user}"]], 'filters' => [['key' => 'q', 'label' => "Text{$user}", 'type' => 'text']]])->texts('emptyText', 'label', 'columns.*.label', 'filters.*.label'),
            Example::of('display.table', 'striped')->props(['striped' => true, 'bordered' => true, 'compact' => true, 'label' => "People{$user}", 'columns' => [['key' => 'name', 'label' => "Name{$user}"]], 'rows' => [['name' => "Ana{$user}"], ['name' => "Luis{$user}"]]])->texts('label', 'columns.*.label', 'rows.*.name'),
            Example::of('display.table', 'empty')->props(['columns' => [['key' => 'name', 'label' => "Name{$user}"]], 'rows' => []])->texts('columns.*.label'),
            Example::of('display.stat', 'with an icon and a down trend')->props(['title' => "Sales{$user}", 'value' => '3', 'icon' => 'heroicon-o-banknotes', 'trend' => 'down', 'trendValue' => '2%'])->texts('title'),
            Example::of('display.timeline', 'horizontal')->props(['orientation' => 'horizontal', 'items' => [['title' => "A{$user}", 'icon' => 'heroicon-o-check'], ['title' => "B{$user}", 'time' => '10:00']]])->texts('items.*.title'),
            Example::of('display.list', 'ordered')->props(['ordered' => true, 'hoverable' => true, 'items' => ["One{$user}", "Two{$user}"]])->texts('items.*'),
            Example::of('display.status', 'as a dot')->props(['status' => 'success', 'position' => 'dot', 'pulse' => true]),
            Example::of('feedback.alert', 'not closable, success')->props(['message' => "Done{$user}", 'type' => 'success', 'closable' => false])->texts('message'),
            Example::of('feedback.progress', 'indeterminate')->props(['indeterminate' => true, 'label' => "Wait{$user}", 'showValue' => true])->texts('label'),
            Example::of('feedback.skeleton', 'lines')->props(['type' => 'text', 'lines' => 2]),
            Example::of('feedback.toast', 'auto close')->props(['message' => "Bye{$user}", 'autoClose' => true, 'position' => 'start', 'vertical' => 'bottom'])->texts('message'),
            Example::of('feedback.tooltip', 'text trigger')->props(['text' => "Tip{$user}", 'display' => 'text', 'position' => 'bottom'])->texts('text')->slot("Word{$user}"),
            Example::of('form.checkbox', 'checked and disabled')->props(['name' => 'a', 'value' => true, 'disabled' => true, 'required' => true, 'label' => "A{$user}"])->texts('label'),
            Example::of('form.input', 'password with an icon on the right')->props(['name' => 'p', 'type' => 'password', 'icon' => 'heroicon-o-lock-closed', 'iconSide' => 'right', 'required' => true, 'label' => "Pass{$user}", 'value' => 'x'])->texts('label'),
            Example::of('form.input', 'readonly small')->props(['name' => 'r', 'readonly' => true, 'size' => 'xs', 'value' => "v{$user}"]),
            Example::of('form.radio', 'inline and disabled')->props(['name' => 'r', 'inline' => true, 'disabled' => true, 'options' => ['a' => "A{$user}"], 'hint' => "Hint{$user}"])->texts('options.*', 'hint')->exceptInBag('id'),
            Example::of('form.select', 'multiple')->props(['name' => 'm', 'multiple' => true, 'options' => ['a' => "A{$user}", 'b' => "B{$user}"], 'value' => ['a'], 'required' => true, 'hint' => "Many{$user}"])->texts('options.*', 'hint'),
            Example::of('form.select', 'grouped searchable')->props(['name' => 'g', 'searchable' => true, 'options' => ["Group{$user}" => ['a' => "A{$user}"], "Other{$user}" => ['b' => "B{$user}"]], 'value' => 'a'])->hostileProps(['name' => 'g', 'searchable' => true, 'options' => [Example::HOSTILE => ['a' => Example::HOSTILE]], 'value' => 'a']),
            Example::of('form.select', 'remote and depends on another field')->props(['name' => 'region', 'loadUrl' => '/options', 'dependsOn' => 'country', 'disabled' => true, 'multiple' => true]),
            Example::of('form.textarea', 'counter and error')->props(['name' => 't', 'maxLength' => 10, 'value' => "abc{$user}", 'required' => true, 'readonly' => true]),
            Example::of('form.toggle', 'on')->props(['name' => 't', 'value' => true, 'color' => 'success', 'size' => 'lg']),
            Example::of('form.upload', 'single file with no label')->props(['name' => 'f', 'uploadUrl' => '/up', 'accept' => '.pdf', 'maxSize' => 1024, 'capture' => 'environment', 'hint' => "Max{$user}"])->texts('hint')->modules('upload'),
            Example::of('layout.divider', 'vertical with an icon')->props(['vertical' => true, 'icon' => 'heroicon-o-star', 'label' => "x{$user}"])->texts('label'),
            Example::of('layout.hero', 'with an image and an overlay')->props(['bgImage' => '/pixel.png', 'overlay' => true, 'minHeight' => 'min-h-0'])->slot("<p>Hi{$user}</p>"),
            Example::of('navigation.menu', 'horizontal compact')->props(['items' => $menu, 'direction' => 'horizontal', 'compact' => true, 'showIcons' => false, 'size' => 'sm'])->texts('items.*.label'),
            Example::of('navigation.pagination', 'one page')->props(['meta' => ['current_page' => 1, 'last_page' => 1, 'from' => 1, 'to' => 3, 'total' => 3, 'per_page' => 15], 'baseUrl' => '/list']),
            Example::of('navigation.steps', 'vertical and clickable')->props(['steps' => [['label' => "One{$user}"], ['label' => "Two{$user}"]], 'currentStep' => 2, 'vertical' => true, 'clickable' => true])->texts('steps.*.label'),
            Example::of('navigation.tabs', 'boxed')->props(['style' => 'boxed', 'active' => 1, 'items' => [['label' => "A{$user}", 'content' => "<p>a{$user}</p>", 'icon' => 'heroicon-o-home'], ['label' => "B{$user}", 'content' => "<p>b{$user}</p>"]]])->texts('items.*.label'),
            Example::of('sections.auto-form', 'form mode with values')->props(['mode' => 'form', 'fields' => ['name' => ['type' => 'text', 'label' => "Name{$user}"]], 'values' => ['name' => "Ana{$user}"], 'submitUrl' => '/save', 'submitLabel' => "Send{$user}", 'resetLabel' => "Undo{$user}", 'successMsg' => "Saved{$user}"])->texts('submitLabel', 'resetLabel', 'successMsg', 'fields.*.label'),
        ];
    }
}
