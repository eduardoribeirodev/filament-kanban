@php
    use Filament\Tables\Enums\FiltersLayout;

    $table = $board->getLivewire()->getTable();
    $filtersLayout = $table->getFiltersLayout();
    $hasFilters = $table->isFilterable() && ($filtersLayout !== FiltersLayout::Hidden);
    $hasFiltersBeforeContent = $hasFilters && in_array($filtersLayout, [FiltersLayout::BeforeContent, FiltersLayout::BeforeContentCollapsible]);
    $hasFiltersAfterContent = $hasFilters && in_array($filtersLayout, [FiltersLayout::AfterContent, FiltersLayout::AfterContentCollapsible]);
    $hasFiltersBelowContent = $hasFilters && ($filtersLayout === FiltersLayout::BelowContent);
    $filterIndicators = $table->getFilterIndicators();
@endphp

<div class="fi-kanban">
    @if ($hasFilters && ! $hasFiltersBeforeContent && ! $hasFiltersAfterContent && ! $hasFiltersBelowContent)
        @include('filament-kanban::filters')
    @endif

    @if (count($filterIndicators))
        <div class="fi-kanban-filter-indicators fi-ta-ctn">
            <div class="fi-ta-filter-indicators">
                <div>
                    <span class="fi-ta-filter-indicators-label">
                        {{ __('filament-tables::table.filters.indicator') }}
                    </span>

                    <div class="fi-ta-filter-indicators-badges-ctn">
                        @foreach ($filterIndicators as $indicator)
                            <x-filament::badge :color="$indicator->getColor()">
                                {{ $indicator->getLabel() }}

                                @if ($indicator->isRemovable())
                                    <x-slot
                                        name="deleteButton"
                                        :label="__('filament-tables::table.filters.actions.remove.label')"
                                        :wire:click="$indicator->getRemoveLivewireClickHandler()"
                                        wire:loading.attr="disabled"
                                        wire:target="removeTableFilter"
                                    ></x-slot>
                                @endif
                            </x-filament::badge>
                        @endforeach
                    </div>
                </div>

                @if (collect($filterIndicators)->contains(fn ($indicator): bool => $indicator->isRemovable()))
                    {{ $table->getFiltersRemoveAllAction() }}
                @endif
            </div>
        </div>
    @endif

    <div class="fi-kanban-layout">
        @if ($hasFiltersBeforeContent)
            <div class="fi-kanban-filters-sidebar">
                @include('filament-kanban::filters')
            </div>
        @endif

        <div class="fi-kanban-board">
            @foreach ($board->getColumns() as $column)
                {{ $column }}
            @endforeach
        </div>

        @if ($hasFiltersAfterContent)
            <div class="fi-kanban-filters-sidebar">
                @include('filament-kanban::filters')
            </div>
        @endif
    </div>

    @if ($hasFiltersBelowContent)
        @include('filament-kanban::filters')
    @endif

    <x-filament-actions::modals />
</div>
