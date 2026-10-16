<?php

namespace EduardoRibeiroDev\FilamentKanban\Pages;

use Closure;
use EduardoRibeiroDev\FilamentKanban\Concerns\InteractsWithBoard;
use EduardoRibeiroDev\FilamentKanban\Contracts\HasBoard;
use Filament\Actions\Action;
use Filament\Pages\Page;

class BoardPage extends Page implements HasBoard
{
    use InteractsWithBoard;

    protected string $view = 'filament-kanban::pages.board-page';

    protected function afterActionCalled(Action $action): void
    {
        parent::afterActionCalled($action);
        $this->flushCachedTableRecords();
    }

    public function getDefaultActionSchemaResolver(Action $action): ?Closure
    {
        if ($action->getTable() === $this->getTable()) {
            if ($resolver = $this->getBoard()->getActionFormResolver($action)) {
                return $resolver;
            }
        }

        return parent::getDefaultActionSchemaResolver($action);
    }
}
