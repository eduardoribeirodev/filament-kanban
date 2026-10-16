<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use EduardoRibeiroDev\FilamentKanban\Enums\CardView;
use Illuminate\Database\Eloquent\Model;

trait HasCardView
{
    protected CardView|string|Closure|null $cardView = CardView::Default;

    protected string|array|Closure|null $cardColor = null;

    public function cardColor(string|array|Closure|null $color): static
    {
        $this->cardColor = $color;

        return $this;
    }

    public function getCardColor(Model $record, BoardColumn $column): string|array
    {
        return $this->evaluateCardPresentation($this->cardColor, $record, $column) ?? $column->getColor();
    }

    public function cardView(CardView|string|Closure|null $view): static
    {
        $this->cardView = $view;

        return $this;
    }

    public function getCardView(Model $record, BoardColumn $column): string
    {
        $view = $this->evaluateCardPresentation($this->cardView, $record, $column) ?? CardView::Default;

        return $view instanceof CardView ? $view->value : $view;
    }

    protected function evaluateCardPresentation(mixed $value, Model $record, BoardColumn $column): mixed
    {
        return $this->evaluate($value, [
            'card' => $record,
            'record' => $record,
            'column' => $column,
        ], [
            Model::class => $record,
            $record::class => $record,
            BoardColumn::class => $column,
        ]);
    }
}
