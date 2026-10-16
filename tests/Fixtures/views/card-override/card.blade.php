<li
    class="fi-kanban-card"
    wire:key="board-card-{{ $card->getKey() }}"
    wire:sort:item="{{ $card->getKey() }}"
>
    Custom card: {{ $board->getRecordTitle($card) }} in {{ $column->getLabel() }}
</li>
