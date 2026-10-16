<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use InvalidArgumentException;

trait HasColumnPagination
{
    protected int|Closure|null $cardsPerPage = 50;

    protected bool|Closure $hasInfiniteScroll = false;

    public function infiniteScroll(bool|Closure $condition = true): static
    {
        $this->hasInfiniteScroll = $condition;

        return $this;
    }

    public function hasInfiniteScroll(BoardColumn $column): bool
    {
        return (bool) $this->evaluate($this->hasInfiniteScroll, ['column' => $column], [BoardColumn::class => $column]);
    }

    public function cardsPerPage(int|Closure|null $perPage): static
    {
        $this->cardsPerPage = $perPage;

        return $this;
    }

    public function getCardsPerPage(BoardColumn $column): ?int
    {
        $perPage = $this->evaluate($this->cardsPerPage, ['column' => $column], [BoardColumn::class => $column]);

        if ($perPage !== null && ($perPage < 1 || $perPage > 500)) {
            throw new InvalidArgumentException('cardsPerPage() must be between 1 and 500, or null to disable pagination.');
        }

        return $perPage;
    }
}
