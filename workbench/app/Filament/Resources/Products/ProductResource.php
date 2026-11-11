<?php

namespace Workbench\App\Filament\Resources\Products;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\AdvancedFilter;
use KaraOdin\FilamentDynamicTables\Filters\TextFilter;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Product;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('sku')->required(),
            Select::make('category')->options(Category::class)->required(),
            Select::make('brand_id')->relationship('brand', 'name'),
            TextInput::make('price')->numeric()->required(),
            TextInput::make('stock')->numeric()->required(),
            Toggle::make('is_active'),
            DatePicker::make('released_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('sku')->label('SKU')->searchable()->sortable()->toggleable(),
                TextColumn::make('brand.name')->label('Brand')->searchable()->sortable()->toggleable(),
                TextColumn::make('category')->badge()->sortable()->toggleable(),
                TextColumn::make('price')->money('usd')->searchable()->sortable(),
                TextColumn::make('stock')->numeric()->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('released_at')->date()->searchable()->sortable()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(Category::class)->multiple(),
                SelectFilter::make('brand')->relationship('brand', 'name')->preload(),
                TernaryFilter::make('is_active')->label('Active'),
                Filter::make('low_stock')
                    ->label('Low stock')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('stock', '<', 20)),
                AdvancedFilter::make()
                    ->filters([
                        TextFilter::make('brand.name')->relationship('brand', 'name')->multiple()->preload(),
                        Filter::make('discontinued')
                            ->label('Discontinued')
                            ->toggle()
                            ->query(fn (Builder $query): Builder => $query->where('is_active', false)->where('stock', 0)),
                    ])
                    ->icons([
                        'name' => 'heroicon-o-tag',
                        'category' => 'heroicon-o-squares-2x2',
                        'price' => 'heroicon-o-currency-dollar',
                        'stock' => 'heroicon-o-archive-box',
                        'released_at' => 'heroicon-o-calendar',
                        'created_at' => 'heroicon-o-clock',
                        'is_active' => 'heroicon-o-check-circle',
                        'brand.name' => 'heroicon-o-building-storefront',
                    ])
                    ->filterPickerSearch()
                    ->filterPickerColumns(2)
                    ->defaultFilters([['category']]),
            ])
            ->groups([
                Group::make('category'),
                Group::make('brand.name')->label('Brand'),
            ])
            ->reorderableColumns()
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }
}
