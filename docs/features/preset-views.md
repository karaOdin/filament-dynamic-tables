# Preset Views

Preset views are defined in code by the developer. They can change the table query, set default filters, sorting, grouping and columns, and show a badge.

## Defining preset views

Return preset views from `getPresetViews()` on the table component. The array key identifies the view and must stay stable, because users' favorites and defaults reference it.

```php
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Components\PresetView;

public function getPresetViews(): array
{
    return [
        'active' => PresetView::make('Active')
            ->icon('heroicon-o-check-circle')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
            ->badge(fn (Builder $query) => $query->count())
            ->favorite()
            ->default(),

        'electronics' => PresetView::make('Electronics')
            ->color('info')
            ->defaultFilters(['category' => ['values' => ['electronics']]])
            ->favorite(),

        'expensive' => PresetView::make('Most expensive')
            ->defaultSort(['price' => 'desc', 'name' => 'asc']),

        'restock' => PresetView::make('Needs restock')
            ->tooltip('Active products with fewer than 20 units')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('stock', '<', 20))
            ->defaultSort('stock')
            ->visibleColumns(['sku', 'brand.name'])
            ->visible(fn () => auth()->user()->can('manage-stock')),

        'by_brand' => PresetView::make('By brand')
            ->defaultGrouping('brand.name', 'asc'),
    ];
}
```

## Methods

### Presentation

| Method | Description |
| --- | --- |
| `label()` | The view name. Defaults to the value passed to `make()`. |
| `icon()`, `activeIcon()` | The icon, and the icon shown while the view is active. |
| `color()` | The view color. |
| `tooltip()` | A tooltip in the favorites bar. |
| `badge()`, `badgeColor()` | A static badge, or a closure. The closure's `$query` is the table query with the preset's query modification applied. |
| `indicator()` | A note in the save form summary that describes the preset's query. |

### Table state

| Method | Description |
| --- | --- |
| `modifyQueryUsing()` or `query()` | Changes the Eloquent query while the view is active. The change is not shown as a filter. Accepts a closure or a `Builder`. |
| `defaultFilters()` | Filter form state, keyed by filter name. Filter indicators are shown. |
| `defaultSort($column, $direction)` | A single sort, or an array of `column => direction` pairs for several sorts. |
| `defaultMultiSort([...])` | Several sorts, in priority order. |
| `defaultGrouping($group, $direction)` | The grouping and its direction. |
| `defaultSearch()` | A search term, or [advanced search](advanced-search.md) groups. |
| `defaultColumns([...])` | Shows these columns in this order. Unlisted toggleable columns are hidden. |
| `visibleColumns([...])` or `toggledColumns([...])` | Shows only these toggleable columns. |
| `columnOrder([...])` | The column order. Requires `->reorderableColumns()` on the table. |
| `defaultFavoriteFilters([...])` | The [quick filter](quick-filters.md) chips shown while the view is active. |

### Behavior

| Method | Description |
| --- | --- |
| `favorite()` | Shows the view in the favorites bar. Users can remove it unless presets are not manageable. |
| `default()` | Applies the view when the table opens without other state. |
| `visible()`, `hidden()` | Hides the view from every list. |
| `preserveFilters()`, `preserveSearch()`, `preserveSort()`, `preserveGrouping()`, `preserveColumns()` | Keeps the user's current state for that part of the table when the view is applied. `preserveToggledColumns()` is an alias of `preserveColumns()`. |
| `preserveAll()` | Keeps all of the above. |

Closures can inject `$livewire`, `$table` and `$key`.

## The Default view

The favorites bar starts with a built-in **Default** view that resets the table to its initial state.

- Change its label with `defaultViewLabel()` and its icon with `favoritesBarDefaultIcon()`.
- Remove it with `favoritesBarDefaultView(false)`, or define `hasDefaultView()` returning `false` on the page.
- Customize it with a preset keyed `default`. Its label, icon, query and defaults are used for the Default view.

```php
public function getPresetViews(): array
{
    return [
        'default' => PresetView::make('All active')
            ->icon('heroicon-o-check')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
    ];
}
```

## Plugin options

| Method | Default | Description |
| --- | --- | --- |
| `presetViews()` | `true` | Enables preset views. |
| `presetViewsManageable()` | `true` | Allows users to reorder presets and change which presets are favorites. |
| `newPresetViewSortPosition()` | `before` | Places new favorite presets `before` or `after` the user's views. |
| `createUsingPresetView()` | `true` | When `false`, users cannot save a view while a preset with a query modification is active. |
| `presetViewLockIcon()` | `false` | Marks preset views with a lock icon. Pass an icon name to change it. |
| `favoritesBarDivider()` | `false` | Separates preset views from user views in the favorites bar. |

Disable preset views for a single table with a static method on the page:

```php
public static function presetViewsAreEnabled(): bool
{
    return false;
}
```
