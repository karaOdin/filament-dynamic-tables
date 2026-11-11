# Installation

## Requirements

- PHP 8.2 or later
- Laravel 12 or later
- Filament 4.16 or later, or Filament 5.9 or later

If your application runs an older Filament minor version, update Filament first:

```bash
composer update "filament/*"
```

## Install the package

```bash
composer require karaodin/filament-dynamic-tables
```

## Publish the configuration and migrations

The package stores views and preferences in three tables: `dynamic_table_user_views`, `dynamic_table_managed_views` and `dynamic_table_preferences`.

Run the installer, which publishes the configuration file and the migration and offers to run the migrations:

```bash
php artisan filament-dynamic-tables:install
```

Or publish them manually:

```bash
php artisan vendor:publish --tag="filament-dynamic-tables-config"
php artisan vendor:publish --tag="filament-dynamic-tables-migrations"
php artisan migrate
```

Review these settings in `config/filament-dynamic-tables.php` before you migrate, because the migration reads them:

| Key | Default | Purpose |
| --- | --- | --- |
| `users.table` | `users` | The table the `user_id` foreign keys reference. |
| `users.key_column` | `id` | The referenced key column. |
| `tenancy.column` | `tenant_id` | The tenant column added to each package table. |
| `tables.*` | `dynamic_table_*` | The names of the package tables. |

If your user keys are UUIDs or ULIDs, edit the published migration and replace `foreignId()` with `foreignUuid()` or `foreignUlid()`.

## Register the plugin

Add the plugin to every panel that uses dynamic tables:

```php
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            DynamicTablesPlugin::make(),
        ]);
}
```

## Prepare the user model

Add the `HasDynamicTableViews` trait to your user model. It defines the relationships to the user's views and preferences.

```php
use KaraOdin\FilamentDynamicTables\Concerns\HasDynamicTableViews;

class User extends Authenticatable
{
    use HasDynamicTableViews;
}
```

## Publish the assets

The package registers a stylesheet and an Alpine component with Filament. Publish them with Filament's asset command:

```bash
php artisan filament:assets
```

Run this command again after every update of the package. Most applications already run it in a `post-autoload-dump` Composer script.

The stylesheet is plain CSS built on Filament's color variables. It does not require a custom theme or a Tailwind build, and it follows the panel colors and dark mode.

## Next step

Add the `DynamicTables` trait to a table. See [Adding dynamic tables to a table](adding-to-tables.md).
