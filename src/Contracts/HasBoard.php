<?php

namespace EduardoRibeiroDev\FilamentKanban\Contracts;

use EduardoRibeiroDev\FilamentKanban\Board;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Collection;

/**
 * Deve ser implementada pelo componente Livewire (Page, Widget ou
 * componente customizado) que hospeda o board — assim como HasTable
 * é implementada por quem hospeda uma Table do Filament.
 *
 * Use em conjunto com a trait Concerns\InteractsWithBoard.
 */
interface HasBoard extends HasActions, HasSchemas, HasTable
{
    /**
     * Define a configuração do board (colunas, query, schema, actions...).
     * Chamado uma única vez e cacheado pela trait InteractsWithBoard.
     */
    public function board(Board $board): Board;

    public function getBoard(): Board;

    public function getBoardRecords(): Collection;
}
