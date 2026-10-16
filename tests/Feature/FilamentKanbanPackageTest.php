<?php

namespace EduardoRibeiroDev\FilamentKanban\Tests\Feature;

use DOMDocument;
use DOMXPath;
use EduardoRibeiroDev\FilamentKanban\Board;
use EduardoRibeiroDev\FilamentKanban\BoardColumn;
use EduardoRibeiroDev\FilamentKanban\Enums\CardView;
use EduardoRibeiroDev\FilamentKanban\Pages\BoardPage;
use EduardoRibeiroDev\FilamentKanban\Tests\TestCase;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema as FormSchema;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

class FilamentKanbanPackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Schema::create('kanban_package_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->string('priority');
            $table->unsignedInteger('tenant_id');
            $table->decimal('position', 20, 10)->nullable();
            $table->boolean('flagged')->default(false);
            $table->softDeletes();
        });

        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        view()->replaceNamespace('filament-kanban', dirname((new ReflectionClass(Board::class))->getFileName(), 2).'/resources/views');
        view()->addNamespace('kanban-package-tests', dirname(__DIR__).'/Fixtures/views');
    }

    public function test_native_filters_update_cards_indicators_and_reset_without_a_page_table(): void
    {
        $this->createCard(['name' => 'Urgent task', 'priority' => 'high']);
        $this->createCard(['name' => 'Regular task']);
        $this->createCard(['name' => 'Another tenant task', 'tenant_id' => 2]);

        $page = Livewire::test(PackageKanbanPage::class)
            ->assertSuccessful()
            ->assertSee('Urgent task')
            ->assertSee('Regular task')
            ->assertDontSee('Another tenant task')
            ->assertTableFilterExists('priority')
            ->filterTable('priority', 'high')
            ->assertSee('Urgent task')
            ->assertDontSee('Regular task');

        $this->assertCount(1, $page->instance()->getTable()->getFilterIndicators());
        $this->assertStringContainsString('High', $page->instance()->getTable()->getFilterIndicators()[0]->getLabel());

        $page->removeTableFilter('priority')
            ->assertSee('Regular task')
            ->filterTable('priority', 'normal')
            ->assertDontSee('Urgent task')
            ->resetTableFilters()
            ->assertSee('Urgent task')
            ->assertSee('Regular task');

        $this->assertCount(0, $page->instance()->getTable()->getFilterIndicators());
    }

    public function test_deferred_filters_only_change_cards_when_applied(): void
    {
        $this->createCard(['name' => 'Urgent task', 'priority' => 'high']);
        $this->createCard(['name' => 'Regular task']);

        Livewire::test(PackageKanbanPage::class, ['deferred' => true])
            ->set('tableDeferredFilters.priority.value', 'high')
            ->assertSee('Urgent task')
            ->assertSee('Regular task')
            ->call('applyTableFilters')
            ->assertSee('Urgent task')
            ->assertDontSee('Regular task')
            ->resetTableFilters()
            ->assertSee('Regular task');
    }

    public function test_empty_states_follow_filtered_cards_in_each_column(): void
    {
        $record = $this->createCard();
        $page = Livewire::test(PackageKanbanPage::class)->assertSuccessful();

        $this->assertCount(1, $this->xpath($page->html())->query('//li[@role="status"]'));
        $this->assertCount(1, $this->xpath($page->html())->query('//*[@*[name() = "wire:key"] = "board-empty-done"]//h4'));

        $page->filterTable('priority', 'high');
        $this->assertCount(2, $this->xpath($page->html())->query('//li[@role="status"]'));

        $page->resetTableFilters()->call('sortBoardCard', (string) $record->id, 0, 'done');
        $this->assertCount(1, $this->xpath($page->html())->query('//*[@*[name() = "wire:key"] = "board-empty-todo"]'));
        $this->assertCount(0, $this->xpath($page->html())->query('//*[@*[name() = "wire:key"] = "board-empty-done"]'));
    }

    public function test_empty_state_content_and_actions_are_resolved_for_each_column(): void
    {
        $page = Livewire::test(PackageEmptyStateKanbanPage::class)
            ->assertSuccessful()
            ->assertSee('No tasks in To do')
            ->assertSee('No tasks in Done')
            ->assertSee('Add a task to To do.')
            ->assertSee('Add a task to Done.')
            ->assertSee('New todo')
            ->assertSee('New done')
            ->assertDontSee('Hidden action')
            ->assertDontSee('Forbidden action');

        $this->assertCount(2, $this->xpath($page->html())->query('//*[contains(@class, "fi-ta-empty-state-icon-bg")]/svg'));

        foreach (['todo', 'done'] as $column) {
            $emptyState = $page->instance()->getBoard()->getEmptyStateActionsForColumn($column);
            $this->assertSame($column, $emptyState[0]->getFlatActions()['createEmpty']->getArguments()['column']);
        }

        $this->assertSame([], $page->instance()->getTable()->getAction('createEmpty')->getArguments());
    }

    public function test_empty_state_create_action_uses_the_shared_form_and_selected_column(): void
    {
        $page = Livewire::test(PackageEmptyStateKanbanPage::class)
            ->mountAction(TestAction::make('createEmpty')->table()->arguments(['column' => 'done']))
            ->assertFormFieldExists('name')
            ->setActionData(['name' => 'Created from empty state'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Created from empty state')
            ->assertDontSee('No tasks in Done');

        $this->assertSame('done', PackageKanbanRecord::query()->where('name', 'Created from empty state')->sole()->status);
        $this->assertCount(1, $this->xpath($page->html())->query('//li[@role="status"]'));
    }

    public function test_empty_state_icon_can_be_removed_and_custom_view_replaces_default_content(): void
    {
        $withoutIcon = Livewire::test(PackageEmptyStateKanbanPage::class, ['hideIcon' => true])
            ->assertSuccessful()
            ->assertSee('No tasks in To do');

        $this->assertCount(0, $this->xpath($withoutIcon->html())->query('//*[contains(@class, "fi-ta-empty-state-icon-bg")]'));

        Livewire::test(PackageEmptyStateKanbanPage::class, ['customView' => true])
            ->assertSuccessful()
            ->assertSee('Custom empty state: To do')
            ->assertSee('Custom empty state: Done')
            ->assertDontSee('No tasks in')
            ->assertDontSee('Add a task');
    }

    public function test_card_view_can_be_overridden_without_overriding_the_column(): void
    {
        $this->createCard(['name' => 'Customized task']);
        view()->prependNamespace('filament-kanban', dirname(__DIR__).'/Fixtures/views/card-override');

        Livewire::test(PackageKanbanPage::class)
            ->assertSuccessful()
            ->assertSee('Custom card: Customized task in To do')
            ->assertSee('To do')
            ->assertSee('Done')
            ->assertSee('board-empty-done', escape: false);
    }

    #[DataProvider('cardViewPresets')]
    public function test_card_view_presets_keep_the_card_schema_actions_and_drag_handle(CardView $view, bool $useHandle): void
    {
        $first = $this->createCard(['name' => 'Read-only task']);
        $second = $this->createCard(['name' => 'Second preset task']);

        $page = Livewire::test(PackageCardViewKanbanPage::class, [
            'selectedView' => $view->value,
            'useHandle' => $useHandle,
            'hideEditForFirstRecord' => true,
        ])
            ->assertSuccessful()
            ->assertSee('Read-only task')
            ->assertSee('Second preset task')
            ->assertSee('normal');

        $xpath = $this->xpath($page->html());
        $this->assertCount(2, $xpath->query('//ul/li[@*[name() = "wire:sort:item"]]'));
        $this->assertCount($useHandle ? 2 : 0, $xpath->query('//*[@data-kanban-drag-handle]'));

        foreach ([$first, $second] as $record) {
            $this->assertCount(1, $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]'));
            $this->assertCount(1, $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]/div[@class="fi-kanban-card-actions"]'));
            $this->assertCount(0, $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]//div[@class="fi-kanban-card-header-actions"]'));
        }

        $this->assertCount(0, $xpath->query(
            '//li[@*[name() = "wire:key"] = "board-card-'.$first->id.'"]//*[@*[name() = "wire:click"] and contains(@*[name() = "wire:click"], "mountAction(\'edit\'")]',
        ));

        $page->callAction(TestAction::make('edit')->table($second), ['name' => 'Updated preset task'])
            ->assertHasNoActionErrors();

        $this->assertSame('Updated preset task', $second->fresh()->name);
        $this->assertSame('Read-only task', $first->fresh()->name);
    }

    public static function cardViewPresets(): iterable
    {
        foreach (CardView::cases() as $view) {
            yield $view->name.' whole card' => [$view, false];
            yield $view->name.' with handle' => [$view, true];
        }
    }

    #[DataProvider('cardActionCompactionModes')]
    public function test_card_actions_compact_into_the_header_or_remain_in_the_footer_for_every_variant(CardView $view, bool $compact): void
    {
        $first = $this->createCard(['name' => 'First positioned task']);
        $second = $this->createCard(['name' => 'Second positioned task']);
        $page = Livewire::test(PackageCardViewKanbanPage::class, [
            'selectedView' => $view->value,
            'compactActions' => $compact,
        ])->assertSuccessful();
        $xpath = $this->xpath($page->html());
        $containerClass = $compact ? 'fi-kanban-card-header-actions' : 'fi-kanban-card-actions';

        foreach ([$first, $second] as $record) {
            $containers = $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]//div[@class="'.$containerClass.'"]');
            $this->assertCount(1, $containers);
            $container = $containers->item(0);
            $this->assertTrue($container->hasAttribute('wire:sort:ignore'));
            $this->assertCount($compact ? 0 : 1, $xpath->query('./*[@*[name() = "wire:click"] and contains(@*[name() = "wire:click"], "mountAction(\'edit\'")]', $container));
            $this->assertCount(1, $xpath->query('./div[@x-data="filamentDropdown"]', $container));
            $this->assertCount($compact ? 2 : 1, $xpath->query('.//div[@x-data="filamentDropdown"]', $container));
            $this->assertStringContainsString('Flag '.$record->name, $container->textContent);
        }

        $page->callAction(TestAction::make('edit')->table($first), ['name' => 'Edited positioned task'])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('flag')->table($second))
            ->assertHasNoActionErrors();

        $this->assertSame('Edited positioned task', $first->fresh()->name);
        $this->assertFalse($first->fresh()->flagged);
        $this->assertTrue($second->fresh()->flagged);
    }

    public static function cardActionCompactionModes(): iterable
    {
        foreach (CardView::cases() as $view) {
            yield $view->name.' compact actions' => [$view, true];
            yield $view->name.' footer actions' => [$view, false];
        }
    }

    public function test_card_action_compaction_resolves_record_and_column_context_and_can_be_disabled(): void
    {
        $urgent = $this->createCard(['priority' => 'high']);
        $normal = $this->createCard();
        $done = $this->createCard(['priority' => 'high', 'status' => 'done']);
        $page = Livewire::test(PackageCardViewKanbanPage::class, ['dynamicCompactActions' => true])->assertSuccessful();
        $xpath = $this->xpath($page->html());

        $this->assertCount(1, $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$urgent->id.'"]//div[@class="fi-kanban-card-header-actions"]'));

        foreach ([$normal, $done] as $record) {
            $this->assertCount(1, $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]/div[@class="fi-kanban-card-actions"]'));
        }

        $board = $page->instance()->getBoard();
        $this->assertFalse($board->compactCardActions(false)->hasCompactCardActions($urgent, $board->getColumn('todo')));
        $this->assertTrue($board->compactCardActions()->hasCompactCardActions($normal, $board->getColumn('todo')));
    }

    public function test_a_custom_card_view_can_be_selected_without_publishing_views(): void
    {
        $this->createCard(['name' => 'Custom view task']);

        Livewire::test(PackageCardViewKanbanPage::class, ['selectedView' => 'kanban-package-tests::card-override.card'])
            ->assertSuccessful()
            ->assertSee('Custom card: Custom view task in To do')
            ->assertSee('board-empty-done', escape: false);
    }

    public function test_card_views_can_be_resolved_per_record_and_column_and_reset_to_default(): void
    {
        $first = $this->createCard(['name' => 'Urgent preset task', 'priority' => 'high']);
        $second = $this->createCard(['name' => 'Done custom task', 'status' => 'done']);
        $page = Livewire::test(PackageCardViewKanbanPage::class, ['dynamicViews' => true])
            ->assertSuccessful()
            ->assertSee('Urgent preset task')
            ->assertSee('Custom card: Done custom task in Done');

        $board = $page->instance()->getBoard();
        $todo = $board->getColumn('todo');
        $done = $board->getColumn('done');

        $this->assertSame(CardView::Compact->value, $board->getCardView($first, $todo));
        $this->assertSame('kanban-package-tests::card-override.card', $board->getCardView($second, $done));
        $this->assertSame(CardView::Default->value, $board->cardView(null)->getCardView($first, $todo));
        $this->assertSame(CardView::Default->value, $board->cardView(fn () => null)->getCardView($second, $done));
    }

    public function test_card_view_enum_provides_filament_labels_in_portuguese_and_english(): void
    {
        app()->setLocale('pt_BR');
        $this->assertSame(['Padrão', 'Compacto', 'Plano', 'Destaque'], array_map(fn (CardView $view) => $view->getLabel(), CardView::cases()));

        app()->setLocale('en');
        $this->assertSame(['Default', 'Compact', 'Flat', 'Accent'], array_map(fn (CardView $view) => $view->getLabel(), CardView::cases()));
    }

    public function test_thousands_of_records_only_hydrate_the_visible_page_in_each_column(): void
    {
        $this->insertCards(1200, 'todo');
        $this->insertCards(800, 'done');
        $this->insertCards(500, 'todo', tenant: 2);
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.PackageKanbanRecord::class, function () use (&$hydrated): void {
            $hydrated++;
        });

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['perPage' => 25])->assertSuccessful();
        $board = $page->instance()->getBoard();

        $this->assertCount(50, $board->getRecords());
        $this->assertSame(50, $hydrated);
        $this->assertSame(1200, $board->getColumn('todo')->getCardsCount());
        $this->assertSame(800, $board->getColumn('done')->getCardsCount());
        $this->assertCount(50, $this->xpath($page->html())->query('//li[@*[name() = "wire:sort:item"]]'));
        $this->assertStringNotContainsString('@js(', $page->html());
        $this->assertCount(1, $this->xpath($page->html())->query('//button[@*[name() = "wire:click"] = "goToBoardColumnPage(\'todo\', 2)"]'));
        $page->assertDontSee('todo task 26')->assertDontSee('done task 26');

        $page->call('goToBoardColumnPage', 'todo', 2)
            ->assertSee('todo task 26')
            ->assertDontSee('todo task 1<', escape: false)
            ->assertSee('done task 1');

        $this->assertSame(2, $page->instance()->getBoardColumnPage($page->instance()->getBoard()->getColumn('todo')));
        $this->assertSame(1, $page->instance()->getBoardColumnPage($page->instance()->getBoard()->getColumn('done')));
        $this->assertCount(50, $page->instance()->getBoardRecords());
    }

    public function test_progressive_loading_keeps_previous_cards_and_only_expands_the_requested_column(): void
    {
        $this->insertCards(1200, 'todo');
        $this->insertCards(800, 'done');
        $this->insertCards(500, 'todo', tenant: 2);
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.PackageKanbanRecord::class, function () use (&$hydrated): void {
            $hydrated++;
        });

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['perPage' => 25, 'infinite' => true])->assertSuccessful();
        $this->assertSame(50, $hydrated);
        $this->assertCount(50, $page->instance()->getBoardRecords());
        $column = $page->instance()->getBoard()->getColumn('todo');
        $this->assertNull($column->getPaginator());
        $this->assertTrue($column->hasMoreCards());
        $this->assertSame(1200, $column->getCardsCount());
        $this->assertSame(720600.0, $column->getSum('position'));
        $this->assertCount(2, $this->xpath($page->html())->query('//*[@*[name() = "wire:intersect.once"]]'));
        $this->assertCount(0, $this->xpath($page->html())->query('//nav[contains(@class, "fi-kanban-column-pagination")]'));

        $hydrated = 0;
        $page->call('loadMoreBoardColumnCards', 'todo', 1)
            ->assertSee('todo task 1<', escape: false)
            ->assertSee('todo task 50')
            ->assertDontSee('todo task 51')
            ->assertSee('done task 1')
            ->assertDontSee('done task 26');
        $this->assertSame(75, $hydrated);
        $records = $page->instance()->getBoardRecords();
        $this->assertCount(75, $records);
        $this->assertCount(75, $records->unique(fn ($record) => $record->getKey()));
        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
        $this->assertSame(1, $page->get('boardColumnPages')['done']);
    }

    public function test_progressive_loading_ignores_stale_requests_and_stops_at_the_end(): void
    {
        $this->insertCards(5, 'todo');
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true])
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->call('loadMoreBoardColumnCards', 'todo', 1);
        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
        $this->assertCount(4, $page->instance()->getBoard()->getColumn('todo')->getCards());

        $page->call('loadMoreBoardColumnCards', 'todo', 2)
            ->call('loadMoreBoardColumnCards', 'todo', 3)
            ->assertSee('todo task 5');
        $this->assertSame(3, $page->get('boardColumnPages')['todo']);
        $this->assertFalse($page->instance()->getBoard()->getColumn('todo')->hasMoreCards());
        $this->assertCount(0, $this->xpath($page->html())->query('//*[@*[name() = "wire:intersect.once"]]'));
        $page->call('goToBoardColumnPage', 'todo', 99);
        $this->assertSame(3, $page->get('boardColumnPages')['todo']);
        $page->call('loadMoreBoardColumnCards', 'unknown', 1)->assertNotFound();
    }

    public function test_progressive_loading_resets_all_columns_when_filters_are_applied_or_reset(): void
    {
        $this->insertCards(8, 'todo');
        $this->insertCards(6, 'done');
        $this->createCard(['name' => 'Urgent progressive task', 'priority' => 'high']);
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true, 'deferred' => true])
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->call('loadMoreBoardColumnCards', 'todo', 2)
            ->call('loadMoreBoardColumnCards', 'done', 1)
            ->set('tableDeferredFilters.priority.value', 'high');
        $this->assertSame(3, $page->get('boardColumnPages')['todo']);

        $page->call('applyTableFilters')->assertSee('Urgent progressive task')->assertDontSee('todo task 1');
        $this->assertSame(['todo' => 1, 'done' => 1], $page->get('boardColumnPages'));
        $this->assertCount(1, $page->instance()->getBoardRecords());
        $page->resetTableFilters()->assertSee('todo task 1')->assertDontSee('todo task 3');
        $this->assertSame(['todo' => 1, 'done' => 1], $page->get('boardColumnPages'));
        $this->assertCount(4, $page->instance()->getBoardRecords());
    }

    public function test_progressive_sorting_uses_the_full_loaded_prefix_and_preserves_hidden_and_unloaded_cards(): void
    {
        $first = $this->createCard(['priority' => 'high', 'position' => 100]);
        $hidden = $this->createCard(['position' => 200]);
        $second = $this->createCard(['priority' => 'high', 'position' => 300]);
        $third = $this->createCard(['priority' => 'high', 'position' => 400]);
        $fourth = $this->createCard(['priority' => 'high', 'position' => 500]);
        $last = $this->createCard(['priority' => 'high', 'position' => 600]);
        $incoming = $this->createCard(['priority' => 'high', 'status' => 'done']);

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true])
            ->filterTable('priority', 'high')
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->call('sortBoardCard', (string) $fourth->id, 0, 'todo');
        $this->assertSame([$fourth->id, $first->id, $hidden->id, $second->id, $third->id, $last->id], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());

        $page->call('sortBoardCard', (string) $incoming->id, 4, 'todo')->assertSuccessful();
        $this->assertSame([$fourth->id, $first->id, $hidden->id, $second->id, $third->id, $incoming->id, $last->id], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());
        $this->assertTrue($page->instance()->getBoard()->getColumn('todo')->getCards()->contains('id', $incoming->id));
        $this->assertSame(3, $page->get('boardColumnPages')['todo']);
    }

    public function test_prepend_keeps_loaded_batches_in_progressive_columns(): void
    {
        $this->insertCards(6, 'todo');
        $incoming = $this->createCard(['status' => 'done']);
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true, 'moveIncomingToTop' => true])
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->call('sortBoardCard', (string) $incoming->id, 3, 'todo');
        $column = $page->instance()->getBoard()->getColumn('todo');
        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
        $this->assertCount(4, $column->getCards());
        $this->assertSame($incoming->id, $column->getCards()->first()->id);
        $this->assertSame(7, $column->getCardsCount());
    }

    public function test_card_actions_preserve_progressive_batches_and_fill_deleted_slots(): void
    {
        $this->insertCards(5, 'todo');
        $fourth = PackageKanbanRecord::query()->where('position', 4)->firstOrFail();
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true])
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->callAction(TestAction::make('edit')->table($fourth), ['name' => 'Edited progressive card'])
            ->assertHasNoActionErrors()
            ->assertSee('Edited progressive card')
            ->callAction(TestAction::make('delete')->table($fourth))
            ->assertHasNoActionErrors()
            ->assertDontSee('Edited progressive card')
            ->assertSee('todo task 5');
        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
        $this->assertCount(4, $page->instance()->getBoardRecords());
    }

    public function test_progressive_loading_supports_collections_and_resets_their_filtered_batches(): void
    {
        $page = Livewire::test(PackagePaginatedCollectionKanbanPage::class, ['infinite' => true])
            ->assertSee('Urgent collection task')
            ->assertDontSee('Regular collection task')
            ->call('loadMoreBoardColumnCards', 'todo', 1)
            ->assertSee('Urgent collection task')
            ->assertSee('Regular collection task');
        $this->assertSame(3.0, $page->instance()->getBoard()->getColumn('todo')->getSum('id'));
        $page->filterTable('priority', 'high')->assertDontSee('Regular collection task');
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
        $this->assertDatabaseCount('kanban_package_records', 0);
    }

    public function test_progressive_loading_is_disabled_for_unbounded_or_modified_columns(): void
    {
        $this->insertCards(7, 'todo');
        foreach ([['unbounded' => true], ['modifyCards' => true]] as $config) {
            $page = Livewire::test(PackagePaginatedKanbanPage::class, ['infinite' => true, ...$config]);
            $column = $page->instance()->getBoard()->getColumn('todo');
            $this->assertFalse($column->hasInfiniteScroll());
            $page->call('loadMoreBoardColumnCards', 'todo', 1)->assertSuccessful();
            $this->assertCount(0, $this->xpath($page->html())->query('//*[@*[name() = "wire:intersect.once"]]'));
        }
    }

    public function test_progressive_loading_can_be_configured_per_column(): void
    {
        $page = Livewire::test(PackagePaginatedKanbanPage::class);
        $board = $page->instance()->getBoard()->infiniteScroll(fn (BoardColumn $column, Board $board, $livewire): bool => $column->getId() === 'todo' && $livewire->getBoard() === $board);
        $this->assertTrue($board->getColumn('todo')->hasInfiniteScroll());
        $this->assertFalse($board->getColumn('done')->hasInfiniteScroll());
        $board->infiniteScroll(false);
        $this->assertFalse($board->getColumn('todo')->hasInfiniteScroll());
    }

    public function test_filter_application_and_reset_return_all_columns_to_the_first_page(): void
    {
        $this->insertCards(6, 'todo');
        $this->insertCards(5, 'done');
        $this->createCard(['name' => 'Urgent page task', 'priority' => 'high']);

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['deferred' => true])
            ->call('goToBoardColumnPage', 'todo', 3)
            ->call('goToBoardColumnPage', 'done', 2)
            ->set('tableDeferredFilters.priority.value', 'high');

        $this->assertSame(3, $page->get('boardColumnPages')['todo']);

        $page->call('applyTableFilters')->assertSee('Urgent page task');
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
        $this->assertSame(1, $page->get('boardColumnPages')['done']);
        $this->assertSame(1, $page->instance()->getBoard()->getColumn('todo')->getCardsCount());

        $page->resetTableFilters()->assertSee('todo task 1');
        $this->assertSame(7, $page->instance()->getBoard()->getColumn('todo')->getCardsCount());
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
    }

    public function test_deleting_the_last_card_on_a_page_returns_to_the_last_available_page(): void
    {
        $this->insertCards(5, 'todo');
        $last = PackageKanbanRecord::query()->orderByDesc('id')->first();

        $page = Livewire::test(PackagePaginatedKanbanPage::class)
            ->call('goToBoardColumnPage', 'todo', 3)
            ->callAction(TestAction::make('delete')->table($last))
            ->assertHasNoActionErrors()
            ->assertSee('todo task 3')
            ->assertSee('todo task 4')
            ->assertDontSee('todo task 5');

        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
        $this->assertSame(4, $page->instance()->getBoard()->getColumn('todo')->getCardsCount());
    }

    public function test_sorting_on_a_later_filtered_page_keeps_unloaded_and_hidden_records_in_order(): void
    {
        $first = $this->createCard(['priority' => 'high', 'position' => 100]);
        $hidden = $this->createCard(['position' => 200]);
        $second = $this->createCard(['priority' => 'high', 'position' => 300]);
        $third = $this->createCard(['priority' => 'high', 'position' => 400]);
        $fourth = $this->createCard(['priority' => 'high', 'position' => 500]);
        $last = $this->createCard(['priority' => 'high', 'position' => 600]);
        $incoming = $this->createCard(['priority' => 'high', 'status' => 'done']);

        $page = Livewire::test(PackagePaginatedKanbanPage::class)
            ->filterTable('priority', 'high')
            ->call('goToBoardColumnPage', 'todo', 2)
            ->call('sortBoardCard', (string) $fourth->id, 0, 'todo');

        $this->assertSame([$first->id, $hidden->id, $second->id, $fourth->id, $third->id, $last->id], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());

        $page->call('sortBoardCard', (string) $incoming->id, 2, 'todo')->assertSuccessful();

        $this->assertSame([$first->id, $hidden->id, $second->id, $fourth->id, $third->id, $incoming->id, $last->id], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());
    }

    public function test_large_column_reordering_updates_siblings_in_batches(): void
    {
        $this->insertCards(1201, 'todo');
        $last = PackageKanbanRecord::query()->orderByDesc('id')->first();
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['perPage' => 5]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $page->call('sortBoardCard', (string) $last->id, 0, 'todo')->assertSuccessful();
            $updates = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update "kanban_package_records"'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertGreaterThan(0, $updates->count());
        $this->assertLessThanOrEqual(4, $updates->count());
        $this->assertSame($last->id, PackageKanbanRecord::query()->orderBy('position')->value('id'));
        $this->assertSame(1201, (int) PackageKanbanRecord::query()->max('position'));
    }

    #[DataProvider('incomingCardPlacementModes')]
    public function test_cross_column_moves_can_prepend_instead_of_using_the_drop_position(bool $prepend): void
    {
        $first = $this->createCard(['position' => 100]);
        $second = $this->createCard(['position' => 200]);
        $third = $this->createCard(['position' => 300]);
        $incoming = $this->createCard(['status' => 'done']);
        $foreign = $this->createCard(['tenant_id' => 2, 'position' => 500]);

        $page = Livewire::test(PackageKanbanPage::class, [
            'moveIncomingToTop' => $prepend,
            'customPersistence' => true,
        ])
            ->call('sortBoardCard', (string) $incoming->id, 2, 'todo')
            ->assertSuccessful()
            ->assertDispatched('kanban-card-moved', card: (string) $incoming->id, column: 'todo');

        $expected = $prepend
            ? [$incoming->id, $first->id, $second->id, $third->id]
            : [$first->id, $second->id, $incoming->id, $third->id];

        $this->assertSame($expected, PackageKanbanRecord::query()->where('tenant_id', 1)->where('status', 'todo')->orderBy('position')->pluck('id')->all());
        $this->assertSame([[$incoming->id, 'todo', $prepend ? 1 : 3]], $page->get('persistedMoves'));
        $this->assertTrue($incoming->fresh()->flagged);
        $this->assertSame(500, (int) $foreign->fresh()->position);
    }

    public static function incomingCardPlacementModes(): iterable
    {
        yield 'prepend incoming cards' => [true];
        yield 'keep the drop position by default' => [false];
    }

    public function test_prepend_places_a_card_before_hidden_records_and_returns_the_destination_to_page_one(): void
    {
        $hidden = $this->createCard(['position' => 100]);
        $visible = [];

        foreach (range(1, 5) as $index) {
            $visible[] = $this->createCard(['name' => 'Visible task '.$index, 'priority' => 'high', 'position' => 100 + $index * 100]);
        }

        foreach ([100, 200, 400, 500] as $position) {
            $this->createCard(['status' => 'done', 'priority' => 'high', 'position' => $position]);
        }

        $incoming = $this->createCard(['name' => 'Incoming task', 'status' => 'done', 'priority' => 'high', 'position' => 300]);
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['moveIncomingToTop' => true])
            ->filterTable('priority', 'high')
            ->call('goToBoardColumnPage', 'todo', 2)
            ->call('goToBoardColumnPage', 'done', 2)
            ->call('sortBoardCard', (string) $incoming->id, 1, 'todo')
            ->assertSuccessful()
            ->assertSee('Incoming task');

        $this->assertSame([$incoming->id, $hidden->id, ...array_map(fn ($record) => $record->id, $visible)], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], PackageKanbanRecord::query()->where('status', 'todo')->orderBy('position')->pluck('position')->map(fn ($value): int => (int) $value)->all());
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
        $this->assertSame(2, $page->get('boardColumnPages')['done']);
        $this->assertSame([$incoming->id, $visible[0]->id], $page->instance()->getBoard()->getColumn('todo')->getCards()->modelKeys());
        $this->assertSame([100, 200, 400, 500], PackageKanbanRecord::query()->where('status', 'done')->orderBy('position')->pluck('position')->map(fn ($value): int => (int) $value)->all());
    }

    public function test_prepend_option_keeps_same_column_reordering_on_the_current_page(): void
    {
        $this->insertCards(6, 'todo');
        $cards = PackageKanbanRecord::query()->orderBy('position')->get();

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['moveIncomingToTop' => true])
            ->call('goToBoardColumnPage', 'todo', 2)
            ->call('sortBoardCard', (string) $cards[3]->id, 0, 'todo')
            ->assertSuccessful();

        $this->assertSame([$cards[0]->id, $cards[1]->id, $cards[3]->id, $cards[2]->id, $cards[4]->id, $cards[5]->id], PackageKanbanRecord::query()->orderBy('position')->pluck('id')->all());
        $this->assertSame(2, $page->get('boardColumnPages')['todo']);
    }

    public function test_prepend_can_move_to_an_empty_column_without_a_drop_position(): void
    {
        $incoming = $this->createCard();

        Livewire::test(PackageKanbanPage::class, ['moveIncomingToTop' => true])
            ->call('moveCard', (string) $incoming->id, 'done')
            ->assertSuccessful();

        $this->assertSame('done', $incoming->fresh()->status);
        $this->assertSame(1, (int) $incoming->fresh()->position);
    }

    public function test_prepend_condition_receives_the_record_destination_and_board_context(): void
    {
        $record = $this->createCard();
        $page = Livewire::test(PackageKanbanPage::class);
        $board = $page->instance()->getBoard()->moveCardsToTop(
            fn (PackageKanbanRecord $item, BoardColumn $column, Board $board, $livewire): bool => $item->is($record)
                && $column->getId() === 'done'
                && $livewire->getBoard() === $board,
        );

        $this->assertTrue($board->shouldMoveCardsToTop($record, $board->getColumn('done')));
        $this->assertFalse($board->shouldMoveCardsToTop($record, $board->getColumn('todo')));
        $this->assertFalse($board->moveCardsToTop(false)->shouldMoveCardsToTop($record, $board->getColumn('done')));
    }

    public function test_prepend_requires_a_position_column_to_persist_order(): void
    {
        $record = $this->createCard();
        $page = Livewire::test(PackageKanbanPage::class);
        $page->instance()->getBoard()->positionColumn(null)->moveCardsToTop();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('moveCardsToTop() requires a positionColumn()');
        $page->instance()->moveCard((string) $record->id, 'done', 0);
    }

    public function test_counts_limits_and_sums_include_records_outside_the_current_page(): void
    {
        $this->insertCards(7, 'todo');
        $page = Livewire::test(PackagePaginatedKanbanPage::class);
        $column = $page->instance()->getBoard()->getColumn('todo')->limit(5);

        $this->assertCount(2, $column->getCards());
        $this->assertSame(7, $column->getCardsCount());
        $this->assertTrue($column->isOverLimit());
        $this->assertSame(28.0, $column->getSum('position'));

        $page->call('goToBoardColumnPage', 'todo', 99);
        $this->assertSame(4, $page->get('boardColumnPages')['todo']);
        $page->call('goToBoardColumnPage', 'todo', -1);
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
        $page->call('goToBoardColumnPage', 'unknown', 2)->assertNotFound();
    }

    public function test_pagination_can_be_disabled_and_collection_modifiers_keep_their_full_column(): void
    {
        $this->insertCards(7, 'todo');
        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['perPage' => null]);
        $column = $page->instance()->getBoard()->getColumn('todo');
        $this->assertCount(7, $column->getCards());
        $this->assertNull($column->getPaginator());

        $page = Livewire::test(PackagePaginatedKanbanPage::class, ['modifyCards' => true]);
        $column = $page->instance()->getBoard()->getColumn('todo');
        $this->assertCount(6, $column->getCards());
        $this->assertSame(6, $column->getCardsCount());
        $this->assertNull($column->getPaginator());
    }

    public function test_accent_cards_use_the_column_color_or_a_record_specific_palette(): void
    {
        $first = $this->createCard(['priority' => 'high']);
        $second = $this->createCard(['priority' => 'normal']);
        $page = Livewire::test(PackageCardViewKanbanPage::class, ['selectedView' => CardView::Accent->value]);
        $board = $page->instance()->getBoard();
        $this->assertSame('gray', $board->getCardColor($first, $board->getColumn('todo')));

        $page = Livewire::test(PackageCardViewKanbanPage::class, ['selectedView' => CardView::Accent->value, 'customColor' => true]);
        $xpath = $this->xpath($page->html());
        $firstStyle = $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$first->id.'"]')->item(0)->getAttribute('style');
        $secondStyle = $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$second->id.'"]')->item(0)->getAttribute('style');
        $this->assertStringContainsString('--kanban-card-accent:var(--danger-500)', $firstStyle);
        $this->assertNotSame($firstStyle, $secondStyle);
        $this->assertStringNotContainsString('--color-500:', $secondStyle);
    }

    public function test_collection_pagination_keeps_full_sums_and_applies_filters_before_slicing(): void
    {
        $page = Livewire::test(PackagePaginatedCollectionKanbanPage::class)
            ->assertSee('Urgent collection task')
            ->assertDontSee('Regular collection task');
        $column = $page->instance()->getBoard()->getColumn('todo');
        $this->assertSame(2, $column->getCardsCount());
        $this->assertSame(3.0, $column->getSum('id'));

        $page->call('goToBoardColumnPage', 'todo', 2)
            ->assertSee('Regular collection task')
            ->filterTable('priority', 'high')
            ->assertSee('Urgent collection task')
            ->assertDontSee('Regular collection task');
        $this->assertSame(1, $page->get('boardColumnPages')['todo']);
        $this->assertDatabaseCount('kanban_package_records', 0);
    }

    #[DataProvider('filterLayouts')]
    public function test_native_filter_layouts_render_the_board(FiltersLayout $layout): void
    {
        $this->createCard(['name' => 'Task in every layout']);

        $page = Livewire::test(PackageKanbanPage::class, ['filterLayout' => $layout->name])
            ->assertSuccessful()
            ->assertSee('Task in every layout');

        if ($layout === FiltersLayout::Hidden) {
            $page->assertDontSee('fi-kanban-filters', escape: false);
        } else {
            $page->assertSee('fi-kanban-filters', escape: false);
        }

        if ($layout === FiltersLayout::BelowContent) {
            $this->assertCount(1, $this->xpath($page->html())->query(
                '//*[contains(concat(" ", normalize-space(@class), " "), " fi-ta-filters ") and contains(concat(" ", normalize-space(@class), " "), " fi-ta-filters-below-content ")]',
            ));
        }
    }

    public static function filterLayouts(): iterable
    {
        foreach (FiltersLayout::cases() as $layout) {
            yield $layout->name => [$layout];
        }
    }

    public function test_each_card_renders_its_own_schema_and_grouped_action_record(): void
    {
        $first = $this->createCard(['name' => 'First task', 'priority' => 'high']);
        $second = $this->createCard(['name' => 'Second task']);

        $page = Livewire::test(PackageKanbanPage::class)->assertSuccessful();
        $xpath = $this->xpath($page->html());

        foreach ([[$first, $second], [$second, $first]] as [$record, $otherRecord]) {
            $cards = $xpath->query('//li[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]');

            $this->assertCount(1, $cards);
            $this->assertStringContainsString($record->name, $cards->item(0)->textContent);
            $this->assertStringContainsString('Flag '.$record->name, $cards->item(0)->textContent);
            $this->assertStringNotContainsString($otherRecord->name, $cards->item(0)->textContent);
        }
    }

    public function test_native_edit_and_grouped_custom_actions_target_the_selected_card(): void
    {
        $first = $this->createCard(['name' => 'First task']);
        $second = $this->createCard(['name' => 'Second task']);

        Livewire::test(PackageKanbanPage::class)
            ->callAction(TestAction::make('edit')->table($first), ['name' => 'Edited first task'])
            ->assertHasNoActionErrors()
            ->assertSee('Edited first task')
            ->callAction(TestAction::make('flag')->table($second))
            ->assertHasNoActionErrors();

        $this->assertSame('Edited first task', $first->fresh()->name);
        $this->assertFalse($first->fresh()->flagged);
        $this->assertSame('Second task', $second->fresh()->name);
        $this->assertTrue($second->fresh()->flagged);
    }

    public function test_native_delete_action_removes_only_the_selected_card(): void
    {
        $first = $this->createCard(['name' => 'Keep task']);
        $second = $this->createCard(['name' => 'Delete task']);

        Livewire::test(PackageKanbanPage::class)
            ->callAction(TestAction::make('delete')->table($second))
            ->assertHasNoActionErrors()
            ->assertSee('Keep task')
            ->assertDontSee('Delete task');

        $this->assertModelExists($first);
        $this->assertModelMissing($second);
    }

    public function test_one_default_form_creates_edits_and_views_records_in_nested_action_groups(): void
    {
        $first = $this->createCard(['name' => 'First shared task']);
        $second = $this->createCard(['name' => 'Second shared task']);

        $page = Livewire::test(PackageSharedFormKanbanPage::class)
            ->mountAction(TestAction::make('edit')->table($second))
            ->assertActionMounted(TestAction::make('edit')->table($second))
            ->assertFormFieldExists('name', fn (TextInput $field): bool => $field->getLabel() === 'Shared edit '.$second->id.' card')
            ->assertActionDataSet(['name' => 'Second shared task'])
            ->setActionData(['name' => 'Edited shared task'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('Edited shared task', $second->fresh()->name);
        $this->assertSame('First shared task', $first->fresh()->name);

        $page->mountAction(TestAction::make('view')->table($first))
            ->assertActionMounted(TestAction::make('view')->table($first))
            ->assertFormFieldExists('name', fn (TextInput $field): bool => $field->getLabel() === 'Shared view '.$first->id.' card')
            ->assertFormFieldIsDisabled('name')
            ->assertActionDataSet(['name' => 'First shared task'])
            ->unmountAction()
            ->mountAction(TestAction::make('create')->table()->arguments(['column' => 'done']))
            ->assertActionMounted(TestAction::make('create')->table()->arguments(['column' => 'done']))
            ->assertFormFieldExists('name', fn (TextInput $field): bool => $field->getLabel() === 'Shared create new done')
            ->setActionData(['name' => 'Created shared task'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Created shared task');

        $this->assertSame('done', PackageKanbanRecord::query()->where('name', 'Created shared task')->sole()->status);
    }

    public function test_shared_form_accepts_an_array_of_fields(): void
    {
        $record = $this->createCard();

        Livewire::test(PackageSharedFormKanbanPage::class, ['useArrayForm' => true])
            ->callAction(TestAction::make('edit')->table($record), ['name' => 'Updated with array form'])
            ->assertHasNoActionErrors();

        $this->assertSame('Updated with array form', $record->fresh()->name);
    }

    public function test_shared_array_form_keeps_simultaneous_action_schemas_independent(): void
    {
        $first = $this->createCard(['name' => 'First shared task']);
        $second = $this->createCard(['name' => 'Second shared task']);
        $livewire = Livewire::test(PackageSharedFormKanbanPage::class)->instance();
        $livewire->getBoard()->form([
            TextInput::make('name'),
            Grid::make()->schema([TextInput::make('priority')]),
        ]);

        $firstSchema = EditAction::make()->table($livewire->getTable())->record($first)
            ->getSchema(FormSchema::make($livewire)->record($first)->operation('edit')->statePath('firstAction'));
        $firstFields = array_values($firstSchema->getFlatFields());

        $secondSchema = ViewAction::make()->table($livewire->getTable())->record($second)
            ->getSchema(FormSchema::make($livewire)->record($second)->operation('view')->statePath('secondAction'));
        $secondFields = array_values($secondSchema->getFlatFields());

        $this->assertCount(2, $firstFields);
        $this->assertCount(2, $secondFields);

        foreach ($firstFields as $index => $field) {
            $this->assertNotSame($field, $secondFields[$index]);
            $this->assertSame($first, $field->getRecord());
            $this->assertSame($second, $secondFields[$index]->getRecord());
            $this->assertSame('firstAction.'.$field->getName(), $field->getStatePath());
            $this->assertSame('secondAction.'.$field->getName(), $secondFields[$index]->getStatePath());
            $this->assertFalse($field->isDisabled());
            $this->assertTrue($secondFields[$index]->isDisabled());
        }
    }

    public function test_an_explicit_action_schema_replaces_the_default_form(): void
    {
        $record = $this->createCard();

        Livewire::test(PackageSharedFormKanbanPage::class)
            ->mountAction(TestAction::make('editPriority')->table($record))
            ->assertActionMounted(TestAction::make('editPriority')->table($record))
            ->assertFormFieldExists('priority')
            ->assertFormFieldDoesNotExist('name')
            ->setActionData(['priority' => 'high'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('high', $record->fresh()->priority);
        $this->assertSame('Package task', $record->fresh()->name);
    }

    public function test_an_explicit_empty_action_schema_disables_the_default_form(): void
    {
        $record = $this->createCard();

        $page = Livewire::test(PackageSharedFormKanbanPage::class)
            ->mountAction(TestAction::make('editWithoutForm')->table($record))
            ->assertActionMounted(TestAction::make('editWithoutForm')->table($record));

        $this->assertNull($page->instance()->getSchema($page->instance()->getMountedActionSchemaName()));
    }

    public function test_confirmation_actions_do_not_receive_the_default_form(): void
    {
        $record = $this->createCard();

        $page = Livewire::test(PackageSharedFormKanbanPage::class);

        foreach (['delete', 'confirm'] as $action) {
            $page->mountAction(TestAction::make($action)->table($record))
                ->assertActionMounted(TestAction::make($action)->table($record));

            $this->assertNull($page->instance()->getSchema($page->instance()->getMountedActionSchemaName()));

            $page->unmountAction();
        }

        $trashed = PackageSharedFormKanbanRecord::findOrFail($record->id);
        $trashed->delete();

        $page->mountAction(TestAction::make('restore')->table($trashed))
            ->assertActionMounted(TestAction::make('restore')->table($trashed));

        $this->assertNull($page->instance()->getSchema($page->instance()->getMountedActionSchemaName()));

        $page->callMountedAction()->assertHasNoActionErrors();

        $this->assertFalse($trashed->fresh()->trashed());
    }

    public function test_the_package_registers_and_publishes_its_compiled_styles_without_the_application_build(): void
    {
        $styles = FilamentAsset::getStyles(['eduardoribeirodev/filament-kanban']);
        $styles = array_values(array_filter($styles, fn (Css $style): bool => $style->getId() === 'filament-kanban'));

        $this->assertCount(1, $styles);

        $style = $styles[0];
        $this->assertStringEndsWith('/dist/filament-kanban.css', $style->getPath());
        $this->assertFileExists($style->getPath());

        $css = file_get_contents($style->getPath());

        foreach (['.fi-kanban-board', '.fi-kanban-card', '.fi-kanban-column'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertDoesNotMatchRegularExpression('/@(source|apply|tailwind)\b/', $css);
        $this->assertDoesNotMatchRegularExpression('/(?:^|[},])\s*(?:html|body|button|input|h[1-6])\s*(?:[,:{])/', $css);

        $originalPublicPath = public_path();
        $temporaryPublicPath = sys_get_temp_dir().'/kanban-package-assets-'.bin2hex(random_bytes(8));
        $filesystem = app(Filesystem::class);

        try {
            app()->usePublicPath($temporaryPublicPath);

            $this->assertSame(0, Artisan::call('filament:assets'));
            $this->assertFileExists($style->getPublicPath());
            $this->assertSame($css, file_get_contents($style->getPublicPath()));
            $this->assertStringContainsString(
                '/css/eduardoribeirodev/filament-kanban/filament-kanban.css',
                FilamentAsset::renderStyles(['eduardoribeirodev/filament-kanban']),
            );
        } finally {
            app()->usePublicPath($originalPublicPath);
            $filesystem->deleteDirectory($temporaryPublicPath);
        }
    }

    public function test_package_views_use_the_dedicated_stylesheet(): void
    {
        $viewsPath = dirname((new ReflectionClass(Board::class))->getFileName(), 2).'/resources/views';

        foreach (app(Filesystem::class)->allFiles($viewsPath) as $view) {
            $this->assertStringNotContainsString('<style', $view->getContents(), $view->getRelativePathname());
        }
    }

    public function test_native_column_action_receives_the_selected_column(): void
    {
        $page = Livewire::test(PackageKanbanPage::class)->assertSuccessful();

        $this->assertCreateActionsTargetTheirColumns($page->html());

        $page->callAction(TestAction::make('create')->table()->arguments(['column' => 'done']), ['name' => 'Created in done'])
            ->assertHasNoActionErrors()
            ->assertSee('Created in done');

        $record = PackageKanbanRecord::query()->sole();

        $this->assertSame('done', $record->status);
        $this->assertSame(1, $record->tenant_id);
    }

    public function test_nested_column_actions_render_and_submit_their_own_column_arguments(): void
    {
        $page = Livewire::test(PackageKanbanPage::class, ['groupColumnActions' => true])->assertSuccessful();

        $this->assertCreateActionsTargetTheirColumns($page->html());

        $page->callAction(TestAction::make('create')->table()->arguments(['column' => 'done']), ['name' => 'Nested creation'])
            ->assertHasNoActionErrors()
            ->assertSee('Nested creation');

        $this->assertSame('done', PackageKanbanRecord::query()->sole()->status);
    }

    public function test_default_card_action_respects_the_visibility_of_its_group(): void
    {
        $readOnly = $this->createCard(['name' => 'Read-only task']);
        $editable = $this->createCard(['name' => 'Editable task']);

        $page = Livewire::test(PackageKanbanPage::class, ['hideEditForFirstRecord' => true])->assertSuccessful();
        $xpath = $this->xpath($page->html());

        foreach ([[$readOnly, 0], [$editable, 1]] as [$record, $expectedActions]) {
            $actions = $xpath->query('//*[@*[name() = "wire:key"] = "board-card-'.$record->id.'"]//*[contains(@class, "fi-kanban-card-title")]//*[@*[name() = "wire:click"] and contains(@*[name() = "wire:click"], "mountAction")]');

            $this->assertCount($expectedActions, $actions);
        }
    }

    public function test_records_only_boards_receive_filter_state_in_the_collection_callback(): void
    {
        $page = Livewire::test(PackageCollectionKanbanPage::class)
            ->assertSuccessful()
            ->assertSee('Urgent collection task')
            ->assertSee('Regular collection task')
            ->filterTable('priority', 'high')
            ->assertSee('Urgent collection task')
            ->assertDontSee('Regular collection task')
            ->resetTableFilters()
            ->assertSee('Regular collection task');

        $this->assertFalse($page->instance()->getTable()->hasQuery());
        $this->assertDatabaseCount('kanban_package_records', 0);
    }

    public function test_cards_drag_by_the_whole_card_by_default(): void
    {
        $this->createCard();

        $page = Livewire::test(PackageKanbanPage::class)->assertSuccessful();

        $this->assertCount(0, $this->xpath($page->html())->query('//*[@data-kanban-drag-handle]'));
        $this->assertStringContainsString('handle: null', $page->html());
    }

    public function test_optional_drag_handle_can_be_enabled_with_a_closure(): void
    {
        $this->createCard();

        $page = Livewire::test(PackageKanbanPage::class, ['useHandle' => true])->assertSuccessful();
        $handles = $this->xpath($page->html())->query('//*[@data-kanban-drag-handle]');

        $this->assertCount(1, $handles);
        $this->assertTrue($handles->item(0)->hasAttribute('wire:sort:handle'));
    }

    public function test_native_sorting_persists_order_and_keeps_hidden_cards(): void
    {
        $first = $this->createCard(['priority' => 'high', 'position' => 100]);
        $hidden = $this->createCard(['position' => 200]);
        $second = $this->createCard(['priority' => 'high', 'position' => 300]);
        $third = $this->createCard(['priority' => 'high', 'position' => 400]);

        Livewire::test(PackageKanbanPage::class)
            ->filterTable('priority', 'high')
            ->call('sortBoardCard', (string) $third->id, 1, 'todo')
            ->assertSuccessful();

        $this->assertSame(
            [$first->id, $hidden->id, $third->id, $second->id],
            PackageKanbanRecord::query()->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_sorting_uses_visible_card_positions_when_the_query_has_an_existing_order(): void
    {
        $first = $this->createCard(['name' => 'First task', 'position' => 100]);
        $second = $this->createCard(['name' => 'Second task', 'position' => 200]);
        $third = $this->createCard(['name' => 'Third task', 'position' => 300]);

        Livewire::test(PackageKanbanPage::class, ['reverseQuery' => true])
            ->assertSeeInOrder(['First task', 'Second task', 'Third task'])
            ->call('sortBoardCard', (string) $third->id, 0, 'todo')
            ->assertSuccessful();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            PackageKanbanRecord::query()->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_move_card_compatibility_persists_status_and_emits_an_event(): void
    {
        $record = $this->createCard();

        Livewire::test(PackageKanbanPage::class)
            ->call('moveCard', (string) $record->id, 'done', 0)
            ->assertSuccessful()
            ->assertDispatched('kanban-card-moved', card: (string) $record->id, column: 'done');

        $this->assertSame('done', $record->fresh()->status);
    }

    public function test_move_calls_the_custom_persistence_callback(): void
    {
        $record = $this->createCard();

        $page = Livewire::test(PackageKanbanPage::class, ['customPersistence' => true])
            ->call('sortBoardCard', (string) $record->id, 0, 'done')
            ->assertSuccessful();

        $this->assertSame([[$record->id, 'done', 1]], $page->get('persistedMoves'));
        $this->assertSame('done', $record->fresh()->status);
        $this->assertTrue($record->fresh()->flagged);
    }

    public function test_move_rejects_records_outside_the_native_board_query(): void
    {
        $record = $this->createCard(['tenant_id' => 2]);

        Livewire::test(PackageKanbanPage::class)
            ->call('sortBoardCard', (string) $record->id, 0, 'done')
            ->assertNotFound();

        $this->assertSame('todo', $record->fresh()->status);
    }

    public function test_move_rejects_columns_outside_the_native_board_configuration(): void
    {
        $record = $this->createCard();

        Livewire::test(PackageKanbanPage::class)
            ->call('sortBoardCard', (string) $record->id, 0, 'unknown')
            ->assertNotFound();

        $this->assertSame('todo', $record->fresh()->status);
    }

    private function insertCards(int $count, string $status, int $tenant = 1): void
    {
        foreach (array_chunk(range(1, $count), 500) as $indexes) {
            PackageKanbanRecord::insert(array_map(fn (int $index): array => [
                'name' => $status.' task '.$index,
                'status' => $status,
                'priority' => 'normal',
                'tenant_id' => $tenant,
                'position' => $index,
            ], $indexes));
        }
    }

    private function createCard(array $attributes = []): PackageKanbanRecord
    {
        return PackageKanbanRecord::create([
            'name' => 'Package task',
            'status' => 'todo',
            'priority' => 'normal',
            'tenant_id' => 1,
            'position' => 100,
            ...$attributes,
        ]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function assertCreateActionsTargetTheirColumns(string $html): void
    {
        $xpath = $this->xpath($html);

        foreach (['todo', 'done'] as $column) {
            $actions = $xpath->query('//*[@*[name() = "wire:key"] = "board-column-'.$column.'"]//*[@*[name() = "wire:click"] and contains(@*[name() = "wire:click"], "mountAction")]');

            $this->assertCount(1, $actions);
            $handler = str_replace('\\u0022', '"', $actions->item(0)->getAttribute('wire:click'));
            $this->assertStringContainsString("mountAction('create'", $handler);
            $this->assertStringContainsString('"column":"'.$column.'"', $handler);
        }
    }
}

class PackageKanbanPage extends BoardPage
{
    public bool $moveIncomingToTop = false;

    public ?bool $useHandle = null;

    public bool $deferred = false;

    public string $filterLayout = 'AboveContent';

    public bool $customPersistence = false;

    public bool $groupColumnActions = false;

    public bool $hideEditForFirstRecord = false;

    public bool $reverseQuery = false;

    public array $persistedMoves = [];

    public function board(Board $board): Board
    {
        $createAction = CreateAction::make()
            ->schema([TextInput::make('name')->required()])
            ->mutateDataUsing(fn (array $data, array $arguments): array => [
                ...$data,
                'status' => $arguments['column'],
                'priority' => 'normal',
                'tenant_id' => 1,
                'position' => 100,
            ]);

        $columnActions = $this->groupColumnActions
            ? [ActionGroup::make([ActionGroup::make([$createAction])])]
            : [$createAction];

        $editAction = EditAction::make()->schema([TextInput::make('name')->required()]);

        if ($this->hideEditForFirstRecord) {
            $editAction = ActionGroup::make([$editAction])
                ->hidden(fn (Model $record): bool => $record->name === 'Read-only task');
        }

        $board
            ->query(fn () => PackageKanbanRecord::query()
                ->where('tenant_id', 1)
                ->when($this->reverseQuery, fn ($query) => $query->orderByDesc('id')))
            ->statusColumn('status')
            ->positionColumn('position')
            ->moveCardsToTop($this->moveIncomingToTop)
            ->recordTitleAttribute('name')
            ->columns([
                BoardColumn::make('todo')->label('To do'),
                BoardColumn::make('done')->label('Done'),
            ])
            ->filters([
                SelectFilter::make('priority')->options(['high' => 'High', 'normal' => 'Normal']),
            ], constant(FiltersLayout::class.'::'.$this->filterLayout))
            ->deferFilters($this->deferred)
            ->cardSchema([
                TextEntry::make('name'),
                TextEntry::make('priority'),
            ])
            ->cardActions([
                $editAction,
                ActionGroup::make([
                    Action::make('flag')
                        ->label(fn (Model $record): string => 'Flag '.$record->name)
                        ->action(fn (Model $record) => $record->update(['flagged' => true])),
                    DeleteAction::make(),
                ]),
            ])
            ->cardAction('edit')
            ->columnActions($columnActions);

        if ($this->useHandle !== null) {
            $board->dragHandle(fn (): bool => $this->useHandle);
        }

        if ($this->customPersistence) {
            $board->updateStatusUsing(function (Model $record, int|string $columnId, ?int $position): void {
                $this->persistedMoves[] = [$record->getKey(), $columnId, $position];
                $record->update(['status' => $columnId, 'position' => $position, 'flagged' => true]);
            });
        }

        return $board;
    }

    public function render(): View
    {
        return view('kanban-package-tests::board');
    }
}

class PackageCardViewKanbanPage extends PackageKanbanPage
{
    public ?bool $compactActions = null;

    public bool $dynamicCompactActions = false;

    public bool $customColor = false;

    public string $selectedView = 'filament-kanban::card';

    public bool $dynamicViews = false;

    public function board(Board $board): Board
    {
        $board = parent::board($board);

        if ($this->compactActions !== null) {
            $board->compactCardActions($this->compactActions);
        }

        if ($this->dynamicCompactActions) {
            $board->compactCardActions(function (PackageKanbanRecord $item, BoardColumn $column, Board $board, $livewire): bool {
                return $item->priority === 'high' && $column->getId() === 'todo' && $livewire->getBoard() === $board;
            });
        }

        if ($this->customColor) {
            $board->cardColor(fn (Model $record): string => $record->priority === 'high' ? 'danger' : '#22c55e');
        }

        if (! $this->dynamicViews) {
            return $board->cardView(CardView::tryFrom($this->selectedView) ?? $this->selectedView);
        }

        return $board->cardView(function (PackageKanbanRecord $item, BoardColumn $column, Board $board, $livewire): CardView|string|null {
            if ($column->getId() === 'done' && $livewire->getBoard() === $board) {
                return 'kanban-package-tests::card-override.card';
            }

            return $item->priority === 'high' ? CardView::Compact : null;
        });
    }
}

class PackagePaginatedKanbanPage extends PackageKanbanPage
{
    public ?int $perPage = 2;

    public bool $modifyCards = false;

    public bool $infinite = false;

    public bool $unbounded = false;

    public function board(Board $board): Board
    {
        $board = parent::board($board)->cardsPerPage($this->unbounded ? null : $this->perPage)->infiniteScroll($this->infinite);

        if ($this->modifyCards) {
            $board->getColumn('todo')->modifyCardsUsing(fn ($cards) => $cards->filter(fn ($record) => $record->position > 1)->values());
        }

        return $board;
    }
}

class PackageEmptyStateKanbanPage extends BoardPage
{
    public bool $hideIcon = false;

    public bool $customView = false;

    public function board(Board $board): Board
    {
        $board
            ->query(fn () => PackageSharedFormKanbanRecord::query()->where('tenant_id', 1))
            ->statusColumn('status')
            ->recordTitleAttribute('name')
            ->columns([
                BoardColumn::make('todo')->label('To do'),
                BoardColumn::make('done')->label('Done'),
            ])
            ->cardSchema([TextEntry::make('name')])
            ->form([TextInput::make('name')->required()])
            ->emptyStateHeading(fn (BoardColumn $column, Board $board): string => 'No tasks in '.$board->getColumn($column->getId())->getLabel())
            ->emptyStateDescription(fn ($column, $livewire): string => 'Add a task to '.$livewire->getBoard()->getColumn($column->getId())->getLabel().'.')
            ->emptyStateIcon($this->hideIcon ? null : Heroicon::OutlinedInboxStack)
            ->emptyStateActions(fn (): ActionGroup => ActionGroup::make([
                ActionGroup::make([
                    CreateAction::make('createEmpty')
                        ->label(fn (array $arguments): string => 'New '.($arguments['column'] ?? 'task'))
                        ->button()
                        ->mutateDataUsing(fn (array $data, array $arguments): array => [
                            ...$data,
                            'status' => $arguments['column'],
                            'priority' => 'normal',
                            'tenant_id' => 1,
                            'position' => 100,
                        ]),
                    Action::make('hidden')->label('Hidden action')->hidden()->action(fn () => null),
                    Action::make('forbidden')->label('Forbidden action')->authorize(false)->action(fn () => null),
                ])->dropdown(false),
            ])->dropdown(false));

        if ($this->customView) {
            $board->emptyState(fn (BoardColumn $column): View => view('kanban-package-tests::empty-state', ['column' => $column]));
        }

        return $board;
    }

    public function render(): View
    {
        return view('kanban-package-tests::board');
    }
}

class PackageSharedFormKanbanPage extends BoardPage
{
    public bool $useArrayForm = false;

    public function board(Board $board): Board
    {
        return $board
            ->query(fn () => PackageSharedFormKanbanRecord::query()->withTrashed()->where('tenant_id', 1))
            ->statusColumn('status')
            ->recordTitleAttribute('name')
            ->columns([
                BoardColumn::make('todo')->label('To do'),
                BoardColumn::make('done')->label('Done'),
            ])
            ->cardSchema([TextEntry::make('name')])
            ->form($this->useArrayForm
                ? [TextInput::make('name')->required()]
                : function (FormSchema $schema, string $operation, ?Model $record, array $arguments): FormSchema {
                    return $schema->components([
                        TextInput::make('name')
                            ->label('Shared '.$operation.' '.($record?->getKey() ?? 'new').' '.($arguments['column'] ?? 'card'))
                            ->required(),
                    ]);
                })
            ->cardActions([
                ActionGroup::make([
                    ActionGroup::make([
                        EditAction::make(),
                        ViewAction::make(),
                        EditAction::make('editPriority')->schema([TextInput::make('priority')->required()]),
                        EditAction::make('editWithoutForm')->schema([])->requiresConfirmation(),
                        DeleteAction::make(),
                        RestoreAction::make(),
                        Action::make('confirm')->requiresConfirmation()->action(fn () => null),
                    ]),
                ]),
            ])
            ->columnActions([
                ActionGroup::make([
                    ActionGroup::make([
                        CreateAction::make()
                            ->mutateDataUsing(fn (array $data, array $arguments): array => [
                                ...$data,
                                'status' => $arguments['column'],
                                'priority' => 'normal',
                                'tenant_id' => 1,
                                'position' => 100,
                            ]),
                    ]),
                ]),
            ]);
    }

    public function render(): View
    {
        return view('kanban-package-tests::board');
    }
}

class PackageCollectionKanbanPage extends BoardPage
{
    public function board(Board $board): Board
    {
        return $board
            ->records(function (array $filters) {
                $records = collect([
                    new PackageKanbanRecord(['id' => 1, 'name' => 'Urgent collection task', 'status' => 'todo', 'priority' => 'high']),
                    new PackageKanbanRecord(['id' => 2, 'name' => 'Regular collection task', 'status' => 'todo', 'priority' => 'normal']),
                ]);

                if ($priority = $filters['priority']['value'] ?? null) {
                    return $records->where('priority', $priority);
                }

                return $records;
            })
            ->statusColumn('status')
            ->recordTitleAttribute('name')
            ->columns([BoardColumn::make('todo')])
            ->cardSchema([TextEntry::make('name')])
            ->filters([SelectFilter::make('priority')->options(['high' => 'High', 'normal' => 'Normal'])])
            ->deferFilters(false);
    }

    public function render(): View
    {
        return view('kanban-package-tests::board');
    }
}

class PackagePaginatedCollectionKanbanPage extends PackageCollectionKanbanPage
{
    public bool $infinite = false;

    public function board(Board $board): Board
    {
        return parent::board($board)->cardsPerPage(1)->infiniteScroll($this->infinite);
    }
}

class PackageKanbanRecord extends Model
{
    protected $table = 'kanban_package_records';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['flagged' => 'boolean', 'tenant_id' => 'integer'];
    }
}

class PackageSharedFormKanbanRecord extends PackageKanbanRecord
{
    use SoftDeletes;
}
