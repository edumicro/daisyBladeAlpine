<?php

return [

    'component_map' => [
        'text'     => 'dbl::form.input',
        'email'    => 'dbl::form.input',
        'password' => 'dbl::form.input',
        'number'   => 'dbl::form.input',
        'money'    => 'dbl::form.input',
        'date'     => 'dbl::form.input',
        'datetime' => 'dbl::form.input',
        'select'   => 'dbl::form.select',
        'relation' => 'dbl::form.select',
        'textarea' => 'dbl::form.textarea',
        'toggle'   => 'dbl::form.toggle',
        'checkbox' => 'dbl::form.checkbox',
    ],

    'defaults' => [
        'input_class'    => 'input input-bordered',
        'select_class'   => 'select select-bordered',
        'button_variant' => 'btn-primary',
        'relation_label' => 'name',
    ],

    'toast' => [
        'duration' => 4000,
        'position' => 'end',   // start | center | end
        'vertical' => 'top',   // top | middle | bottom
    ],

    'sidebar' => [
        'persistent' => true,  // save open/closed state in localStorage
    ],

];
