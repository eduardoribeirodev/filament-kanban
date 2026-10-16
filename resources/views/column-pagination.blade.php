@php
    use Filament\Support\Icons\Heroicon;

    $paginator = $column->getPaginator();
@endphp

@if ($paginator?->hasPages())
    <nav class="fi-kanban-column-pagination" aria-label="{{ __('filament-kanban::pagination.label', ['column' => $column->getLabel()]) }}">
        <x-filament::icon-button
            :icon="Heroicon::ChevronLeft"
            color="gray"
            :label="__('filament-kanban::pagination.previous')"
            :disabled="$paginator->onFirstPage()"
            :wire:click="'goToBoardColumnPage(' . \Illuminate\Support\Js::from($column->getId()) . ', ' . ($paginator->currentPage() - 1) . ')'"
            wire:loading.attr="disabled"
            wire:target="goToBoardColumnPage"
        />

        <span>
            {{ __('filament-kanban::pagination.summary', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </span>

        <x-filament::icon-button
            :icon="Heroicon::ChevronRight"
            color="gray"
            :label="__('filament-kanban::pagination.next')"
            :disabled="! $paginator->hasMorePages()"
            :wire:click="'goToBoardColumnPage(' . \Illuminate\Support\Js::from($column->getId()) . ', ' . ($paginator->currentPage() + 1) . ')'"
            wire:loading.attr="disabled"
            wire:target="goToBoardColumnPage"
        />
    </nav>
@endif
