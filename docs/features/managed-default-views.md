# Managed Default Views

Managed default views let each user choose the view a table opens with.

```php
DynamicTablesPlugin::make()->managedDefaultViewsEnabled()
```

Users set a default in two ways:

- with the **Set as default** item in the view manager's action menu
- with the **Make default** toggle in the quick save form

Only one view per user and per table can be the default. Setting a new default replaces the previous one.

## When the default applies

The default view is applied only when the page loads without table state, that is, without filters, search or sort in the query string and without state persisted in Filament's session.

When several defaults exist, the first one found is used:

1. the active view stored in the session, when `persistActiveViewInSession()` is enabled
2. the user's managed default view
3. the preset view marked with `->default()`

## Storage

Defaults are stored with the user's favorites and view order in the `dynamic_table_managed_views` table, through the `ManagedView` model.
