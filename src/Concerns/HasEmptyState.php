<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use BackedEnum;
use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

trait HasEmptyState
{
    protected View|Htmlable|Closure|null $emptyState = null;

    protected string|Htmlable|Closure|null $emptyStateHeading = null;

    protected string|Htmlable|Closure|null $emptyStateDescription = null;

    protected string|BackedEnum|Htmlable|Closure|null $emptyStateIcon = Heroicon::XMark;

    /** @var array<Action|ActionGroup>|ActionGroup|Closure */
    protected array|ActionGroup|Closure $emptyStateActions = [];

    public function emptyState(View|Htmlable|Closure|null $emptyState): static
    {
        $this->emptyState = $emptyState;

        return $this;
    }

    public function emptyStateHeading(string|Htmlable|Closure|null $heading): static
    {
        $this->emptyStateHeading = $heading;

        return $this;
    }

    public function emptyStateDescription(string|Htmlable|Closure|null $description): static
    {
        $this->emptyStateDescription = $description;

        return $this;
    }

    public function emptyStateIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->emptyStateIcon = $icon;

        return $this;
    }

    /** @param array<Action|ActionGroup>|ActionGroup|Closure $actions */
    public function emptyStateActions(array|ActionGroup|Closure $actions): static
    {
        $this->emptyStateActions = $actions;

        return $this;
    }

    public function getEmptyState(BoardColumn $column): View|Htmlable|null
    {
        return $this->evaluateEmptyState($this->emptyState, $column);
    }

    public function getEmptyStateHeading(BoardColumn $column): string|Htmlable
    {
        return $this->evaluateEmptyState($this->emptyStateHeading, $column)
            ?? $this->getLivewire()->getTable()->getEmptyStateHeading();
    }

    public function getEmptyStateDescription(BoardColumn $column): string|Htmlable|null
    {
        return $this->evaluateEmptyState($this->emptyStateDescription, $column);
    }

    public function getEmptyStateIcon(BoardColumn $column): string|BackedEnum|Htmlable|null
    {
        return $this->evaluateEmptyState($this->emptyStateIcon, $column);
    }

    /** @return array<Action|ActionGroup> */
    public function getEmptyStateActions(): array
    {
        return Arr::wrap($this->evaluate($this->emptyStateActions));
    }

    /** @return array<Action|ActionGroup> */
    public function getEmptyStateActionsForColumn(int|string $columnId): array
    {
        return $this->prepareActionsForColumn(
            $this->getLivewire()->getTable()->getEmptyStateActions(),
            $columnId,
        );
    }

    protected function evaluateEmptyState(mixed $value, BoardColumn $column): mixed
    {
        return $this->evaluate($value, ['column' => $column], [BoardColumn::class => $column]);
    }
}
