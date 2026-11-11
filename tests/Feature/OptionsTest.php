<?php

use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias;
use Livewire\Livewire;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = actingAsUser();
});

it('customizes the default view with a preset keyed default', function () {
    $active = Product::factory()->create(['is_active' => true]);
    $inactive = Product::factory()->create(['is_active' => false]);

    $component = new class extends ListProducts
    {
        public function getPresetViews(): array
        {
            return [
                'default' => PresetView::make('Everything active')
                    ->icon('heroicon-o-check')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
            ];
        }
    };

    livewire($component::class)
        ->assertSet('activeDynamicTableView', 'default')
        ->assertSee('Everything active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('refuses to save while a preset view with a query is active', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->createUsingPresetView(false);
        }
    };

    livewire($component::class)
        ->call('applyDynamicTableView', 'preset:restock')
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Nope'])
        ->assertNotified(__('filament-dynamic-tables::dynamic-tables.quick_save.preset_view_blocked'));

    expect(UserView::query()->where('name', 'Nope')->exists())->toBeFalse();
});

it('shows shared views to others once their status is high enough', function () {
    $other = User::factory()->create();

    $pending = UserView::query()->create(['user_id' => $other->getKey(), 'table_key' => ListProducts::class, 'name' => 'Pending shared', 'is_public' => true, 'status' => ViewStatus::Pending, 'state' => []]);
    $rejected = UserView::query()->create(['user_id' => $other->getKey(), 'table_key' => ListProducts::class, 'name' => 'Rejected shared', 'is_public' => true, 'status' => ViewStatus::Rejected, 'state' => []]);

    $lenient = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->minimumStatusForDisplay(ViewStatus::Pending);
        }
    };

    $keys = livewire($lenient::class)->instance()->getDynamicTableUserViews()->modelKeys();

    expect($keys)->toContain($pending->getKey())->not->toContain($rejected->getKey());

    $strict = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->minimumStatusForDisplay(ViewStatus::Approved);
        }
    };

    expect(livewire($strict::class)->instance()->getDynamicTableUserViews()->modelKeys())->not->toContain($pending->getKey());
});

it('starts shared views with the initial status and keeps private ones approved', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->initialStatus(ViewStatus::Rejected);
        }
    };

    livewire($component::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Private'])
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Shared', 'is_public' => true]);

    expect(Models::userView()::query()->where('name', 'Private')->first()->status)->toBe(ViewStatus::Approved)
        ->and(Models::userView()::query()->where('name', 'Shared')->first()->status)->toBe(ViewStatus::Rejected);
});

it('stops users from removing global favorites when they are not manageable', function () {
    $other = User::factory()->create();
    $global = UserView::query()->create(['user_id' => $other->getKey(), 'table_key' => ListProducts::class, 'name' => 'Company view', 'is_global_favorite' => true, 'state' => []]);

    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->globalFavorites()->globalUserViewsManageable(false);
        }
    };

    $instance = livewire($component::class)->call('toggleDynamicTableViewFavorite', $global->getKeyString());

    expect($instance->instance()->getDynamicTableViewItem($global->getKeyString())->isFavorite)->toBeTrue();
});

it('stops users from managing preset views when they are not manageable', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->presetViewsManageable(false);
        }
    };

    $instance = livewire($component::class);
    $before = $instance->instance()->getDynamicTableViewItem('preset:active')->isFavorite;

    $instance->call('toggleDynamicTableViewFavorite', 'preset:active');

    expect($instance->instance()->getDynamicTableViewItem('preset:active')->isFavorite)->toBe($before);
});

it('lists favorited preset views before user views', function () {
    $view = UserView::query()->create(['user_id' => $this->user->getKey(), 'table_key' => ListProducts::class, 'name' => 'Mine', 'state' => []]);

    $instance = livewire(ListProducts::class)
        ->call('toggleDynamicTableViewFavorite', $view->getKeyString())
        ->call('reorderDynamicTableFavoriteViews', [$view->getKeyString(), 'preset:active']);

    $keys = $instance->instance()->getDynamicTableFavoriteViewItems()->pluck('key')->all();

    expect(array_search('preset:active', $keys, true))->toBeLessThan(array_search($view->getKeyString(), $keys, true));
});

