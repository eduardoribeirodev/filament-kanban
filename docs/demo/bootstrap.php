<?php

use Illuminate\Foundation\Application;
use KanbanDemo\DemoPanelProvider;

require __DIR__.'/vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'KanbanDemo\\')) {
        require __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen('KanbanDemo\\'))).'.php';
    }
});

foreach (['bootstrap/cache', 'database', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
    if (! is_dir(__DIR__.'/'.$directory)) {
        mkdir(__DIR__.'/'.$directory, 0755, true);
    }
}

$app = Application::configure(basePath: __DIR__)
    ->withRouting(web: __DIR__.'/routes.php')
    ->withProviders([DemoPanelProvider::class])
    ->withMiddleware()
    ->withExceptions()
    ->create();

$app->useAppPath(__DIR__.'/src');

return $app;
