<?php

namespace EduardoRibeiroDev\FilamentKanban;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class FilamentKanbanServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-kanban');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-kanban');

        FilamentAsset::register([
            Css::make('filament-kanban', __DIR__.'/../dist/filament-kanban.css'),
        ], package: 'eduardoribeirodev/filament-kanban');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-kanban'),
        ], 'filament-kanban-views');

        $this->publishes([
            __DIR__.'/../resources/views/card.blade.php' => resource_path('views/vendor/filament-kanban/card.blade.php'),
        ], 'filament-kanban-card-view');

        $this->publishes([
            __DIR__.'/../resources/views/empty-state.blade.php' => resource_path('views/vendor/filament-kanban/empty-state.blade.php'),
        ], 'filament-kanban-empty-state-view');
    }
}
