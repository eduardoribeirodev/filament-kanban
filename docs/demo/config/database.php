<?php

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => dirname(__DIR__).'/database/demo.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],
];
