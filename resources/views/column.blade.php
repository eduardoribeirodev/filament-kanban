@php
    use Filament\Support\Colors\Color;

    $board = $column->getBoard();
    $cards = $column->getCards();
    $hasDragHandle = $board->hasDragHandle();
    $columnActions = $board->getColumnActionsForColumn($column->getId());
    $color = $column->getColor();

    if (is_string($color) && str_starts_with($color, '#')) {
        $color = Color::hex($color);
    }
@endphp

<div
    class="fi-kanban-column"
    wire:key="board-column-{{ $column->getId() }}"
>
    <div
        class="fi-kanban-column-accent"
        style="{{ \Filament\Support\get_color_css_variables($color, shades: [500]) }}"
    ></div>

    <div class="fi-kanban-column-header">
        <div class="fi-kanban-column-heading">
            <div class="fi-kanban-column-label">
                @if ($icon = $column->getIcon())
                    <x-filament::icon :icon="$icon" class="fi-kanban-column-icon" />
                @endif

                <h3 class="fi-kanban-column-title">
                    {{ $column->getLabel() }}
                </h3>

                <x-filament::badge :color="$column->isOverLimit() ? 'danger' : $color" size="sm">
                    {{ $column->getCardsCount() }}{{ $column->getLimit() ? ' / ' . $column->getLimit() : '' }}
                </x-filament::badge>
            </div>

            @if (count($columnActions))
                <div class="fi-kanban-column-actions" wire:sort:ignore>
                    @foreach ($columnActions as $action)
                        {{ $action }}
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <ul
        class="fi-kanban-column-cards"
        wire:sort.ghost="sortBoardCard"
        wire:sort:group="kanban-cards"
        wire:sort:group-id="{{ $column->getId() }}"
        wire:sort:config="{
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 220,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 5,
            handle: {{ $hasDragHandle ? "'[data-kanban-drag-handle]'" : 'null' }},
        }"
    >
        @forelse ($cards as $card)
            @include($board->getCardView($card, $column), ['board' => $board, 'column' => $column, 'card' => $card])
        @empty
            @include('filament-kanban::empty-state', ['board' => $board, 'column' => $column])
        @endforelse

        @include('filament-kanban::column-load-more', ['column' => $column])
    </ul>

    @include('filament-kanban::column-pagination', ['column' => $column])
</div>
