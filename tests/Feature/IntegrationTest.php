<?php

use Filament\Facades\Filament;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource\Pages\ListUserViews;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Filament\Widgets\LowStockProducts;
use Workbench\App\Livewire\ProductsTable;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

use function Pest\Livewire\livewire;

it('reads options from the plugin, then config', function () {
    $plugin = DynamicTablesPlugin::get();

    expect($plugin->isAdvancedSearchEnabled())->toBeTrue()
        ->and($plugin->getFavoritesBarTheme())->toBe(FavoritesBarTheme::Github)
        ->and($plugin->getMultiSortMaxColumns())->toBe(5);

    config(['filament-dynamic-tables.multi_sort.max_columns' => 3]);

    expect($plugin->getMultiSortMaxColumns())->toBe(3);
});

it('uses a config backed plugin outside of panels', function () {
    Filament::setCurrentPanel(null);

    expect(DynamicTablesPlugin::current())->toBe(app('filament-dynamic-tables.fallback-plugin'))
        ->and(DynamicTablesPlugin::current()->isAdvancedSearchEnabled())->toBeFalse();
});

it('lets one table override options without touching the panel plugin', function () {
    actingAsUser();

    $widget = livewire(LowStockProducts::class)->instance();

    expect($widget->getDynamicTablesConfig()->getFavoritesBarTheme())->toBe(FavoritesBarTheme::Tabs)
        ->and(DynamicTablesPlugin::get()->getFavoritesBarTheme())->toBe(FavoritesBarTheme::Github);
});

it('works on table widgets', function () {
    actingAsUser();
    $empty = Product::factory()->create(['stock' => 0]);
    $some = Product::factory()->create(['stock' => 10]);

    livewire(LowStockProducts::class)
        ->assertSee('Out of stock')
        ->call('applyDynamicTableView', 'preset:empty')
        ->assertCanSeeTableRecords([$empty])
        ->assertCanNotSeeTableRecords([$some]);
});

it('works on standalone table builder components', function () {
    actingAsUser();
    $active = Product::factory()->create(['is_active' => true, 'price' => 1]);
    $inactive = Product::factory()->create(['is_active' => false, 'price' => 2]);

    livewire(ProductsTable::class)
        ->assertSee('fdt-favorites-bar', escape: false)
        ->call('applyDynamicTableView', 'preset:active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->call('applyDynamicTableView', 'default')
        ->call('setTableMultiSort', ['price:desc', 'name:asc'])
        ->assertCanSeeTableRecords([$inactive, $active], inOrder: true)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Standalone view'])
        ->assertHasNoActionErrors();

    expect(UserView::query()->sole()->table_key)->toBe(ProductsTable::class);
});

it('lists every view in the resource and approves pending ones', function () {
    $admin = actingAsAdmin();
    $author = User::factory()->create();

    $pending = UserView::query()->create([
        'user_id' => $author->getKey(),
        'table_key' => ListProducts::class,
        'name' => 'Waiting',
        'is_public' => true,
        'status' => ViewStatus::Pending,
        'state' => [],
    ]);

    expect(UserViewResource::getNavigationBadge())->toBe('1');

    livewire(ListUserViews::class)
        ->assertCanSeeTableRecords([$pending])
        ->callTableAction('approve', $pending);

    expect($pending->refresh()->status)->toBe(ViewStatus::Approved);
});

it('does not let regular users approve views', function () {
    actingAsUser();
    $author = User::factory()->create();

    $pending = UserView::query()->create([
        'user_id' => $author->getKey(),
        'table_key' => ListProducts::class,
        'name' => 'Waiting',
        'is_public' => true,
        'status' => ViewStatus::Pending,
        'state' => [],
    ]);

    livewire(ListUserViews::class)->assertTableActionHidden('approve', $pending);
});

it('restricts the resource with a callback', function () {
    actingAsUser();

    DynamicTablesPlugin::get()->canAccessUserViewsResourceUsing(fn (User $user): bool => $user->is_admin);

    expect(UserViewResource::canAccess())->toBeFalse();
});

it('registers the resource on the panel', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(UserViewResource::class);
});

it('renders the list page over http', function () {
    actingAsUser();
    Product::factory(3)->create();

    $this->get('/admin/products')
        ->assertOk()
        ->assertSee('fdt-favorites-bar', escape: false)
        ->assertSee('filament-dynamic-tables.css', escape: false);
});
