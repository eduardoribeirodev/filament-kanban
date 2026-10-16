@if ($column->hasInfiniteScroll() && $column->getCardsCount())
    @php
        $page = $board->getLivewire()->getBoardColumnPage($column);
        $loadMore = 'loadMoreBoardColumnCards(' . \Illuminate\Support\Js::from($column->getId()) . ', ' . $page . ')';
    @endphp

    <li
        class="fi-kanban-column-load-more"
        wire:key="board-column-load-more-{{ $column->getId() }}-{{ $page }}-{{ (int) $column->hasMoreCards() }}"
        wire:sort:ignore
    >
        @if ($column->hasMoreCards())
            <span
                class="fi-kanban-column-load-more-trigger"
                aria-hidden="true"
                wire:intersect.once="{{ $loadMore }}"
            ></span>
        @endif

        <span role="status">
            {{ __('filament-kanban::pagination.loaded', ['loaded' => $column->getCards()->count(), 'total' => $column->getCardsCount()]) }}
        </span>

        @if ($column->hasMoreCards())
            <x-filament::button
                color="gray"
                size="sm"
                :wire:click="$loadMore"
                wire:loading.attr="disabled"
                wire:target="loadMoreBoardColumnCards"
                :aria-label="__('filament-kanban::pagination.load_more_column', ['column' => $column->getLabel()])"
            >
                {{ __('filament-kanban::pagination.load_more') }}
            </x-filament::button>
        @endif
    </li>
@endif
