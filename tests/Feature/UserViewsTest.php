<?php

use Filament\Facades\Filament;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Models\ManagedView;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

use function Pest\Livewire\livewire;

function makeUserView(User $user, array $attributes = []): UserView
{
    return UserView::query()->create([
        'user_id' => $user->getKey(),
        'table_key' => ListProducts::class,
        'name' => 'Saved view',
        'state' => ['filters' => [], 'search' => '', 'sort' => []],
        ...$attributes,
    ]);
}

it('saves the current table state with quick save', function () {
    $user = actingAsUser();
    Product::factory(3)->create();

    livewire(ListProducts::class)
        ->set('tableSearch', 'lamp')
        ->call('setTableMultiSort', ['price:desc', 'name:asc'])
        ->set('tableFilters.category.values', [Category::Books->value])
        ->callAction('dynamicTablesSaveView', data: [
            'name' => 'Cheap lamps',
            'icon' => 'heroicon-o-star',
            'color' => 'success',
            'is_favorite' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('activeDynamicTableView', 'user:1');

    $view = UserView::query()->sole();

    expect($view)
        ->name->toBe('Cheap lamps')
        ->icon->toBe('heroicon-o-star')
        ->color->toBe('success')
        ->user_id->toBe($user->getKey())
        ->table_key->toBe(ListProducts::class)
        ->status->toBe(ViewStatus::Approved)
        ->and($view->state['search'])->toBe('lamp')
        ->and($view->state['sort'])->toBe(['price:desc', 'name:asc'])
        ->and($view->state['filters']['category']['values'])->toBe([Category::Books->value]);

    expect(ManagedView::query()->sole())
        ->view_key->toBe((string) $view->getKey())
        ->is_favorite->toBeTrue();
});

it('requires a name', function () {
    actingAsUser();

    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);
});

it('rejects icons and colors that are not allowed', function () {
    actingAsUser();

    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: [
            'name' => 'Hacked',
            'icon' => 'heroicon-o-not-a-real-icon',
            'color' => 'javascript:alert(1)',
        ])
        ->assertHasActionErrors(['icon', 'color']);

    expect(UserView::query()->count())->toBe(0);
});

it('cannot create views by calling the method from the browser', function () {
    actingAsUser();

    livewire(ListProducts::class)->call('createDynamicTableUserView', ['name' => 'x']);
})->throws(Exception::class, 'Public method [createDynamicTableUserView] not found');

it('sanitizes icons and colors even when called directly', function () {
    actingAsUser();

    $component = livewire(ListProducts::class);

    (fn () => $this->createDynamicTableUserView([
        'name' => 'Direct',
        'icon' => 'heroicon-o-not-a-real-icon',
        'color' => 'javascript:alert(1)',
    ]))->call($component->instance());

    expect(UserView::query()->sole())
        ->icon->toBeNull()
        ->color->toBeNull();
});

it('restores a saved view', function () {
    $user = actingAsUser();
    $books = Product::factory(2)->create(['category' => Category::Books]);
    $toys = Product::factory(2)->create(['category' => Category::Toys]);

    $view = makeUserView($user, [
        'state' => [
            'filters' => ['category' => ['values' => [Category::Toys->value]]],
            'search' => '',
            'sort' => ['stock:desc'],
            'grouping' => null,
            'columns' => [],
        ],
    ]);

    livewire(ListProducts::class)
        ->call('applyDynamicTableView', $view->getKeyString())
        ->assertSet('activeDynamicTableView', $view->getKeyString())
        ->assertSet('tableSort', 'stock:desc')
        ->assertCanSeeTableRecords($toys)
        ->assertCanNotSeeTableRecords($books);
});

it('shows favorited user views in the favorites bar', function () {
    $user = actingAsUser();
    $view = makeUserView($user, ['name' => 'My shiny view']);

    $component = livewire(ListProducts::class);

    expect(favoritesBarHtml($component))->not->toContain('My shiny view');

    $component->call('toggleDynamicTableViewFavorite', $view->getKeyString());

    expect(favoritesBarHtml($component))->toContain('My shiny view');
});

it('lets users remove preset views from their favorites', function () {
    actingAsUser();

    $component = livewire(ListProducts::class);

    expect(favoritesBarHtml($component))->toContain('Needs restock');

    $component->call('toggleDynamicTableViewFavorite', 'preset:restock');

    expect(favoritesBarHtml($component))->not->toContain('Needs restock');

    $component->call('toggleDynamicTableViewFavorite', 'preset:by_brand');

    expect(favoritesBarHtml($component))->toContain('By brand');
});

it('reorders favorites per user', function () {
    actingAsUser();

    $component = livewire(ListProducts::class)
        ->call('reorderDynamicTableFavoriteViews', ['preset:restock', 'preset:active', 'preset:expensive', 'preset:electronics']);

    expect($component->instance()->getDynamicTableFavoriteViewItems()->pluck('key')->all())
        ->toBe(['preset:restock', 'preset:active', 'preset:expensive', 'preset:electronics']);
});

