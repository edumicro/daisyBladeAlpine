<?php

it('renders the badge component', function () {
    $this->blade('<x-dbl::display.badge label="Hello" />')
         ->assertSee('Hello');
});

it('renders the button component', function () {
    $this->blade('<x-dbl::actions.button label="Click me" />')
         ->assertSee('Click me');
});

it('renders the alert component', function () {
    $this->blade('<x-dbl::feedback.alert message="Test message" />')
         ->assertSee('Test message');
});

it('renders the modal component', function () {
    $this->blade('<x-dbl::actions.modal id="test-modal" title="My Modal">Content</x-dbl::actions.modal>')
         ->assertSee('My Modal')
         ->assertSee('Content');
});

it('renders the card component', function () {
    $this->blade('<x-dbl::display.card title="Card title">Body</x-dbl::display.card>')
         ->assertSee('Card title')
         ->assertSee('Body');
});
