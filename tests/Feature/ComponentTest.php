<?php

it('renders the badge component', function () {
    $this->blade('<x-db::display.badge label="Hello" />')
         ->assertSee('Hello');
});

it('renders the button component', function () {
    $this->blade('<x-db::actions.button label="Click me" />')
         ->assertSee('Click me');
});

it('renders the alert component', function () {
    $this->blade('<x-db::feedback.alert message="Test message" />')
         ->assertSee('Test message');
});

it('renders the modal component', function () {
    $this->blade('<x-db::actions.modal id="test-modal" title="My Modal">Content</x-db::actions.modal>')
         ->assertSee('My Modal')
         ->assertSee('Content');
});

it('renders the card component', function () {
    $this->blade('<x-db::display.card title="Card title">Body</x-db::display.card>')
         ->assertSee('Card title')
         ->assertSee('Body');
});
