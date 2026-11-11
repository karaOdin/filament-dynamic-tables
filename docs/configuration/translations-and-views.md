# Translations and Views

## Translations

The package ships translations for English, French, Spanish and Arabic.

Publish the translations to change them or add a language:

```bash
php artisan vendor:publish --tag="filament-dynamic-tables-translations"
```

The files are published to `lang/vendor/filament-dynamic-tables/{locale}/dynamic-tables.php`.

## Views

Publish the Blade views to change the markup:

```bash
php artisan vendor:publish --tag="filament-dynamic-tables-views"
```

Published views are not updated with the package. Review them after each update.

## Styling

The stylesheet uses Filament's CSS color variables and follows the panel colors and dark mode. Package elements use the `fdt-` class prefix, so they can be targeted from a custom theme:

| Class | Element |
| --- | --- |
| `.fdt-favorites-bar` | The favorites bar |
| `.fdt-view-manager-panel` | The view manager |
| `.fdt-advanced-search` | The advanced search input |
| `.fdt-filter-builder` | The advanced filter builder |
