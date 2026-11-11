# Adding Dynamic Tables to a Table

Add the `DynamicTables` trait to the Livewire component that owns the table. The trait works with every kind of Filament table component.

```php
use KaraOdin\FilamentDynamicTables\DynamicTables;

// Resource list page
class ListProducts extends ListRecords
{
    use DynamicTables;
}

// Simple (modal) resource
class ManageCustomers extends ManageRecords
{
    use DynamicTables;
}

// Relation manager
class OrdersRelationManager extends RelationManager
{
    use DynamicTables;
}

// Table widget
class LatestOrders extends TableWidget
{
    use DynamicTables;
}
```

Custom panel pages that render a table use the trait in the same way. Livewire components that use the Table Builder outside a panel are covered in [Standalone Table Builder](../table-builder/getting-started.md).

## What the trait adds

With the default configuration, a table using the trait gets:

- a favorites bar above the toolbar with the built-in Default view
- a quick save button and a view manager dropdown
- a multi-sort button when the table has at least one sortable column
- saving and applying user views and preset views

Quick filters, advanced search, global favorites, the loading overlay and the user views resource are disabled by default. Enable them on the plugin or per table. See [Plugin options](../configuration/plugin-options.md).

## Table keys

Views belong to a single table. The table key defaults to the component class, followed by the table identifier when the component defines one. Anonymous subclasses use the key of their parent class.

Override `getDynamicTableKey()` to share views between several tables, or to keep existing views after renaming a class:

```php
public function getDynamicTableKey(): string
{
    return 'orders';
}
```

## How the interface is rendered

The interface is injected through Filament's table render hooks. Filament's table views are never overridden, so Filament updates continue to work.

The favorites bar renders inside the table header. Filament only renders the header when the table has a heading, header actions, a search field, filters, toggleable columns or grouping.
