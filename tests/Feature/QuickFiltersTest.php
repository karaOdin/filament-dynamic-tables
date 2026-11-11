<?php

use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Models\Preference;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Product;

beforeEach(function () {
    actingAsUser();
    Product::factory(2)->create();
});

it('renders active filters as quick filter chips', function () {
    livewire(ListProducts::class)
        ->set('tableFilters.category.values', [Category::Books->value])
        ->assertSee('fdt-quick-filter-active', escape: false)
        ->assertSee('fdt-quick-filter-dropdown', escape: false);
});

it('pins and unpins filters per user', function () {
    $component = livewire(ListProducts::class)
        ->assertDontSee('fdt-quick-filter-pinned', escape: false)
        ->call('toggleQuickFilterPin', 'low_stock')
        ->assertSee('fdt-quick-filter-pinned', escape: false);

    expect(Preference::query()->sole()->settings['pinned_filters'])->toBe(['low_stock']);

    $component->call('toggleQuickFilterPin', 'low_stock');

    expect(Preference::query()->sole()->settings['pinned_filters'])->toBe([]);
});

it('ignores unknown filters when pinning', function () {
    livewire(ListProducts::class)->call('toggleQuickFilterPin', 'not_a_filter');

    expect(Preference::query()->count())->toBe(0);
});

it('uses developer pinned filters by default', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->pinnedFilters(['is_active']);
        }
    };

    expect(livewire($component::class)->instance()->getPinnedQuickFilters())->toBe(['is_active']);
});

it('falls back to the default indicators when disabled', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->quickFilters(false);
        }
    };

    livewire($component::class)
        ->set('tableFilters.category.values', [Category::Books->value])
        ->assertDontSee('fdt-quick-filter', escape: false)
        ->assertSee('fi-ta-filter-indicators', escape: false);
});
