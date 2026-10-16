<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;

trait HasQuery
{
    protected Builder|Closure|null $queryCallback = null;

    /**
     * Define a query Eloquent base do board. O status de cada card
     * (a coluna em que ele aparece) é resolvido via statusColumn().
     */
    public function query(Builder|Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function hasQuery(): bool
    {
        return $this->queryCallback !== null;
    }

    public function getQuery(): Builder
    {
        if (! $this->hasQuery()) {
            throw new Exception(
                'Defina uma query para o board através do método query(), ex: ->query(fn () => Task::query())'
            );
        }

        return clone $this->evaluate($this->queryCallback);
    }
}
