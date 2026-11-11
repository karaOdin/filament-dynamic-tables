# Favorites Bar

The favorites bar sits above the table toolbar. It lists the user's favorite views, followed by the quick save and view manager buttons.

Favorites are ordered in groups: favorite preset views first, then global favorites that users cannot manage, then the remaining views in the user's order. Users reorder their favorites in the [view manager](view-manager.md).

## Themes

```php
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;

DynamicTablesPlugin::make()
    ->favoritesBarTheme(FavoritesBarTheme::Tabs)
```

| Theme | Enum case |
| --- | --- |
| GitHub (default) | `FavoritesBarTheme::Github` |
| Filament | `FavoritesBarTheme::Filament` |
| Links | `FavoritesBarTheme::Links` |
| Links simple | `FavoritesBarTheme::LinksSimple` |
| Tabs | `FavoritesBarTheme::Tabs` |
| Tabs simple | `FavoritesBarTheme::TabsSimple` |

## Options

```php
DynamicTablesPlugin::make()
    ->favoritesBarSize('sm')
    ->favoritesBarIconPosition('after')
    ->favoritesBarIcons(false)
    ->favoritesBarDivider()
    ->favoritesBarBadges(false)
    ->favoritesBarLoadingIndicator()
    ->favoritesBarHiddenWhenEmpty()
```

| Method | Default | Description |
| --- | --- | --- |
| `favoritesBarEnabled()` | `true` | Shows the favorites bar. |
| `favoritesBarTheme()` | `Github` | The visual theme. |
| `favoritesBarSize()` | `md` | `sm`, `md` or `lg`. |
| `favoritesBarIconPosition()` | `before` | Places view icons `before` or `after` the label. |
| `favoritesBarIcons()` | `true` | Shows view icons. |
| `favoritesBarDivider()` | `false` | Separates preset views from user views. |
| `favoritesBarBadges()` | `true` | Shows preset view badges. |
| `favoritesBarLoadingIndicator()` | `false` | Shows a loading indicator on the view being applied. |
| `favoritesBarHiddenWhenEmpty()` | `false` | Hides the bar when there are no favorites. |
| `favoritesBarDefaultView()` | `true` | Shows the built-in Default view. |
| `favoritesBarDefaultIcon()` | alias `FAVORITES_BAR_DEFAULT` | The Default view icon. |
| `defaultViewLabel()` | `Default` | The Default view label. |

When the bar is disabled, quick save and the view manager move to the table toolbar.

Disable the bar for a single table:

```php
public static function favoritesBarIsEnabled(): bool
{
    return false;
}
```

## Placing the bar yourself

By default the bar renders in the table header. To render it elsewhere on the page, for example on a custom page or above a standalone table, use the Blade component:

```blade
<x-filament-dynamic-tables::favorites-bar />
```

When the component is present, the bar is no longer added to the table header.
