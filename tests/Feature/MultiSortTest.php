<?php

use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Brand;
use Workbench\App\Models\Product;

beforeEach(function () {
    actingAsUser();
});

it('sorts by several columns in priority order', function () {
    $a = Product::factory()->create(['category' => Category::Books, 'price' => 30]);
    $b = Product::factory()->create(['category' => Category::Books, 'price' => 10]);
    $c = Product::factory()->create(['category' => Category::Electronics, 'price' => 50]);
    $d = Product::factory()->create(['category' => Category::Electronics, 'price' => 5]);

    livewire(ListProducts::class)
        ->call('addTableMultiSort', 'category')
        ->call('addTableMultiSort', 'price', 'desc')
        ->assertSet('tableMultiSort', ['category:asc', 'price:desc'])
        ->assertSet('tableSort', 'category:asc')
        ->assertCanSeeTableRecords([$a, $b, $c, $d], inOrder: true);
});

it('toggles direction, reorders and removes sorts', function () {
    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['category:asc', 'price:desc', 'name:asc'])
        ->call('toggleTableMultiSortDirection', 1)
        ->assertSet('tableMultiSort', ['category:asc', 'price:asc', 'name:asc'])
        ->call('reorderTableMultiSort', ['name', 'category', 'price'])
        ->assertSet('tableMultiSort', ['name:asc', 'category:asc', 'price:asc'])
        ->assertSet('tableSort', 'name:asc')
        ->call('removeTableMultiSort', 0)
        ->assertSet('tableMultiSort', ['category:asc', 'price:asc'])
        ->call('resetTableMultiSort')
        ->assertSet('tableMultiSort', [])
        ->assertSet('tableSort', null);
});

it('replaces the sort list when a column header is clicked', function () {
    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['category:asc', 'price:desc'])
        ->call('sortTable', 'stock')
        ->assertSet('tableMultiSort', ['stock:asc']);
});

it('keeps secondary sorts when the primary header direction is toggled', function () {
    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['category:asc', 'price:desc'])
        ->call('sortTable', 'category')
        ->assertSet('tableSort', 'category:desc')
        ->assertSet('tableMultiSort', ['category:desc', 'price:desc']);
});

it('ignores columns that are not sortable', function () {
    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['sku:asc', 'nope:desc', 'is_active:asc'])
        ->assertSet('tableMultiSort', ['sku:asc', 'is_active:asc'])
        ->call('addTableMultiSort', 'does_not_exist')
        ->assertSet('tableMultiSort', ['sku:asc', 'is_active:asc']);
});

it('respects the maximum number of sorted columns', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->multiSortMaxColumns(2);
        }
    };

    livewire($component::class)
        ->call('setTableMultiSort', ['name:asc', 'price:asc', 'stock:asc'])
        ->assertSet('tableMultiSort', ['name:asc', 'price:asc'])
        ->call('addTableMultiSort', 'stock')
        ->assertSet('tableMultiSort', ['name:asc', 'price:asc']);
});

it('sorts by a relationship column', function () {
    $zeta = Product::factory()->for(Brand::factory()->state(['name' => 'Zeta']))->create(['price' => 1]);
    $alpha = Product::factory()->for(Brand::factory()->state(['name' => 'Alpha']))->create(['price' => 2]);

    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['brand.name:asc', 'price:asc'])
        ->assertCanSeeTableRecords([$alpha, $zeta], inOrder: true);
});

it('renders the multi-sort dropdown', function () {
    livewire(ListProducts::class)
        ->call('setTableMultiSort', ['category:asc', 'price:desc'])
        ->assertSee('fdt-multi-sort-dropdown', escape: false)
        ->assertSee('fdt-sort-row', escape: false);
});
