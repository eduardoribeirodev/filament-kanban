<?php

namespace EduardoRibeiroDev\FilamentKanban;

use EduardoRibeiroDev\FilamentKanban\Contracts\HasBoard;
use Filament\Support\Components\ViewComponent;

class Board extends ViewComponent
{
    use Concerns\BelongsToLivewire;
    use Concerns\HasActionForm;
    use Concerns\HasCardActions;
    use Concerns\HasCardSchema;
    use Concerns\HasCardView;
    use Concerns\HasColumnPagination;
    use Concerns\HasColumns;
    use Concerns\HasDragHandle;
    use Concerns\HasEmptyState;
    use Concerns\HasFilters;
    use Concerns\HasQuery;
    use Concerns\HasRecords;
    use Concerns\HasStatusColumn;

    protected string $view = 'filament-kanban::index';

    protected string $viewIdentifier = 'board';

    protected string $evaluationIdentifier = 'board';

    final public function __construct(HasBoard $livewire)
    {
        $this->livewire($livewire);
    }

    public static function make(HasBoard $livewire): static
    {
        $static = app(static::class, ['livewire' => $livewire]);
        $static->configure();

        return $static;
    }

    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'livewire' => [$this->getLivewire()],
            'board' => [$this],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
