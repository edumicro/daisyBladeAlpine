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

    'map' => [
        'driver' => 'maplibre',          // maplibre | google
        'base'   => 'osm',               // osm | ign-base | ign-pnoa | URL XYZ ({z}/{x}/{y})
        'center' => [39.4699, -0.3763],  // [lat, lng]
        'zoom'   => 6,
        'height' => '24rem',
        'google_key' => env('GOOGLE_MAPS_KEY'),  // clave de navegador (restringida por referer)
    ],

    'geo' => [
        'driver'  => 'cartociudad',      // cartociudad | nominatim | google
        'timeout' => 5,
        'cartociudad' => [
            'url' => 'https://www.cartociudad.es/geocoder/api/geocoder',
        ],
        'nominatim' => [
            'url'          => 'https://nominatim.openstreetmap.org',
            'user_agent'   => env('NOMINATIM_USER_AGENT', 'DaisyBlade/2 (contacto@example.com)'),
            'min_interval' => 1.0,       // segundos entre peticiones (política: máx. 1/s); 0 = sin espera
        ],
        'google' => [
            'key' => env('GOOGLE_GEOCODING_KEY'),  // clave de servidor
        ],
    ],

];