it('updates a view with the edit action', function () {
    $user = actingAsUser();
    $view = makeUserView($user);

    livewire(ListProducts::class)
        ->callAction('dynamicTablesEditView', data: ['name' => 'Renamed', 'is_favorite' => true], arguments: ['view' => $view->getKeyString()])
        ->assertHasNoActionErrors();

    expect($view->refresh()->name)->toBe('Renamed');
});

it('replaces a view with the current table state', function () {
    $user = actingAsUser();
    $view = makeUserView($user);

    livewire(ListProducts::class)
        ->set('tableSearch', 'replaced')
        ->callAction('dynamicTablesReplaceView', arguments: ['view' => $view->getKeyString()]);

    expect($view->refresh()->state['search'])->toBe('replaced');
});

it('deletes a view and its favorites', function () {
    $user = actingAsUser();
    $view = makeUserView($user);

    livewire(ListProducts::class)
        ->call('toggleDynamicTableViewFavorite', $view->getKeyString())
        ->callAction('dynamicTablesDeleteView', arguments: ['view' => $view->getKeyString()]);

    expect(UserView::query()->count())->toBe(0)
        ->and(ManagedView::query()->count())->toBe(0);
});

it('does not let users change views they do not own', function () {
    $owner = User::factory()->create();
    $view = makeUserView($owner, ['is_public' => true, 'name' => 'Owned']);

    actingAsUser();

    livewire(ListProducts::class)
        ->call('deleteDynamicTableUserView', $view->getKeyString())
        ->call('replaceDynamicTableUserView', $view->getKeyString())
        ->assertActionHidden('dynamicTablesEditView', ['view' => $view->getKeyString()]);

    expect($view->refresh())->exists->toBeTrue()
        ->name->toBe('Owned');
});

it('shows public views of other users but not private ones', function () {
    $owner = User::factory()->create();
    $public = makeUserView($owner, ['is_public' => true, 'name' => 'Public one']);
    $private = makeUserView($owner, ['name' => 'Private one']);

    actingAsUser();

    $keys = livewire(ListProducts::class)->instance()->getDynamicTableViewItems()->pluck('key');

    expect($keys)->toContain($public->getKeyString())
        ->not->toContain($private->getKeyString());
});

it('puts approved global favorites in everyone\'s favorites bar', function () {
    $owner = User::factory()->create();
    makeUserView($owner, ['is_global_favorite' => true, 'name' => 'Company wide']);
    makeUserView($owner, ['is_global_favorite' => true, 'status' => ViewStatus::Pending, 'name' => 'Not yet']);

    actingAsUser();

    livewire(ListProducts::class)
        ->assertSee('Company wide')
        ->assertDontSee('Not yet');
});

it('marks public views as pending when approval is required', function () {
    actingAsUser();

    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Share me', 'is_public' => true]);

    expect(UserView::query()->sole()->status)->toBe(ViewStatus::Pending);
});

it('auto approves views created by users who can approve', function () {
    actingAsAdmin();

    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Admin view', 'is_public' => true, 'is_global_favorite' => true]);

    expect(UserView::query()->sole())
        ->status->toBe(ViewStatus::Approved)
        ->is_global_favorite->toBeTrue();
});

it('does not let regular users create global favorites', function () {
    actingAsUser();

    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Sneaky', 'is_global_favorite' => true]);

    expect(UserView::query()->sole()->is_global_favorite)->toBeFalse();
});

it('applies the user managed default view on load', function () {
    $user = actingAsUser();
    $active = Product::factory(2)->create(['is_active' => true]);
    $inactive = Product::factory(2)->create(['is_active' => false]);

    livewire(ListProducts::class)->call('setDynamicTableDefaultView', 'preset:active');

    expect(ManagedView::query()->where('is_default', true)->sole()->view_key)->toBe('active');

    livewire(ListProducts::class)
        ->assertSet('activeDynamicTableView', 'preset:active')
        ->assertCanSeeTableRecords($active)
        ->assertCanNotSeeTableRecords($inactive);
});

it('keeps only one default view per user', function () {
    actingAsUser();

    livewire(ListProducts::class)
        ->call('setDynamicTableDefaultView', 'preset:active')
        ->call('toggleDynamicTableDefaultView', 'preset:expensive');

    expect(ManagedView::query()->where('is_default', true)->pluck('view_key')->all())->toBe(['expensive']);
});

it('keeps views separate per table', function () {
    $user = actingAsUser();
    makeUserView($user, ['table_key' => 'Some\\Other\\Table', 'name' => 'Elsewhere', 'is_global_favorite' => true]);

    livewire(ListProducts::class)->assertDontSee('Elsewhere');
});

it('keeps views separate per tenant when tenancy is enabled', function () {
    $user = actingAsUser();

    makeUserView($user, ['tenant_id' => '1', 'name' => 'Tenant one', 'is_global_favorite' => true]);
    makeUserView($user, ['tenant_id' => '2', 'name' => 'Tenant two', 'is_global_favorite' => true]);

    Filament::getCurrentPanel()->getPlugin('filament-dynamic-tables')
        ->resolveTenantUsing(fn () => '2');

    livewire(ListProducts::class)
        ->assertSee('Tenant two')
        ->assertDontSee('Tenant one');
});
