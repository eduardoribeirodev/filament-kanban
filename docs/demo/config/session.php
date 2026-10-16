<?php

return [
    'driver' => 'file',
    'lifetime' => 120,
    'files' => dirname(__DIR__).'/storage/framework/sessions',
    'cookie' => 'filament_kanban_demo',
    'path' => '/',
    'http_only' => true,
    'same_site' => 'lax',
    'lottery' => [2, 100],
];
