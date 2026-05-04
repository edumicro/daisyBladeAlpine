<?php

return [

    'component_map' => [
        'text'     => 'db::form.input',
        'email'    => 'db::form.input',
        'password' => 'db::form.input',
        'number'   => 'db::form.input',
        'money'    => 'db::form.input',
        'date'     => 'db::form.input',
        'datetime' => 'db::form.input',
        'select'   => 'db::form.select',
        'relation' => 'db::form.select',
        'textarea' => 'db::form.textarea',
        'toggle'   => 'db::form.toggle',
        'checkbox' => 'db::form.checkbox',
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
