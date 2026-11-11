# Quick Filters

Quick filters turn active filter indicators into clickable chips above the table. Clicking a chip opens that filter's fields inline, so users change a filter without opening the filters panel. With deferred filters, an **Apply** button applies the change.

Quick filters are disabled by default:

```php
DynamicTablesPlugin::make()->quickFiltersEnabled()
```

## Pinned filters

The star on a chip pins the filter. A pinned filter's chip stays visible when the filter is inactive. Pins are saved per user and per table.

```php
DynamicTablesPlugin::make()
    ->quickFiltersEnabled()
    ->pinnedFilters(['status', 'is_active']) // pinned for everyone until a user changes them
    ->quickFilterPinning(false)              // remove the star
```

## Filter options

The package adds three methods to every Filament filter:

```php
SelectFilter::make('status')
    ->multiple()
    ->favorite()               // always shown as a chip, like a pin
    ->limitIndicatorLabels(3); // "Status: New, Processing, Shipped & 2 more"

Filter::make('created_at')
    ->schema([...])
    ->indicateUsing(...)
    ->multipleIndicators();    // one chip per indicator
```

| Method | Description |
| --- | --- |
| `favorite()` | Shows the filter as a chip even when it is inactive. |
| `limitIndicatorLabels(int $limit)` | Limits the number of values listed in the chip. |
| `multipleIndicators(bool $condition = true)` | Shows one chip per indicator. Pass `false` to merge them into one chip. |

Each rule of the [advanced filter builder](advanced-filter-builder.md) gets its own chip.

## Preset views

Preset views can set the chips shown while they are active:

```php
PresetView::make('Open orders')->defaultFavoriteFilters(['status', 'created_at'])
```

## Disabling quick filters for a table

```php
public static function quickFiltersAreEnabled(): bool
{
    return false;
}
```
