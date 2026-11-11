# Multi-Sort

Multi-sort lets users sort a table by several columns.

A sort button in the table toolbar opens a panel where users:

- add a sortable column
- switch a column between ascending and descending
- drag columns to change their priority
- remove a column from the sort

Clicking a column header still works. It replaces the sort list with that column. Clicking the primary column again flips its direction and keeps the other sorts.

## Options

```php
DynamicTablesPlugin::make()
    ->multiSortMaxColumns(3)
    ->multiSortTriggerAction(fn (Action $action) => $action->tooltip('Sort by multiple columns'))
```

| Method | Default | Description |
| --- | --- | --- |
| `multiSortEnabled()` | `true` | Shows the multi-sort button. |
| `multiSortMaxColumns()` | `5` | The maximum number of sorted columns. `null` removes the limit. |
| `multiSortTriggerAction()` | | Customizes the trigger action. |

The button is shown only when the table has at least one sortable column.

Disable multi-sort for a single table:

```php
public static function multiSortIsEnabled(): bool
{
    return false;
}
```

## Behavior

- Sorting uses each column's own `sortable()` logic, so relationship columns and custom sort queries work.
- Only sortable columns are accepted. Sorts sent by the browser for other columns are ignored.
- When the table persists its sort in the session, the full sort list is persisted.
- User views save every sort in priority order.

## Preset views

Set several sorts on a preset view:

```php
PresetView::make('Featured')
    ->defaultSort(['is_featured' => 'desc', 'price' => 'asc'])
```
