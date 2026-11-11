# Loading Overlay

The loading overlay shows a skeleton over the table while it loads, for example after applying a view or changing a filter.

The overlay is disabled by default:

```php
DynamicTablesPlugin::make()
    ->tableLoadingOverlay()
    ->loadingOverlaySkeletonRows(8)
```

| Method | Default | Description |
| --- | --- | --- |
| `tableLoadingOverlay()` | `false` | Enables the overlay. |
| `loadingOverlaySkeletonRows()` | `5` | The number of skeleton rows. |

Columns that use `->extraCellAttributes()` should pass `merge: true`, so the overlay's attributes are kept.

Disable the overlay for a single table:

```php
public static function tableLoadingOverlayIsEnabled(): bool
{
    return false;
}
```
