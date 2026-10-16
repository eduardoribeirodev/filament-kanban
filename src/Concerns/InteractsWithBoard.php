<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use EduardoRibeiroDev\FilamentKanban\Board;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

trait InteractsWithBoard
{
    use InteractsWithBoardTable;

    protected ?Board $board = null;

    public function bootedInteractsWithBoard(): void
    {
        $this->getBoard();
    }

    protected function makeBoard(): Board
    {
        return Board::make($this);
    }

    public function getBoard(): Board
    {
        return $this->board ??= $this->board($this->makeBoard());
    }

    public function board(Board $board): Board
    {
        return $board;
    }

    public function sortBoardCard(int|string $recordKey, int $position, int|string $columnId): void
    {
        $this->moveCard($recordKey, $columnId, $position);
    }

    public function moveCard(int|string $recordKey, int|string $columnId, ?int $position = null): void
    {
        abort_if($position !== null && $position < 0, 422);

        $board = $this->getBoard();

        if (($column = $board->getColumn($columnId)) === null) {
            throw (new ModelNotFoundException)->setModel('Kanban column', [$columnId]);
        }

        $revealNextBatch = false;

        if ($position !== null && ($perPage = $column->getCardsPerPage()) !== null) {
            $page = $this->getBoardColumnPage($column);

            if ($column->hasInfiniteScroll()) {
                $position = min($position, $page * $perPage);
                $revealNextBatch = $position === $page * $perPage;
            } else {
                $position = (($page - 1) * $perPage) + min($position, $perPage);
            }
        }

        $movedToTop = DB::transaction(function () use ($board, $recordKey, $columnId, $column, $position): bool {
            $record = $board->getQuery()->whereKey($recordKey)->lockForUpdate()->firstOrFail();
            $positionColumn = $board->getPositionColumn();
            $moveToTop = ((string) $record->getRawOriginal($board->getStatusColumn()) !== (string) $columnId)
                && $board->shouldMoveCardsToTop($record, $column);

            if ($moveToTop && $positionColumn === null) {
                throw new LogicException('moveCardsToTop() requires a positionColumn() to persist the card order.');
            }

            if ($positionColumn === null) {
                $board->updateRecordStatus($record, $columnId, $position);

                return false;
            }

            $cards = $board->getQuery()
                ->where($board->getStatusColumn(), $columnId)
                ->whereKeyNot($recordKey)
                ->reorder()
                ->orderBy($positionColumn)
                ->orderBy($record->getQualifiedKeyName())
                ->lockForUpdate()
                ->pluck($record->getQualifiedKeyName())
                ->all();

            if ($moveToTop) {
                // Prepend to the entire destination, including hidden and unloaded cards.
                $insertAt = 0;
            } else {
                // Resolve drops against the visible cards without losing filtered-out cards.
                $visibleCards = $this->getFilteredTableQuery()
                    ->where($board->getStatusColumn(), $columnId)
                    ->whereKeyNot($recordKey)
                    ->reorder()
                    ->orderBy($positionColumn)
                    ->orderBy($record->getQualifiedKeyName());

                $nextCard = $position !== null
                    ? (clone $visibleCards)->skip($position)->value($record->getQualifiedKeyName())
                    : null;
                $previousCard = $position > 0
                    ? (clone $visibleCards)->skip($position - 1)->value($record->getQualifiedKeyName())
                    : null;
                $insertAt = match (true) {
                    $nextCard !== null => array_search($nextCard, $cards, true),
                    $previousCard !== null => array_search($previousCard, $cards, true) + 1,
                    default => count($cards),
                };
            }

            array_splice($cards, $insertAt, 0, [$record->getKey()]);

            $positions = [];

            foreach ($cards as $index => $cardKey) {
                if ($cardKey != $record->getKey()) {
                    $positions[$cardKey] = $index + 1;
                }
            }

            // Keep the existing integer ranks and custom persistence callback,
            // but update siblings in batches instead of one query per card.
            $connection = $record->getConnection();
            $key = $connection->getQueryGrammar()->wrap($record->getQualifiedKeyName());
            $positionIdentifier = $connection->getQueryGrammar()->wrap($positionColumn);

            foreach (array_chunk($positions, 500, preserve_keys: true) as $chunk) {
                $cases = '';

                foreach ($chunk as $cardKey => $rank) {
                    $cases .= ' when '.$connection->escape((string) $cardKey).' then '.$rank;
                }

                $board->getQuery()->whereKey(array_keys($chunk))->update([
                    $positionColumn => $connection->raw('case '.$key.$cases.' else '.$positionIdentifier.' end'),
                ]);
            }

            $board->updateRecordStatus($record, $columnId, array_search($record->getKey(), $cards, false) + 1);

            return $moveToTop;
        }, attempts: 3);

        if ($movedToTop && ! $column->hasInfiniteScroll()) {
            $this->boardColumnPages[(string) $columnId] = 1;
        } elseif ($revealNextBatch && ! $movedToTop) {
            // Keep a card dropped after the last loaded card visible.
            $this->boardColumnPages[(string) $columnId] = $page + 1;
        }

        $this->flushCachedTableRecords();
        $this->dispatch('kanban-card-moved', card: $recordKey, column: $columnId);
    }
}
