<?php

namespace EduardoRibeiroDev\FilamentKanban;

use Closure;
use EduardoRibeiroDev\FilamentKanban\Concerns\BelongsToBoard;
use Filament\Support\Components\ViewComponent;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BoardColumn extends ViewComponent
{
    use BelongsToBoard;

    protected string $view = 'filament-kanban::column';

    protected string $viewIdentifier = 'column';

    protected string $evaluationIdentifier = 'column';

    protected int|string $id;

    protected string|Closure|null $label = null;

    protected string|array|Closure $color = 'gray';

    protected string|Closure|null $icon = null;

    protected int|Closure|null $limit = null;

    protected bool|Closure $collapsible = false;

    protected ?Closure $modifyCardsUsing = null;

    final public function __construct(int|string $id)
    {
        $this->id($id);
    }

    public static function make(int|string $id): static
    {
        $static = app(static::class, ['id' => $id]);
        $static->configure();

        return $static;
    }

    public function id(int|string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->evaluate($this->label) ?? (string) Str::of((string) $this->getId())->headline();
    }

    public function color(string|array|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string|array
    {
        return $this->evaluate($this->color);
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    /**
     * Limite opcional de WIP (work in progress) para esta coluna.
     */
    public function limit(int|Closure|null $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->evaluate($this->limit);
    }

    public function collapsible(bool|Closure $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function isCollapsible(): bool
    {
        return (bool) $this->evaluate($this->collapsible);
    }

    /**
     * Permite ajustar manualmente a coleção de cards já resolvida para
     * esta coluna (filtro extra, sort custom, limit, etc.).
     */
    public function modifyCardsUsing(Closure $callback): static
    {
        $this->modifyCardsUsing = $callback;

        return $this;
    }

    public function getCards(): Collection
    {
        return $this->getBoard()->getLivewire()->getBoardColumnRecords($this);
    }

    public function hasCardsModifier(): bool
    {
        return $this->modifyCardsUsing !== null;
    }

    public function applyCardsModifier(Collection $cards): Collection
    {
        return $this->modifyCardsUsing
            ? ($this->evaluate($this->modifyCardsUsing, ['cards' => $cards]) ?? $cards)
            : $cards;
    }

    public function getCardsPerPage(): ?int
    {
        return $this->hasCardsModifier() ? null : $this->getBoard()->getCardsPerPage($this);
    }

    public function getPaginator(): ?LengthAwarePaginator
    {
        $perPage = $this->getCardsPerPage();

        return $perPage === null || $this->hasInfiniteScroll() ? null : new LengthAwarePaginator(
            $this->getCards(),
            $this->getCardsCount(),
            $perPage,
            $this->getBoard()->getLivewire()->getBoardColumnPage($this),
        );
    }

    public function hasInfiniteScroll(): bool
    {
        return $this->getCardsPerPage() !== null && $this->getBoard()->hasInfiniteScroll($this);
    }

    public function hasMoreCards(): bool
    {
        return $this->hasInfiniteScroll() && $this->getCards()->count() < $this->getCardsCount();
    }

    public function getSum(string $attribute): float
    {
        return $this->getBoard()->getLivewire()->getBoardColumnSum($this, $attribute);
    }

    public function getCardsCount(): int
    {
        return $this->getBoard()->getLivewire()->getBoardColumnCount($this);
    }

    public function isOverLimit(): bool
    {
        return $this->getLimit() !== null && $this->getCardsCount() > $this->getLimit();
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'livewire' => [$this->getBoard()->getLivewire()],
            'board' => [$this->getBoard()],
            'column' => [$this],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
