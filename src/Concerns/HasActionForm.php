<?php

namespace EduardoRibeiroDev\FilamentKanban\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentKanban\Board;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

trait HasActionForm
{
    /**
     * @var array<Component|Action|ActionGroup>|Closure|null
     */
    protected array|Closure|null $form = null;

    /**
     * Define o formulário padrão das ações de criação, edição e visualização
     * nos cards e nas colunas. O schema explícito de uma ação tem precedência.
     *
     * @param  array<Component|Action|ActionGroup>|Closure|null  $form
     */
    public function form(array|Closure|null $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getActionFormResolver(Action $action): ?Closure
    {
        if (($this->form === null) || ! (
            ($action instanceof CreateAction)
            || ($action instanceof EditAction)
            || ($action instanceof ViewAction)
        )) {
            return null;
        }

        // Evaluate on the action so its record, arguments, and other native
        // dependencies remain available when the modal schema is resolved.
        return function (Schema $schema) use ($action): Schema|array|null {
            $form = $action->evaluate(
                $this->form,
                [
                    'board' => $this,
                    'form' => $schema,
                    'schema' => $schema,
                    'infolist' => $schema,
                    'operation' => $schema->getOperation(),
                ],
                [
                    Board::class => $this,
                    Schema::class => $schema,
                ],
            );

            // Schemas can coexist for nested actions. Each must own its fields
            // and their child components, containers, records, and state paths.
            return is_array($form)
                ? array_map(fn (Component|Action|ActionGroup $component): Component|Action|ActionGroup => $component->getClone(), $form)
                : $form;
        };
    }
}
