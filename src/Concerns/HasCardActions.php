<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Model;

trait HasCardActions
{
    /** @var array<Action|ActionGroup>|Closure */
    protected array|Closure $cardActions = [];

    protected bool|Closure $hasCompactCardActions = false;

    /** @var array<Action|ActionGroup>|Closure */
    protected array|Closure $columnActions = [];

    protected string|Closure|null $cardAction = null;

    protected string|Closure|null $recordTitleAttribute = null;

    /** @param array<Action|ActionGroup>|Closure $actions */
    public function cardActions(array|Closure $actions): static
    {
        $this->cardActions = $actions;

        return $this;
    }

    /** @return array<Action|ActionGroup> */
    public function getCardActions(): array
    {
        return $this->evaluate($this->cardActions);
    }

    public function compactCardActions(bool|Closure $condition = true): static
    {
        $this->hasCompactCardActions = $condition;

        return $this;
    }

    public function hasCompactCardActions(Model $record, BoardColumn $column): bool
    {
        return (bool) $this->evaluate($this->hasCompactCardActions, [
            'card' => $record,
            'record' => $record,
            'column' => $column,
        ], [
            Model::class => $record,
            $record::class => $record,
            BoardColumn::class => $column,
        ]);
    }

    public function getCardAction(string $name): ?Action
    {
        foreach ($this->getCardActions() as $action) {
            if ($action instanceof Action && $action->getName() === $name) {
                return $action;
            }

            if ($action instanceof ActionGroup && isset($action->getFlatActions()[$name])) {
                return $action->getFlatActions()[$name];
            }
        }

        return null;
    }

    public function cardAction(string|Closure|null $action): static
    {
        $this->cardAction = $action;

        return $this;
    }

    public function getDefaultCardAction(): string|Closure|null
    {
        return $this->cardAction;
    }

    /** @return array<Action|ActionGroup> */
    public function getCardActionsForRecord(Model $record): array
    {
        return array_map(
            fn (Action|ActionGroup $action): Action|ActionGroup => $action->getClone()->record($record),
            $this->getLivewire()->getTable()->getRecordActions(),
        );
    }

    public function getCardActionForRecord(Model $record): ?Action
    {
        $table = $this->getLivewire()->getTable();
        $name = $table->getRecordAction($record);

        $find = function (array $actions) use ($name, &$find): ?Action {
            foreach ($actions as $action) {
                if ($action instanceof ActionGroup) {
                    if ($action->isVisible() && ($nested = $find($action->getActions()))) {
                        return $nested;
                    }
                } elseif ($action->getName() === $name) {
                    return $action;
                }
            }

            return null;
        };

        return $name ? $find($this->getCardActionsForRecord($record)) : null;
    }

    /** @param array<Action|ActionGroup>|Closure $actions */
    public function columnActions(array|Closure $actions): static
    {
        $this->columnActions = $actions;

        return $this;
    }

    /** @return array<Action|ActionGroup> */
    public function getColumnActions(): array
    {
        return $this->evaluate($this->columnActions);
    }

    /** @return array<Action|ActionGroup> */
    public function getColumnActionsForColumn(int|string $columnId): array
    {
        return $this->prepareActionsForColumn($this->getLivewire()->getTable()->getHeaderActions(), $columnId);
    }

    /**
     * @param  array<Action|ActionGroup>  $actions
     * @return array<Action|ActionGroup>
     */
    protected function prepareActionsForColumn(array $actions, int|string $columnId): array
    {
        $prepare = function (Action|ActionGroup $action) use ($columnId, &$prepare): Action|ActionGroup {
            $clone = $action->getClone();

            if ($clone instanceof ActionGroup) {
                $clone->actions(array_map($prepare, $action->getActions()));
            } else {
                $clone = $clone([
                    ...($clone->getInvokedArguments() ?? []),
                    ...$clone->getArguments(),
                    'column' => $columnId,
                ]);
            }

            return $clone;
        };

        return array_map($prepare, $actions);
    }

    public function recordTitleAttribute(string|Closure|null $attribute): static
    {
        $this->recordTitleAttribute = $attribute;

        return $this;
    }

    public function getRecordTitleAttribute(): ?string
    {
        return $this->evaluate($this->recordTitleAttribute);
    }

    public function getRecordTitle(Model $record): ?string
    {
        return $this->getRecordTitleAttribute() !== null
            ? $this->getLivewire()->getTable()->getRecordTitle($record)
            : null;
    }
}
