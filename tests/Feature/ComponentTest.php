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

it('uses the DaisyUI modal structure so the box is visible when open', function () {
    // DaisyUI 5 keeps .modal-box at opacity 0 unless its container is a .modal.modal-open
    $this->blade('<x-dbl::actions.modal id="test-modal" title="My Modal">Content</x-dbl::actions.modal>')
         ->assertSee('class="modal"', false)
         ->assertSee(':class="open && \'modal-open\'"', false)
         ->assertSee('modal-box', false)
         ->assertSee('modal-backdrop', false);
});

it('renders the card component', function () {
    $this->blade('<x-dbl::display.card title="Card title">Body</x-dbl::display.card>')
         ->assertSee('Card title')
         ->assertSee('Body');
});
