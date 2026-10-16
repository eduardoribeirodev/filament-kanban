@php
    use Filament\Actions\Action;
    use Filament\Actions\ActionGroup;
    use Filament\Support\Enums\IconSize;
    use Filament\Support\View\ComponentAttributeBag;
@endphp

<li
    class="fi-kanban-empty-state fi-ta-empty-state"
    wire:key="board-empty-{{ $column->getId() }}"
    wire:sort:ignore
    role="status"
>
    @if ($emptyState = $board->getEmptyState($column))
        {{ $emptyState }}
    @else
        <div class="fi-ta-empty-state-content">
            @if (filled($icon = $board->getEmptyStateIcon($column)))
                <div class="fi-ta-empty-state-icon-bg">
                    {{ \Filament\Support\generate_icon_html($icon, attributes: new ComponentAttributeBag, size: IconSize::Large) }}
                </div>
            @endif

            <h4 class="fi-ta-empty-state-heading">
                {{ $board->getEmptyStateHeading($column) }}
            </h4>

            @if (filled($description = $board->getEmptyStateDescription($column)))
                <p class="fi-ta-empty-state-description">
                    {{ $description }}
                </p>
            @endif

            @if ($actions = array_filter(
                $board->getEmptyStateActionsForColumn($column->getId()),
                fn (Action|ActionGroup $action): bool => $action->isVisible(),
            ))
                <div class="fi-ta-actions fi-align-center fi-wrapped">
                    @foreach ($actions as $action)
                        {{ $action }}
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</li>
