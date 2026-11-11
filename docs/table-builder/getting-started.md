# Standalone Table Builder

Dynamic tables also work with Livewire components that use Filament's Table Builder outside a panel.

## Component setup

Replace Filament's `InteractsWithTable` trait with `InteractsWithDynamicTable`:

```php
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use KaraOdin\FilamentDynamicTables\InteractsWithDynamicTable;
use Livewire\Component;

class ProductsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithDynamicTable;
    use InteractsWithSchemas;

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query())
            ->columns([...]);
    }

    public function render(): View
    {
        return view('livewire.products-table');
    }
}
```

Alternatively, extend `KaraOdin\FilamentDynamicTables\Livewire\Page` and use the `DynamicTables` trait.

## Base classes

| Class | Use for |
| --- | --- |
| `KaraOdin\FilamentDynamicTables\Livewire\Page` | Standalone Livewire components. |
| `KaraOdin\FilamentDynamicTables\Livewire\CustomPage` | Custom panel pages. |
| `KaraOdin\FilamentDynamicTables\Livewire\PanelPage` | Custom resource pages. |

## View

Render the action modals in the component view:

```blade
<div>
    {{ $this->table }}

    <x-filament-actions::modals />
</div>
```

Include Filament's styles and scripts in your layout:

```blade
<head>
    @filamentStyles
</head>
<body>
    {{ $slot }}

    @filamentScripts
</body>
```

## Favorites bar

The favorites bar is added to the table header. To place it elsewhere, render the component above the table:

```blade
<x-filament-dynamic-tables::favorites-bar />

{{ $this->table }}
```

## Configuration

Components outside a panel have no plugin instance. They read their options from `config/filament-dynamic-tables.php`. Every plugin option has a matching configuration key. Override `configureDynamicTables()` to change options for one component. See [Per-table configuration](../configuration/per-table-configuration.md).

## User views resource

Route to the included component to manage views outside a panel:

```php
Route::get('/user-views', \KaraOdin\FilamentDynamicTables\Livewire\ListUserViews::class)
    ->middleware(['auth']);
```
