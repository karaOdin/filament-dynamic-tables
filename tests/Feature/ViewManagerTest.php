<?php

use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = actingAsUser();
});

it('opens the view manager with every section', function () {
    $other = User::factory()->create(['name' => 'Jordan Lee']);

    UserView::query()->create(['user_id' => $this->user->getKey(), 'table_key' => ListProducts::class, 'name' => 'Mine', 'state' => []]);
    UserView::query()->create(['user_id' => $other->getKey(), 'table_key' => ListProducts::class, 'name' => 'Shared', 'is_public' => true, 'state' => []]);

    // The view manager is a dropdown by default, rendered with the page.
    livewire(ListProducts::class)
        ->assertSee(['My views', 'Preset views', 'Public views', 'Mine', 'Shared', 'by Jordan Lee', 'Most expensive']);
});

it('opens the view manager as a slide-over', function () {
    $other = User::factory()->create(['name' => 'Jordan Lee']);

    UserView::query()->create(['user_id' => $other->getKey(), 'table_key' => ListProducts::class, 'name' => 'Shared', 'is_public' => true, 'state' => []]);

    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->viewManagerSlideOver();
        }
    };

    livewire($component::class)
        ->mountAction('dynamicTablesViewManager')
        ->assertActionMounted('dynamicTablesViewManager')
        ->assertMountedActionModalSee(['Public views', 'Shared', 'by Jordan Lee']);
});

it('applies a view from the view manager and closes it', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->viewManagerSlideOver();
        }
    };

    livewire($component::class)
        ->mountAction('dynamicTablesViewManager')
        ->call('applyDynamicTableView', 'preset:active', true)
        ->assertSet('activeDynamicTableView', 'preset:active')
        ->assertActionNotMounted('dynamicTablesViewManager');
});

it('renders every favorites bar theme', function (FavoritesBarTheme $theme) {
    $component = new class extends ListProducts
    {
        public static FavoritesBarTheme $theme;

        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->favoritesBarTheme(self::$theme);
        }
    };

    $component::$theme = $theme;

    livewire($component::class)
        ->assertSuccessful()
        ->assertSee('fdt-theme-' . $theme->value, escape: false)
        ->assertSee('Most expensive');
})->with(FavoritesBarTheme::cases());

it('moves quick save and the view manager to the toolbar', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->quickSaveInToolbar()->viewManagerInToolbar();
        }
    };

    $html = livewire($component::class)->html();

    expect($html)->toContain('fdt-toolbar-actions')
        ->and(substr_count($html, 'fdt-quick-save-trigger'))->toBe(1)
        ->and(str($html)->after('fdt-toolbar-actions')->contains('fdt-quick-save-trigger'))->toBeTrue();
});

it('hides the favorites bar when disabled and keeps the controls in the toolbar', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->favoritesBar(false);
        }
    };

    livewire($component::class)
        ->assertDontSee('fdt-favorites-bar', escape: false)
        ->assertSee('fdt-view-manager-trigger', escape: false);
});

it('does not show save controls to guests', function () {
    auth()->logout();

    expect(livewire(ListProducts::class)->instance()->dynamicTablesSaveViewAction()->isVisible())->toBeFalse();
});

it('restores the active view from the session', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->persistActiveViewInSession();
        }
    };

    livewire($component::class)->call('applyDynamicTableView', 'preset:restock');

    livewire($component::class)
        ->assertSet('activeDynamicTableView', 'preset:restock')
        ->assertSet('tableSort', 'stock:asc');
});

it('never leaks raw blade directives into the rendered html', function () {
    UserView::query()->create(['user_id' => $this->user->getKey(), 'table_key' => ListProducts::class, 'name' => 'Mine', 'is_public' => true, 'state' => []]);

    livewire(ListProducts::class)
        ->set('tableFilters.is_active.value', '1')
        ->assertDontSee('@js(', escape: false)
        ->mountAction('dynamicTablesViewManager')
        ->assertMountedActionModalDontSee('@js(', escape: false);
});

it('opens edit, replace and delete on top of the view manager', function () {
    $view = UserView::query()->create(['user_id' => $this->user->getKey(), 'table_key' => ListProducts::class, 'name' => 'Nested', 'state' => []]);

    livewire(ListProducts::class)
        ->mountAction('dynamicTablesViewManager')
        ->call('mountAction', 'dynamicTablesEditView', ['view' => $view->getKeyString()])
        ->assertSet('mountedActions.1.name', 'dynamicTablesEditView')
        ->set('mountedActions.1.data.name', 'Renamed from the manager')
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.name', 'dynamicTablesViewManager');

    expect($view->refresh()->name)->toBe('Renamed from the manager');
});
