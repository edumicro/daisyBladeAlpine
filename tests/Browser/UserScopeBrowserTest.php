<?php

/*
 * The scope of the caller is theirs (cause 1): a component never puts an x-data between the caller and the element that
 * receives their attributes. The proof, for every component that takes `$attributes`: the caller writes `x-ref="mine"` on it
 * and, from THEIR scope, `$refs.mine` is that element. If a scope of the component sat around it, the ref would be registered
 * in the component's scope and the caller would get undefined.
 */

dataset('components that take the attributes of the caller', [
    'input' => '<x-dbl::form.input name="a" x-ref="mine" />',
    'textarea' => '<x-dbl::form.textarea name="a" max-length="10" x-ref="mine" />',
    'select (native)' => '<x-dbl::form.select name="a" :options="[1 => \'x\']" x-ref="mine" />',
    'select (remote)' => '<x-dbl::form.select name="a" load-url="/options" x-ref="mine" />',
    'select (searchable)' => '<x-dbl::form.select name="a" searchable :options="[1 => \'x\']" x-ref="mine" />',
    'checkbox' => '<x-dbl::form.checkbox name="a" x-ref="mine" />',
    'alert' => '<x-dbl::feedback.alert message="hi" x-ref="mine" />',
    'toast' => '<x-dbl::feedback.toast message="hi" x-ref="mine" />',
    'modal' => '<x-dbl::actions.modal id="m" x-ref="mine">x</x-dbl::actions.modal>',
    'tabs' => '<x-dbl::navigation.tabs :items="[[\'label\' => \'A\']]" x-ref="mine" />',
    'steps' => '<x-dbl::navigation.steps :steps="[[\'label\' => \'A\']]" x-ref="mine" />',
    'sidebar' => '<x-dbl::navigation.sidebar x-ref="mine">x</x-dbl::navigation.sidebar>',
    'navbar' => '<x-dbl::navigation.navbar x-ref="mine" />',
    'repeater' => '<x-dbl::form.repeater name="r" :fields="[[\'key\' => \'a\']]" x-ref="mine" />',
    'kv-editor' => '<x-dbl::form.kv-editor name="k" x-ref="mine" />',
    'data-table' => '<x-dbl::display.data-table load-url="/rows" :columns="[[\'key\' => \'id\', \'label\' => \'Id\']]" x-ref="mine" />',
    'resource-details' => '<x-dbl::display.resource-details load-url="/options" x-ref="mine" />',
    'carousel' => '<x-dbl::display.carousel :items="[[\'image\' => \'/a.png\']]" x-ref="mine" />',
]);

it('keeps x-ref for the caller on :dataset', function (string $component) {
    $page = visit(browserPage('scope-'.md5($component), <<<BLADE
        <div x-data="{ found: 'waiting' }" x-init="\$nextTick(() => found = \$refs.mine ? 'yes' : 'no')">
            {$component}
            <p id="found" x-text="found"></p>
        </div>
        BLADE));

    expect(readPage($page, "document.querySelector('#found').textContent", fn ($value) => $value !== 'waiting'))->toBe('yes');
})->with('components that take the attributes of the caller');
