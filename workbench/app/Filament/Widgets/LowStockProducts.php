<?php

namespace Workbench\App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\DynamicTables;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use Workbench\App\Models\Product;

class LowStockProducts extends TableWidget
{
    use DynamicTables;

    protected int | string | array $columnSpan = 'full';

    public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
    {
        return $config
            ->favoritesBarTheme(FavoritesBarTheme::Tabs)
            ->advancedSearch(false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock')
            ->query(Product::query()->where('stock', '<', 50))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('stock')->sortable(),
                TextColumn::make('price')->money('usd')->sortable(),
            ])
            ->defaultPaginationPageOption(5);
    }

    public function getPresetViews(): array
    {
        return [
            'empty' => PresetView::make('Out of stock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stock', 0))
                ->favorite(),
            'cheap' => PresetView::make('Under $50')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('price', '<', 50))
                ->favorite(),
        ];
    }
}
