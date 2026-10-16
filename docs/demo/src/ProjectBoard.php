<?php

namespace KanbanDemo;

use BackedEnum;
use EduardoRibeiroDev\FilamentKanban\Board;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use EduardoRibeiroDev\FilamentKanban\Enums\CardView;
use EduardoRibeiroDev\FilamentKanban\Pages\BoardPage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Attributes\Url;

class ProjectBoard extends BoardPage
{
    protected static ?string $title = 'Project board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    #[Url]
    public string $variant = 'filament-kanban::cards.accent';

    #[Url]
    public bool $compact = true;

    #[Url]
    public bool $handle = false;

    #[Url]
    public bool $infinite = false;

    #[Url]
    public string $dataset = 'launch';

    public function getSubheading(): string
    {
        return $this->dataset === 'scale'
            ? ($this->infinite
                ? '3,000 fictional tasks. Scroll each column to load more work.'
                : '3,000 fictional tasks. Three cards per column, with independent pagination.')
            : 'Plan the next release. Move work forward, one card at a time.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('appearance')
                ->label('Customize view')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Customize your board')
                ->modalDescription('Choose a card style and how to display its actions.')
                ->schema([
                    Select::make('variant')->label('Card style')->options(CardView::class)->required(),
                    Toggle::make('compact')->label('Compact card actions')->helperText('Group actions in a menu in the card header.'),
                    Toggle::make('handle')->label('Show a drag handle')->helperText('By default, the entire card can be dragged.'),
                    Toggle::make('infinite')->label('Progressive loading')->helperText('Load more cards automatically as you scroll down each column.'),
                ])
                ->fillForm(fn (): array => ['variant' => $this->variant, 'compact' => $this->compact, 'handle' => $this->handle, 'infinite' => $this->infinite])
                ->modalSubmitActionLabel('Apply changes')
                ->action(function (array $data): void {
                    $this->variant = $data['variant'] instanceof CardView ? $data['variant']->value : $data['variant'];
                    $this->compact = $data['compact'];
                    $this->handle = $data['handle'];
                    if ($this->infinite !== $data['infinite']) {
                        $this->boardColumnPages = [];
                    }
                    $this->infinite = $data['infinite'];
                    $this->board = null;
                }),
            Action::make('dataset')
                ->label(fn (): string => $this->dataset === 'scale' ? 'Launch project' : '3,000 task demo')
                ->icon(Heroicon::OutlinedCircleStack)
                ->color('gray')
                ->action(function (): void {
                    $this->dataset = $this->dataset === 'scale' ? 'launch' : 'scale';
                    $this->boardColumnPages = [];
                    $this->board = null;
                    $this->resetTableFiltersForm();
                }),
        ];
    }

    public function board(Board $board): Board
    {
        $createTask = fn (string $name): CreateAction => CreateAction::make($name)
            ->label('Add task')
            ->modalHeading('Create task')
            ->modalSubmitActionLabel('Create task')
            ->model(Task::class)
            ->mutateDataUsing(function (array $data, array $arguments): array {
                $data['status'] = $arguments['column'];
                $data['project'] = $this->dataset;
                $data['position'] = ((int) Task::where('project', $this->dataset)->where('status', $data['status'])->max('position')) + 1;

                return $data;
            });

        return $board
            ->query(fn () => Task::query()->where('project', $this->dataset))
            ->statusColumn('status')
            ->positionColumn('position')
            ->cardsPerPage(3)
            ->infiniteScroll($this->infinite)
            ->columns([
                BoardColumn::make('backlog')->label('Backlog')->color('gray')->icon('heroicon-o-inbox-stack'),
                BoardColumn::make('in_progress')->label('In progress')->color('warning')->icon('heroicon-o-arrow-path')->limit(5),
                BoardColumn::make('done')->label('Done')->color('success')->icon('heroicon-o-check-circle'),
            ])
            ->recordTitleAttribute('title')
            ->cardAction('edit')
            ->cardView(CardView::tryFrom($this->variant) ?? CardView::Default)
            ->cardColor(fn (Task $record): string => match ($record->priority) {
                'high' => 'danger',
                'medium' => 'warning',
                default => 'success',
            })
            ->compactCardActions($this->compact)
            ->dragHandle($this->handle)
            ->cardSchema([
                TextEntry::make('description')->hiddenLabel()->color('gray'),
                Grid::make(2)->schema([
                    TextEntry::make('priority')->hiddenLabel()->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(fn (string $state): string => match ($state) {
                            'high' => 'danger', 'medium' => 'warning', default => 'success'
                        }),
                    TextEntry::make('due_date')->hiddenLabel()->date('M j')->icon(Heroicon::OutlinedCalendarDays)->color('gray'),
                ]),
                TextEntry::make('assignee')->hiddenLabel()->icon(Heroicon::OutlinedUserCircle)->color('gray'),
            ])
            ->form([
                TextInput::make('title')->required()->maxLength(120)->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('assignee')->options(self::assignees())->required(),
                DatePicker::make('due_date')->label('Due date')->native(false)->required(),
                ToggleButtons::make('priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])
                    ->colors(['low' => 'success', 'medium' => 'warning', 'high' => 'danger'])
                    ->default('medium')->grouped()->required()->columnSpanFull(),
            ])
            ->cardActions([
                EditAction::make()->modalHeading('Edit task'),
                Action::make('complete')->label('Mark as done')->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')->visible(fn (Task $record): bool => $record->status !== 'done')
                    ->action(fn (Task $record) => $record->update(['status' => 'done'])),
                DeleteAction::make()->modalHeading('Delete task'),
            ])
            ->columnActions([$createTask('create')->icon(Heroicon::Plus)->iconButton()])
            ->filters([
                Filter::make('search')->label('Search')->schema([
                    TextInput::make('value')->label('Search tasks')->placeholder('Search by title...')->live(debounce: 400),
                ])->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%')))
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'Search: '.$data['value'] : null),
                SelectFilter::make('priority')->options(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low']),
                SelectFilter::make('assignee')->label('Assigned to')->options(self::assignees()),
            ])
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->emptyStateHeading('No tasks here yet')
            ->emptyStateDescription('Create a task or drop one into this column.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateActions([$createTask('createEmpty')->icon(Heroicon::Plus)->button()]);
    }

    public static function assignees(): array
    {
        return ['Alex Morgan' => 'Alex Morgan', 'Jamie Chen' => 'Jamie Chen', 'Sam Rivera' => 'Sam Rivera', 'Emma Brooks' => 'Emma Brooks'];
    }
}
