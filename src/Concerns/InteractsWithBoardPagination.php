<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

trait InteractsWithBoardPagination
{
    /** @var array<string, int> */
    #[Locked]
    public array $boardColumnPages = [];

    /** @var array<string, Collection> */
    protected array $cachedBoardColumns = [];

    /** @var array<string, int> */
    protected array $cachedBoardColumnCounts = [];

    /** @var array<string, array<string, float>> */
    protected array $cachedBoardColumnSums = [];

    public function getBoardColumnQuery(BoardColumn $column): Builder
    {
        $board = $this->getBoard();
        $query = clone $this->getFilteredTableQuery();
        $model = $query->getModel();
        $query->where($model->qualifyColumn($board->getStatusColumn()), $column->getId());

        if ($position = $board->getPositionColumn()) {
            $query->reorder()->orderBy($model->qualifyColumn($position));
        }

        return $query->orderBy($model->getQualifiedKeyName());
    }

    public function getBoardColumnRecords(BoardColumn $column): Collection
    {
        $id = (string) $column->getId();

        if (isset($this->cachedBoardColumns[$id])) {
            return $this->cachedBoardColumns[$id];
        }

        $perPage = $column->getCardsPerPage();

        if ($this->getTable()->hasQuery()) {
            $query = $this->getBoardColumnQuery($column);

            if ($perPage !== null) {
                if ($column->hasInfiniteScroll()) {
                    $query->limit($this->getBoardColumnPage($column) * $perPage);
                } else {
                    $query->forPage($this->getBoardColumnPage($column), $perPage);
                }
            }

            $cards = $query->get();
        } else {
            $cards = collect($this->getTableRecords())
                ->filter(fn ($record): bool => $this->getBoard()->getRecordStatus($record) == $column->getId())
                ->values();

            if ($position = $this->getBoard()->getPositionColumn()) {
                $cards = $cards->sortBy([[$position, 'asc'], [$cards->first()?->getKeyName() ?? 'id', 'asc']])->values();
            }

            $this->cachedBoardColumnCounts[$id] = $cards->count();

            if ($perPage !== null) {
                $cards = $column->hasInfiniteScroll()
                    ? $cards->take($this->getBoardColumnPage($column) * $perPage)->values()
                    : $cards->forPage($this->getBoardColumnPage($column), $perPage)->values();
            }
        }

        return $this->cachedBoardColumns[$id] = $column->applyCardsModifier($cards);
    }

    public function getBoardColumnCount(BoardColumn $column): int
    {
        $id = (string) $column->getId();

        if ($column->hasCardsModifier()) {
            return $this->getBoardColumnRecords($column)->count();
        }

        if (! isset($this->cachedBoardColumnCounts[$id])) {
            if ($this->getTable()->hasQuery()) {
                $this->cachedBoardColumnCounts[$id] = $this->getBoardColumnQuery($column)->toBase()->getCountForPagination();
            } else {
                $this->getBoardColumnRecords($column);
            }
        }

        return $this->cachedBoardColumnCounts[$id];
    }

    public function getBoardColumnSum(BoardColumn $column, string $attribute): float
    {
        $id = (string) $column->getId();

        return $this->cachedBoardColumnSums[$id][$attribute] ??= (float) (
            $this->getTable()->hasQuery() && ! $column->hasCardsModifier()
                ? $this->getBoardColumnQuery($column)->sum($attribute)
                : ($column->hasCardsModifier()
                    ? $this->getBoardColumnRecords($column)->sum($attribute)
                    : collect($this->getTableRecords())
                        ->filter(fn ($record): bool => $this->getBoard()->getRecordStatus($record) == $column->getId())
                        ->sum($attribute))
        );
    }

    public function getBoardColumnPage(BoardColumn $column): int
    {
        $perPage = $column->getCardsPerPage();

        if ($perPage === null) {
            return $this->boardColumnPages[(string) $column->getId()] = 1;
        }

        $lastPage = max(1, (int) ceil($this->getBoardColumnCount($column) / $perPage));

        return $this->boardColumnPages[(string) $column->getId()] = max(1, min(
            $this->boardColumnPages[(string) $column->getId()] ?? 1,
            $lastPage,
        ));
    }

    public function goToBoardColumnPage(int|string $columnId, int $page): void
    {
        $column = $this->getBoard()->getColumn($columnId)
            ?? throw (new ModelNotFoundException)->setModel('Kanban column', [$columnId]);

        if ($column->hasInfiniteScroll()) {
            return;
        }

        $this->boardColumnPages[(string) $columnId] = max(1, $page);
        $this->getBoardColumnPage($column);
        unset($this->cachedBoardColumns[(string) $columnId]);
    }

    public function loadMoreBoardColumnCards(int|string $columnId, int $page): void
    {
        $column = $this->getBoard()->getColumn($columnId)
            ?? throw (new ModelNotFoundException)->setModel('Kanban column', [$columnId]);

        if (! $column->hasInfiniteScroll() || $page !== $this->getBoardColumnPage($column)) {
            return;
        }

        if ($page * $column->getCardsPerPage() >= $column->getCardsCount()) {
            return;
        }

        $this->boardColumnPages[(string) $columnId] = $page + 1;
        unset($this->cachedBoardColumns[(string) $columnId]);
    }
}
