<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;

trait HasColumns
{
    /**
     * @var array<BoardColumn> | Closure
     */
    protected array|Closure $columns = [];

    /**
     * @param  array<BoardColumn> | Closure  $columns
     */
    public function columns(array|Closure $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return array<BoardColumn>
     */
    public function getColumns(): array
    {
        $columns = $this->evaluate($this->columns);

        foreach ($columns as $column) {
            $column->board($this);
        }

        return $columns;
    }

    public function getColumn(int|string $id): ?BoardColumn
    {
        foreach ($this->getColumns() as $column) {
            if ((string) $column->getId() === (string) $id) {
                return $column;
            }
        }

        return null;
    }
}
