# Multi-Tenancy

How you set up tenancy depends on whether tenants share a database.

## Database per tenant

With a database per tenant, for example stancl/tenancy in multi-database mode, run the package migrations in each tenant database. Publish them into your tenant migrations folder:

```bash
php artisan vendor:publish --tag="filament-dynamic-tables-migrations"
mv database/migrations/*_create_filament_dynamic_tables_tables.php database/migrations/tenant/
php artisan tenants:migrate
```

No other configuration is needed. Leave tenancy disabled in the plugin.

## Shared database

When tenants share a database, register the tenant model. Views, favorites and preferences are then scoped to the current tenant.

```php
DynamicTablesPlugin::make()
    ->tenant(Team::class)
    ->tenantColumn('account_id')
    ->scopeToTenancy(false)
```

| Method | Default | Description |
| --- | --- | --- |
| `tenant()` | `null` | The tenant model. Setting it enables tenancy. |
| `tenancy()` | `false` | Enables tenancy without a model, with `resolveTenantUsing()`. |
| `tenantColumn()` | `tenant_id` | The tenant column in the package tables. |
| `scopeToTenancy()` | `true` | Set to `false` to share views across tenants while still storing the tenant. |
| `resolveTenantUsing()` | | A closure that returns the current tenant or its key. |

Outside a panel, set `tenancy.model` and the other `tenancy.*` keys in the configuration file.

## Resolving the current tenant

The current tenant is resolved in this order:

1. the closure passed to `resolveTenantUsing()`
2. Filament's current tenant, for panels with Filament tenancy
3. `Tenant::current()`, for spatie/laravel-multitenancy
4. `tenant()`, for stancl/tenancy in single-database mode
5. the tenant model's `getTenantId()` method, for simple one-to-many tenancy

```php
// spatie/laravel-multitenancy
DynamicTablesPlugin::make()->tenant(\Spatie\Multitenancy\Models\Tenant::class)

// stancl/tenancy, single database
DynamicTablesPlugin::make()->tenant(\Stancl\Tenancy\Database\Models\Tenant::class)
```

For simple one-to-many tenancy, define `getTenantId()` on the tenant model:

```php
class Team extends Model
{
    public function getTenantId(): ?string
    {
        return auth()->user()?->team_id;
    }
}
```

## Adding tenancy to existing tables

If the package tables were migrated before tenancy was enabled, add the tenant column with:

```bash
php artisan dynamic-tables:add-tenancy
```

The command uses the column name from `tenancy.column`.