it('opens a view from the url', function () {
    $view = UserView::query()->create(['user_id' => $this->user->getKey(), 'table_key' => ListProducts::class, 'name' => 'Linked', 'state' => ['sort' => ['price:desc']]]);

    Livewire::withQueryParams(['table_view' => $view->getKeyString()])
        ->test(ListProducts::class)
        ->assertSet('activeDynamicTableView', $view->getKeyString())
        ->assertSet('tableSort', 'price:desc');
});

it('reads per-table switches from the page', function () {
    $component = new class extends ListProducts
    {
        public static function quickSaveIsEnabled(): bool
        {
            return false;
        }

        public static function multiSortIsEnabled(): bool
        {
            return false;
        }
    };

    $config = livewire($component::class)->instance()->getDynamicTablesConfig();

    expect($config->isQuickSaveEnabled())->toBeFalse()
        ->and($config->isMultiSortEnabled())->toBeFalse()
        ->and($config->isViewManagerEnabled())->toBeTrue();
});

it('pins favorite filters', function () {
    $component = new class extends ListProducts
    {
        public function table(Table $table): Table
        {
            return parent::table($table)->filters([
                SelectFilter::make('category')->options(Category::class)->multiple()->favorite()->limitIndicatorLabels(1),
            ]);
        }

        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->quickFilters();
        }
    };

    $instance = livewire($component::class);

    expect($instance->instance()->getPinnedQuickFilters())->toBe(['category']);

    $instance->set('tableFilters.category.values', [Category::Electronics->value, Category::Books->value]);

    $item = collect($instance->instance()->getQuickFilterItems())->firstWhere('name', 'category');

    expect($item['summary'])->toContain('& 1 more');
});

it('resolves icon aliases', function () {
    expect(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::QUICK_SAVE_TRIGGER))->toBe('heroicon-o-plus');

    FilamentIcon::register([DynamicTablesIconAlias::QUICK_SAVE_TRIGGER => 'heroicon-o-bookmark']);

    expect(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::QUICK_SAVE_TRIGGER))->toBe('heroicon-o-bookmark')
        ->and(livewire(ListProducts::class)->instance()->getAction('dynamicTablesSaveView', isMounting: false)->getIcon())->toBe('heroicon-o-bookmark');
});

it('offers every heroicon in the icon picker', function () {
    $config = DynamicTablesPlugin::current();

    expect(count($config->getIcons()))->toBeGreaterThan(500)
        ->and($config->getIcons())->toHaveKeys(['heroicon-o-star', 'heroicon-s-star']);

    $outlineOnly = (clone $config)->quickSaveIncludeSolidIcons(false);

    expect($outlineOnly->getIcons())->not->toHaveKey('heroicon-s-star');
});

it('keeps the advanced tables names for options', function () {
    $plugin = (clone DynamicTablesPlugin::current())
        ->quickSaveInFavoritesBar(false)
        ->quickSaveInTable()
        ->viewManagerInTable(position: 'tables::toolbar.toggle-column-trigger.before')
        ->viewManagerSlideOver()
        ->favoritesBarDefaultIcon('heroicon-o-home')
        ->defaultViewIcon('heroicon-o-bookmark')
        ->presetViewLockIcon()
        ->advancedSearchMaxGroups(3);

    expect($plugin->isQuickSaveInFavoritesBar())->toBeFalse()
        ->and($plugin->isQuickSaveInTable())->toBeTrue()
        ->and($plugin->getViewManagerTablePosition())->toBe('tables::toolbar.toggle-column-trigger.before')
        ->and($plugin->isViewManagerSlideOver())->toBeTrue()
        ->and($plugin->getDefaultViewIcon())->toBe('heroicon-o-home')
        ->and($plugin->getFallbackViewIcon())->toBe('heroicon-o-bookmark')
        ->and($plugin->getPresetViewLockIcon())->toBe('heroicon-o-lock-closed')
        ->and($plugin->getAdvancedSearchMaxGroups())->toBe(3);
});

it('renders the view manager in a custom toolbar position', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->viewManagerInFavoritesBar(false)->viewManagerInTable(position: 'tables::toolbar.search.before');
        }
    };

    livewire($component::class)->assertSeeHtml('fdt-view-manager-dropdown');
});
