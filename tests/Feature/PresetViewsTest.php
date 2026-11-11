<?php

use KaraOdin\FilamentDynamicTables\Components\PresetView;
use Livewire\Livewire;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Product;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsUser();
});

it('renders the favorites bar with favorite presets only', function () {
    Product::factory(3)->create();

    $component = livewire(ListProducts::class)
        ->assertSuccessful()
        ->assertSee('fdt-favorites-bar', escape: false);

    expect(favoritesBarHtml($component))
        ->toContain('Most expensive')
        ->toContain('Needs restock')
        ->not->toContain('By brand');
});

it('applies a preset query modification', function () {
    $active = Product::factory(3)->create(['is_active' => true]);
    $inactive = Product::factory(2)->create(['is_active' => false]);

    livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:active')
        ->assertSet('activeDynamicTableView', 'preset:active')
        ->assertCanSeeTableRecords($active)
        ->assertCanNotSeeTableRecords($inactive);
});

it('applies preset default filters', function () {
    $electronics = Product::factory(2)->create(['category' => Category::Electronics]);
    $books = Product::factory(2)->create(['category' => Category::Books]);

    livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:electronics')
        ->assertSet('tableFilters.category.values', [Category::Electronics->value])
        ->assertCanSeeTableRecords($electronics)
        ->assertCanNotSeeTableRecords($books);
});

it('applies a preset multi-sort', function () {
    $cheap = Product::factory()->create(['price' => 10, 'name' => 'B']);
    $mid = Product::factory()->create(['price' => 500, 'name' => 'B']);
    $midA = Product::factory()->create(['price' => 500, 'name' => 'A']);
    $top = Product::factory()->create(['price' => 900, 'name' => 'Z']);

    livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:expensive')
        ->assertSet('tableMultiSort', ['price:desc', 'name:asc'])
        ->assertSet('tableSort', 'price:desc')
        ->assertCanSeeTableRecords([$top, $midA, $mid, $cheap], inOrder: true);
});

it('applies preset grouping and visible columns', function () {
    Product::factory(2)->create();

    $component = livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:by_brand')
        ->assertSet('tableGrouping', 'brand.name:asc');

    $component
        ->call('applyDynamicTableView', 'preset:restock')
        ->assertSet('tableGrouping', null);

    expect($component->instance()->isTableColumnToggledHidden('sku'))->toBeFalse()
        ->and($component->instance()->isTableColumnToggledHidden('brand.name'))->toBeFalse()
        ->and($component->instance()->isTableColumnToggledHidden('category'))->toBeTrue()
        ->and($component->instance()->isTableColumnToggledHidden('name'))->toBeFalse();
});

it('resets to the default view', function () {
    $active = Product::factory(2)->create(['is_active' => true]);
    $inactive = Product::factory(2)->create(['is_active' => false]);

    livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:active')
        ->assertCanNotSeeTableRecords($inactive)
        ->call('applyDynamicTableView', 'default')
        ->assertSet('activeDynamicTableView', 'default')
        ->assertCanSeeTableRecords([...$active, ...$inactive]);
});

it('evaluates preset badges against the preset query', function () {
    Product::factory(3)->create(['is_active' => true]);
    Product::factory(2)->create(['is_active' => false]);

    $component = livewire(ListProducts::class);
    $item = $component->instance()->getDynamicTableViewItem('preset:active');

    expect($item->badge)->toBe(3);
});

it('applies a preset marked as default on first load', function () {
    $active = Product::factory(2)->create(['is_active' => true]);
    $inactive = Product::factory(2)->create(['is_active' => false]);

    $component = new class extends ListProducts
    {
        public function getPresetViews(): array
        {
            return [
                'active' => PresetView::make('Active')
                    ->modifyQueryUsing(fn ($query) => $query->where('is_active', true))
                    ->default(),
            ];
        }
    };

    livewire($component::class)
        ->assertSet('activeDynamicTableView', 'preset:active')
        ->assertCanSeeTableRecords($active)
        ->assertCanNotSeeTableRecords($inactive);
});

it('does not apply the default preset when the url has table state', function () {
    Product::factory(2)->create(['is_active' => false]);

    $component = new class extends ListProducts
    {
        public function getPresetViews(): array
        {
            return [
                'active' => PresetView::make('Active')
                    ->modifyQueryUsing(fn ($query) => $query->where('is_active', true))
                    ->default(),
            ];
        }
    };

    Livewire::withQueryParams(['search' => 'x'])
        ->test($component::class)
        ->assertSet('activeDynamicTableView', null)
        ->assertSet('tableSearch', 'x');
});

it('marks the active view as modified after a change', function () {
    Product::factory(2)->create();

    $component = livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:active');

    expect($component->instance()->isActiveDynamicTableViewModified())->toBeFalse();

    $component->set('tableSearch', 'something');

    expect($component->instance()->isActiveDynamicTableViewModified())->toBeTrue();
});

it('hides presets that are not visible', function () {
    $component = new class extends ListProducts
    {
        public function getPresetViews(): array
        {
            return [
                'shown' => PresetView::make('Shown')->favorite(),
                'hidden' => PresetView::make('Secret')->favorite()->hidden(),
            ];
        }
    };

    livewire($component::class)
        ->assertSee('Shown')
        ->assertDontSee('Secret');
});
