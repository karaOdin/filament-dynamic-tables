# Per-Table Configuration

## Overriding options for one table

Override `configureDynamicTables()` on the table component. It receives a copy of the panel plugin, so changes do not affect other tables.

```php
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;

public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
{
    return $config
        ->favoritesBarTheme(FavoritesBarTheme::Links)
        ->advancedSearchEnabled()
        ->multiSortMaxColumns(2);
}
```

Any plugin method can be used. See [Plugin options](plugin-options.md).

## Feature switches

Features can also be switched off for a table with static methods on the page. Each method returns a boolean.

```php
public static function quickSaveIsEnabled(): bool
{
    return false;
}
```

| Method | Feature |
| --- | --- |
| `userViewsAreEnabled()` | User views |
| `presetViewsAreEnabled()` | Preset views |
| `favoritesBarIsEnabled()` | Favorites bar |
| `hasDefaultView()` | The built-in Default view |
| `quickSaveIsEnabled()` | Quick save |
| `viewManagerIsEnabled()` | View manager |
| `multiSortIsEnabled()` | Multi-sort |
| `advancedSearchIsEnabled()` | Advanced search |
| `quickFiltersAreEnabled()` | Quick filters |
| `tableLoadingOverlayIsEnabled()` | Loading overlay |

## Table key

Override `getDynamicTableKey()` to choose which saved views belong to the table. See [Adding dynamic tables to a table](../get-started/adding-to-tables.md#table-keys).
