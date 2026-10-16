@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\Colors\Color;

    $cardVariant = $cardVariant ?? 'default';
    $hasDragHandle = $board->hasDragHandle();
    $title = $board->getRecordTitle($card);
    $cardAction = $board->getCardActionForRecord($card);
    $cardActions = array_filter(
        $board->getCardActionsForRecord($card),
        fn (Action|ActionGroup $action): bool => $action->isVisible(),
    );
    $hasCompactActions = $board->hasCompactCardActions($card, $column);
    $actionsMenu = $hasCompactActions && count($cardActions)
        ? ActionGroup::make($cardActions)
            ->record($card)
            ->table($board->getLivewire()->getTable())
            ->icon(Heroicon::EllipsisVertical)
            ->label(__('filament-kanban::card-views.actions'))
            ->tooltip(__('filament-kanban::card-views.actions'))
        : null;
    $hasActionsMenu = $actionsMenu && $actionsMenu->isVisible();
    $accentColor = $cardVariant === 'accent' ? $board->getCardColor($card, $column) : null;

    if (is_string($accentColor) && str_starts_with($accentColor, '#')) {
        $accentColor = Color::hex($accentColor);
    }
@endphp

<li
    @class([
        'fi-kanban-card',
        'fi-kanban-card-compact' => $cardVariant === 'compact',
        'fi-kanban-card-flat' => $cardVariant === 'flat',
        'fi-kanban-card-accent' => $cardVariant === 'accent',
        'fi-kanban-card-draggable' => ! $hasDragHandle,
    ])
    wire:key="board-card-{{ $card->getKey() }}"
    wire:sort:item="{{ $card->getKey() }}"
    @if ($accentColor !== null)
        style="{{ str_replace('--color-500:', '--kanban-card-accent:', \Filament\Support\get_color_css_variables($accentColor, shades: [500])) }}"
    @endif
>
    @if ($hasDragHandle || filled($title) || $hasActionsMenu)
        <div class="fi-kanban-card-header">
            @if ($hasDragHandle)
                <button
                    type="button"
                    wire:sort:handle
                    data-kanban-drag-handle
                    class="fi-kanban-card-drag-handle"
                    aria-label="{{ __('filament-tables::table.reorder_indicator') }}{{ filled($title) ? ': ' . $title : '' }}"
                >
                    <x-filament::icon :icon="Heroicon::Bars2" class="fi-kanban-card-drag-handle-icon" />
                </button>
            @endif

            @if (filled($title))
                @if ($cardAction && $cardAction->isVisible())
                    <div class="fi-kanban-card-title" wire:sort:ignore>
                        {{ $cardAction->label($title)->link() }}
                    </div>
                @else
                    <h4 class="fi-kanban-card-title">
                        {{ $title }}
                    </h4>
                @endif
            @endif

            @if ($hasActionsMenu)
                <div class="fi-kanban-card-header-actions" wire:sort:ignore>
                    {{ $actionsMenu }}
                </div>
            @endif
        </div>
    @endif

    {{ $board->getCardSchema($card) }}

    @if (! $hasCompactActions && count($cardActions))
        <div class="fi-kanban-card-actions" wire:sort:ignore>
            @foreach ($cardActions as $action)
                {{ $action }}
            @endforeach
        </div>
    @endif
</li>
