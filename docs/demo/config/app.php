<?php

return [
    'name' => 'Filament Kanban',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://127.0.0.1:8879',
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    // Public, disposable demo key. Never reuse this in another application.
    'key' => 'base64:'.base64_encode(str_repeat('demo', 8)),
    'cipher' => 'AES-256-CBC',
];
