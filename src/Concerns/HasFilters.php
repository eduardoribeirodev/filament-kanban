<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Table;

trait HasFilters
{
    protected array|Closure $boardFilters = [];

    /** @var array<string, mixed> */
    protected array $filterConfiguration = [];

    public function filters(array|Closure $filters, FiltersLayout|Closure|null $layout = null): static
    {
        $this->boardFilters = $filters;

        if ($layout !== null) {
            $this->filtersLayout($layout);
        }

        return $this;
    }

    public function deferFilters(bool|Closure $condition = true): static
    {
        $this->filterConfiguration['deferFilters'] = $condition;

        return $this;
    }

    public function filtersFormColumns(int|array|Closure|null $columns): static
    {
        $this->filterConfiguration['filtersFormColumns'] = $columns;

        return $this;
    }

    public function filtersLayout(FiltersLayout|Closure|null $layout): static
    {
        $this->filterConfiguration['filtersLayout'] = $layout;

        return $this;
    }

    public function filtersFormWidth(Width|string|Closure|null $width): static
    {
        $this->filterConfiguration['filtersFormWidth'] = $width;

        return $this;
    }

    public function filtersFormMaxHeight(string|Closure|null $height): static
    {
        $this->filterConfiguration['filtersFormMaxHeight'] = $height;

        return $this;
    }

    public function filtersResetActionPosition(FiltersResetActionPosition|Closure|null $position): static
    {
        $this->filterConfiguration['filtersResetActionPosition'] = $position;

        return $this;
    }

    public function persistFiltersInSession(bool|Closure $condition = true): static
    {
        $this->filterConfiguration['persistFiltersInSession'] = $condition;

        return $this;
    }

    public function filtersFormSchema(?Closure $callback): static
    {
        $this->filterConfiguration['filtersFormSchema'] = $callback;

        return $this;
    }

    public function filtersTriggerAction(?Closure $callback): static
    {
        $this->filterConfiguration['filtersTriggerAction'] = $callback;

        return $this;
    }

    public function filtersApplyAction(?Closure $callback): static
    {
        $this->filterConfiguration['filtersApplyAction'] = $callback;

        return $this;
    }

    public function filtersRemoveAllAction(?Closure $callback): static
    {
        $this->filterConfiguration['filtersRemoveAllAction'] = $callback;

        return $this;
    }

    public function configureTableFilters(Table $table): Table
    {
        $table->filtersLayout(FiltersLayout::AboveContent)
            ->filters($this->evaluate($this->boardFilters));

        foreach ($this->filterConfiguration as $method => $value) {
            $table->{$method}($value);
        }

        return $table;
    }
}
