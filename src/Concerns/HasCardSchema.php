<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

trait HasCardSchema
{
    /**
     * @var array<\Filament\Schemas\Components\Component> | Closure | null
     */
    protected array | Closure | null $cardSchema = null;

    /**
     * Define os componentes (entries) usados para renderizar o conteúdo
     * de cada card — o equivalente a um infolist de Resource, só que
     * aplicado a cada item do board.
     *
     * @param  array<\Filament\Schemas\Components\Component> | Closure  $schema
     */
    public function cardSchema(array | Closure $schema): static
    {
        $this->cardSchema = $schema;

        return $this;
    }

    public function hasCardSchema(): bool
    {
        return $this->cardSchema !== null;
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function getCardSchemaComponents(): array
    {
        return $this->evaluate($this->cardSchema) ?? [];
    }

    /**
     * Resolve um Schema já vinculado ao registro do card, pronto para
     * ser renderizado na view do card (ex: {{ $board->getCardSchema($card) }}).
     */
    public function getCardSchema(Model $record): Schema
    {
        return Schema::make($this->getLivewire())
            ->record($record)
            ->components($this->getCardSchemaComponents());
    }
}
