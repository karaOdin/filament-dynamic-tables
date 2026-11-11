# Icon Aliases

Every icon of the package is registered as a Filament icon alias. Replace icons across the application in a service provider:

```php
use Filament\Support\Facades\FilamentIcon;
use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias;

public function boot(): void
{
    FilamentIcon::register([
        DynamicTablesIconAlias::QUICK_SAVE_TRIGGER => 'phosphor-floppy-disk',
        DynamicTablesIconAlias::VIEW_MANAGER_TRIGGER => 'phosphor-list-bullets',
        DynamicTablesIconAlias::MULTI_SORT_TRIGGER => 'phosphor-sort-ascending',
    ]);
}
```

## Available aliases

All constants are defined on `KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias`.

### Triggers

| Constant | Icon |
| --- | --- |
| `QUICK_SAVE_TRIGGER` | Quick save button |
| `VIEW_MANAGER_TRIGGER` | View manager button |
| `MULTI_SORT_TRIGGER` | Multi-sort button |
| `SHOW_VIEW_MANAGER` | Link that opens the view manager |
| `FAVORITES_BAR_DEFAULT` | The Default view |

### View actions

| Constant | Icon |
| --- | --- |
| `APPLY_VIEW` | Apply |
| `EDIT_VIEW` | Edit |
| `REPLACE_VIEW` | Replace |
| `DELETE_VIEW` | Delete |
| `ADD_TO_FAVORITES` | Add to favorites |
| `REMOVE_FROM_FAVORITES` | Remove from favorites |
| `SET_DEFAULT_VIEW` | Set as default |
| `REMOVE_DEFAULT_VIEW` | Remove default |
| `VIEW_MANAGER_ACTION_GROUP` | The action menu |
| `REORDER_VIEWS` | Enter reorder mode |
| `REORDER_VIEWS_CONFIRM` | Confirm reordering |
| `DRAG_HANDLE` | Drag handle |

### Indicators

| Constant | Icon |
| --- | --- |
| `DEFAULT_VIEW_INDICATOR` | The user's default view |
| `USER_OWNER_INDICATOR` | A view owned by another user |
| `GLOBAL_VIEW_INDICATOR` | A global favorite |
| `PUBLIC_VIEW_INDICATOR` | A public view |
| `PRESET_LOCKED_INDICATOR` | A preset view, with `presetViewLockIcon()` |

### Sorting

| Constant | Icon |
| --- | --- |
| `SORT_ASCENDING` | Ascending |
| `SORT_DESCENDING` | Descending |

### Filter builder

| Constant | Icon |
| --- | --- |
| `FILTER_BUILDER_DELETE` | Remove a filter or group |
| `FILTER_BUILDER_SEARCH` | Filter picker search |
| `FILTER_BUILDER_EXPAND` | Expand view |

### Advanced search

| Constant | Icon |
| --- | --- |
| `ADVANCED_SEARCH_CONSTRAINT` | Constraint badge |
| `ADVANCED_SEARCH_COLUMN` | Column badge |
| `ADVANCED_SEARCH_EXTRA_COLUMN` | Database column badge |
| `ADVANCED_SEARCH_BOOLEAN_AND` | AND operator |
| `ADVANCED_SEARCH_BOOLEAN_OR` | OR operator |
| `ADVANCED_SEARCH_CLEAR` | Clear the search |

## Resolving an alias

`DynamicTablesIconAlias::resolve()` returns the registered icon for an alias, or the package default:

```php
DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::QUICK_SAVE_TRIGGER); // heroicon-o-plus
```
