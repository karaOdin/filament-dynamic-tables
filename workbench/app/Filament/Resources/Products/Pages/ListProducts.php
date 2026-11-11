<?php

namespace Workbench\App\Filament\Resources\Products\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\DynamicTables;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\ProductResource;

class ListProducts extends ListRecords
{
    use DynamicTables;

    protected static string $resource = ProductResource::class;

    public function getPresetViews(): array
    {
        return [
            'active' => PresetView::make('Active')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
                ->badge(fn (Builder $query): int => $query->count())
                ->favorite(),
            'electronics' => PresetView::make('Electronics')
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->defaultFilters(['category' => ['values' => [Category::Electronics->value]]])
                ->favorite(),
            'expensive' => PresetView::make('Most expensive')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->defaultMultiSort(['price' => 'desc', 'name' => 'asc'])
                ->favorite(),
            'restock' => PresetView::make('Needs restock')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->tooltip('Active products with fewer than 20 units')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)->where('stock', '<', 20))
                ->defaultSort('stock')
                ->visibleColumns(['sku', 'brand.name'])
                ->favorite(),
            'by_brand' => PresetView::make('By brand')
                ->icon('heroicon-o-building-storefront')
                ->defaultGrouping('brand.name'),
        ];
    }
}
