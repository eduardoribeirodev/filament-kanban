<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use Illuminate\Support\Collection;

trait HasRecords
{
    protected ?Closure $recordsCallback = null;

    /**
     * Alternative to query() for a collection of Eloquent records. The
     * callback may accept $filters to apply filtering to its own data source.
     */
    public function records(Closure $callback): static
    {
        $this->recordsCallback = $callback;

        return $this;
    }

    public function hasRecords(): bool
    {
        return $this->recordsCallback !== null;
    }

    /**
     * Registros das páginas atualmente visíveis em todas as colunas.
     */
    public function getRecords(): Collection
    {
        return $this->getLivewire()->getBoardRecords();
    }

    public function getUnfilteredRecords(): Collection
    {
        return collect($this->evaluate($this->recordsCallback, [
            'filters' => $this->getLivewire()->tableFilters ?? [],
        ]));
    }
}
