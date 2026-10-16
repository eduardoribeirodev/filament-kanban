<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use Illuminate\Database\Eloquent\Model;

trait HasStatusColumn
{
    protected string | Closure $statusColumn = 'status';

    protected string | Closure | null $positionColumn = null;

    protected bool | Closure $shouldMoveCardsToTop = false;

    protected ?Closure $updateStatusUsing = null;

    /**
     * Nome do atributo do model que determina em qual BoardColumn
     * o card aparece (ex: 'status', 'stage_id'...).
     */
    public function statusColumn(string | Closure $column): static
    {
        $this->statusColumn = $column;

        return $this;
    }

    public function getStatusColumn(): string
    {
        return $this->evaluate($this->statusColumn);
    }

    /**
     * Nome do atributo usado para ordenar os cards dentro de uma coluna.
     * Se omitido, os cards mantêm a ordem retornada pela query.
     */
    public function positionColumn(string | Closure | null $column): static
    {
        $this->positionColumn = $column;

        return $this;
    }

    public function getPositionColumn(): ?string
    {
        return $this->evaluate($this->positionColumn);
    }

    public function moveCardsToTop(bool | Closure $condition = true): static
    {
        $this->shouldMoveCardsToTop = $condition;

        return $this;
    }

    public function shouldMoveCardsToTop(Model $record, BoardColumn $column): bool
    {
        return (bool) $this->evaluate($this->shouldMoveCardsToTop, [
            'record' => $record,
            'card' => $record,
            'column' => $column,
        ], [
            Model::class => $record,
            $record::class => $record,
            BoardColumn::class => $column,
        ]);
    }

    public function getRecordStatus(Model $record): mixed
    {
        return data_get($record, $this->getStatusColumn());
    }

    /**
     * Permite customizar como a mudança de coluna/posição de um card
     * é persistida (por padrão, salva direto no model).
     */
    public function updateStatusUsing(Closure $callback): static
    {
        $this->updateStatusUsing = $callback;

        return $this;
    }

    /**
     * Chamado pela trait InteractsWithBoard quando um card é arrastado
     * para outra coluna (ou reordenado) via drag and drop.
     */
    public function updateRecordStatus(Model $record, int | string $columnId, ?int $position = null): void
    {
        $callback = $this->updateStatusUsing ?? function (Model $record, int | string $columnId, ?int $position): void {
            $record->setAttribute($this->getStatusColumn(), $columnId);

            if ($position !== null && $this->getPositionColumn()) {
                $record->setAttribute($this->getPositionColumn(), $position);
            }

            $record->save();
        };

        $this->evaluate($callback, [
            'record' => $record,
            'columnId' => $columnId,
            'position' => $position,
        ]);
    }
}
