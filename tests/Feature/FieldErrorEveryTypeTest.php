<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/*
 * ONE place paints the error of a field (resources/views/daisyblade/form/field-error.blade.php). If a type of field forgets to call
 * it, the user presses Save, the server says "422" and nothing appears. These tests give every type of field an error in its name
 * and look for the message in the painted HTML, once.
 */

/** A bag of errors, as the web middleware shares it after a failed validation. */
function errorBagWith(string $name, string $message): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag([$name => [$message]]));
}

/** Every type that `form.fields` (and so auto-form and wizard) knows how to paint. */
dataset('types of field', [
    'text', 'email', 'password', 'number', 'money', 'percentage', 'decimal', 'date', 'datetime', 'color', 'file',
    'textarea', 'select', 'multiselect', 'tags', 'relation', 'checkbox', 'boolean', 'toggle', 'radio',
]);

it('form.fields paints the error of a field of any type, from the bag of the server', function (string $type) {
    View::share('errors', errorBagWith('thing', "Wrong {$type}"));

    $html = Blade::render('<x-dbl::form.fields :fields="$fields" />', ['fields' => ['thing' => ['type' => $type, 'label' => 'Thing', 'options' => ['a' => 'A']]]]);

    expect(substr_count($html, "Wrong {$type}"))->toBe(1);
})->with('types of field');

/** The standalone controls: how each is written, so the test can give it a name. */
dataset('standalone controls', [
    'input' => '<x-dbl::form.input name="thing" />',
    'textarea' => '<x-dbl::form.textarea name="thing" />',
    'native select' => '<x-dbl::form.select name="thing" :options="[\'a\' => \'A\']" />',
    'searchable select' => '<x-dbl::form.select name="thing" searchable :options="[\'a\' => \'A\']" />',
    'remote select' => '<x-dbl::form.select name="thing" load-url="/o" />',
    'checkbox' => '<x-dbl::form.checkbox name="thing" label="L" />',
    'toggle' => '<x-dbl::form.toggle name="thing" label="L" />',
    'radio' => '<x-dbl::form.radio name="thing" :options="[\'a\' => \'A\']" />',
    'upload' => '<x-dbl::form.upload name="thing" upload-url="/u" />',
    'rich text' => '<x-dbl::form.rich-text name="thing" />',
    'signature' => '<x-dbl::form.signature name="thing" />',
    'key-value editor' => '<x-dbl::form.kv-editor name="thing" />',
    'list editor' => '<x-dbl::form.list-editor name="thing" />',
]);

it('a standalone form control paints the error of its name, once', function (string $blade) {
    View::share('errors', errorBagWith('thing', 'Wrong thing'));

    expect(substr_count(Blade::render($blade), 'Wrong thing'))->toBe(1);
})->with('standalone controls');

it('no view paints an error of a field by hand: they all call form.field-error', function () {
    $own = collect(viewsOfThePackage())
        ->reject(fn ($source, $path) => $path === 'daisyblade/form/field-error.blade.php')
        ->filter(fn ($source) => str_contains($source, '@error('))
        ->keys()->all();

    expect($own)->toBe([]);
});
