<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait InteractsWithBoardTable
{
    use InteractsWithBoardPagination;
    use InteractsWithTable {
        flushCachedTableRecords as protected flushFilamentTableRecords;
        handleTableFilterUpdates as protected handleFilamentFilterUpdates;
    }

    public function table(Table $table): Table
    {
        $board = $this->getBoard();

        $table->queryStringIdentifier('board')
            ->paginated(false)
            ->recordTitleAttribute($board->getRecordTitleAttribute())
            ->recordAction($board->getDefaultCardAction())
            ->recordActions($board->getCardActions())
            ->headerActions($board->getColumnActions())
            ->emptyStateActions($board->getEmptyStateActions());

        if ($board->hasQuery()) {
            $table->query(fn () => $board->getQuery());
        }

        if ($board->hasRecords()) {
            $table->records(fn (): Collection => $board->getUnfilteredRecords()
                ->keyBy(fn (Model $record): string => (string) $record->getKey()));
        }

        return $board->configureTableFilters($table);
    }

    public function getBoardRecords(): Collection
    {
        return collect($this->getBoard()->getColumns())->flatMap(fn ($column) => $column->getCards())->values();
    }

    public function flushCachedTableRecords(): void
    {
        $this->flushFilamentTableRecords();
        $this->cachedBoardColumns = [];
        $this->cachedBoardColumnCounts = [];
        $this->cachedBoardColumnSums = [];
    }

    protected function handleTableFilterUpdates(): void
    {
        $this->handleFilamentFilterUpdates();
        $this->boardColumnPages = [];
        $this->flushCachedTableRecords();
    }
}
