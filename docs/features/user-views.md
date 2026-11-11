# User Views

A user view is a named snapshot of a table's state, saved by a user. Users create views with [quick save](quick-save.md) and manage them in the [view manager](view-manager.md).

## Saved state

A user view stores:

- active filters, including deferred filters and the [advanced filter builder](advanced-filter-builder.md)
- the global search and column searches
- every sorted column, in priority order
- the active grouping and its direction
- toggled columns and, when the table uses `->reorderableColumns()`, the column order
- the number of records per page
- the [advanced search](advanced-search.md) groups
- the preset view the user started from, so the preset's query modification still applies

Each view also has a name, an optional icon and color, sharing flags, an approval status, and the panel and URL it was created on. The URL is used by the **Open** action of the [user views resource](user-views-resource.md).

## Applying a view

Clicking a view in the favorites bar or the view manager restores its state. A view can also be applied from the URL with the `table_view` query parameter:

```
/admin/products?table_view=user:12
/admin/products?table_view=preset:active
```

## Modified views

After a view is applied, any change to the table marks the view as modified, shown as a dot next to its name. A **Save changes** link then updates the view in place. Unused default rules of the filter builder are ignored when comparing state, so opening the builder alone does not mark a view as modified.

Disable the indicator with:

```php
DynamicTablesPlugin::make()->modifiedIndicator(false)
```

## Persisting the active view

By default the active view is reset when the page is reloaded without table state. Keep the active view in the session with:

```php
DynamicTablesPlugin::make()->persistActiveViewInSession()
```

## Options

| Method | Default | Description |
| --- | --- | --- |
| `userViewsEnabled()` | `true` | Allows users to save views. |
| `quickSaveIconSelect()` | `true` | Shows the icon picker in the save form. |
| `quickSaveIncludeOutlineIcons()` | `true` | Includes outline Heroicons in the picker. |
| `quickSaveIncludeSolidIcons()` | `true` | Includes solid Heroicons in the picker. |
| `icons()` | every Heroicon | Restricts the picker to a list of icons. |
| `quickSaveColorPicker()` | `true` | Shows the color picker in the save form. |
| `quickSaveColors()` | `primary`, `success`, `warning`, `danger`, `info`, `gray` | The colors offered in the picker. |
| `viewNameMaxLength()` | `60` | The maximum length of a view name. |
| `modifiedIndicator()` | `true` | Marks applied views that were changed. |
| `persistActiveViewInSession()` | `false` | Keeps the active view across page loads. |

Disable user views for a single table by defining a static method on the page:

```php
public static function userViewsAreEnabled(): bool
{
    return false;
}
```

## Data model

User views are stored by the `KaraOdin\FilamentDynamicTables\Models\UserView` model. Replace it with your own model by extending it and registering the class:

```php
DynamicTablesPlugin::make()->userView(App\Models\TableView::class)
```

The `managedUserView()` and `managedPresetView()` methods replace the models that store each user's favorites, order and default view.
