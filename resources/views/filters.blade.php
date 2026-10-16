@php
    use Filament\Support\Enums\Width;
    use Filament\Tables\Enums\FiltersLayout;

    $table = $board->getLivewire()->getTable();
    $layout = $table->getFiltersLayout();
    $triggerAction = $table->getFiltersTriggerAction()->badge($table->getActiveFiltersCount());
    $applyAction = $table->getFiltersApplyAction();
    $filtersForm = $table->getFiltersForm();
    $resetActionPosition = $table->getFiltersResetActionPosition();
    $isCollapsible = in_array($layout, [
        FiltersLayout::AboveContentCollapsible,
        FiltersLayout::BeforeContentCollapsible,
        FiltersLayout::AfterContentCollapsible,
    ]);
@endphp

<div class="fi-kanban-filters fi-ta-ctn">
    @if (($layout === FiltersLayout::Modal) || (($layout === FiltersLayout::Dropdown) && $triggerAction->isModalSlideOver()))
        <div class="fi-ta-header-toolbar">
            <x-filament::modal
                :alignment="$triggerAction->getModalAlignment()"
                :autofocus="$triggerAction->isModalAutofocused()"
                :close-button="$triggerAction->hasModalCloseButton()"
                :close-by-clicking-away="$triggerAction->isModalClosedByClickingAway()"
                :close-by-escaping="$triggerAction->isModalClosedByEscaping()"
                :description="$triggerAction->getModalDescription()"
                :extra-modal-window-attribute-bag="$triggerAction->getExtraModalWindowAttributeBag()"
                :extra-modal-overlay-attribute-bag="$triggerAction->getExtraModalOverlayAttributeBag()"
                :footer-actions="$triggerAction->getVisibleModalFooterActions()"
                :footer-actions-alignment="$triggerAction->getModalFooterActionsAlignment()"
                :heading="$triggerAction->getCustomModalHeading() ?? __('filament-tables::table.filters.heading')"
                :icon="$triggerAction->getModalIcon()"
                :icon-color="$triggerAction->getModalIconColor()"
                :slide-over="$triggerAction->isModalSlideOver()"
                :slide-over-position="$triggerAction->getModalSlideOverPosition()"
                :sticky-footer="$triggerAction->isModalFooterSticky()"
                :sticky-header="$triggerAction->isModalHeaderSticky()"
                :width="$table->getFiltersFormWidth()"
                :wire:key="$this->getId() . '.kanban.filters.modal'"
                class="fi-ta-filters-modal"
            >
                <x-slot name="trigger">
                    {{ $triggerAction }}
                </x-slot>

                {{ $triggerAction->getModalContent() }}
                {{ $filtersForm }}
                {{ $triggerAction->getModalContentFooter() }}
            </x-filament::modal>
        </div>
    @elseif ($layout === FiltersLayout::Dropdown)
        <div class="fi-ta-header-toolbar">
            <x-filament::dropdown
                :max-height="$table->getFiltersFormMaxHeight()"
                placement="bottom-end"
                shift
                :flip="false"
                :width="$table->getFiltersFormWidth() ?? Width::ExtraSmall"
                :wire:key="$this->getId() . '.kanban.filters.dropdown'"
                class="fi-ta-filters-dropdown"
            >
                <x-slot name="trigger">
                    {{ $triggerAction }}
                </x-slot>

                <x-filament-tables::filters
                    :apply-action="$applyAction"
                    :form="$filtersForm"
                    :reset-action-position="$resetActionPosition"
                />
            </x-filament::dropdown>
        </div>
    @else
        <div
            @class([
                'fi-ta-filters-above-content-ctn' => $layout !== FiltersLayout::BelowContent,
            ])
            @if ($isCollapsible)
                x-data="{ areFiltersOpen: false }"
                x-bind:class="{ 'fi-open': areFiltersOpen }"
            @endif
        >
            <x-filament-tables::filters
                :apply-action="$applyAction"
                :form="$filtersForm"
                :reset-action-position="$resetActionPosition"
                :class="$layout === FiltersLayout::BelowContent ? 'fi-ta-filters-below-content' : null"
                :x-show="$isCollapsible ? 'areFiltersOpen' : null"
                :x-cloak="$isCollapsible"
            />

            @if ($isCollapsible)
                <span
                    class="fi-ta-filters-trigger-action-ctn"
                    x-on:click="areFiltersOpen = ! areFiltersOpen"
                    x-bind:aria-expanded="areFiltersOpen"
                >
                    {{ $triggerAction }}
                </span>
            @endif
        </div>
    @endif
</div>
