# View Manager

The view manager lists every view available on the table. It opens as a dropdown by default, or as a slide-over.

## Sections

The view manager has up to five sections:

1. **Favorites**: the user's favorite views, preset views first.
2. **My views**: the user's own views that are not favorites.
3. **Preset views**: preset views that are not favorites.
4. **Public views**: views that other users have made public.
5. **Global views**: global favorites the user removed from their favorites.

Sections can be reordered. Click the arrows icon to enter reorder mode, drag the views, then confirm.

## View actions

Each view has an action menu. Depending on the view type and the user's permissions, it contains:

- **Apply**
- **Set as default** or **Remove default**, when [managed default views](managed-default-views.md) are enabled
- **Add to favorites** or **Remove from favorites**
- **Edit**: change the name, icon, color and sharing flags
- **Replace**: overwrite the view with the current table state
- **Make public** or **Make private**
- **Delete**

## Placement

```php
DynamicTablesPlugin::make()
    // In the favorites bar
    ->viewManagerInFavoritesBar(position: 'start')

    // Or in the table toolbar, at any Filament table render hook
    ->viewManagerInFavoritesBar(false)
    ->viewManagerInTable(position: 'tables::toolbar.toggle-column-trigger.before')
```

## Options

| Method | Default | Description |
| --- | --- | --- |
| `viewManagerEnabled()` | `true` | Shows the view manager. |
| `viewManagerInFavoritesBar(condition, position)` | `true`, `end` | Places the trigger in the favorites bar. |
| `viewManagerInTable(condition, position)` | `false` | Places the trigger in the table toolbar, optionally at a render hook. |
| `viewManagerSlideOver()` | `false` | Opens a slide-over instead of a dropdown. |
| `viewManagerTriggerAction()` | | Customizes the trigger action. |
| `viewManagerSearch()` | `true` | Shows a search field. |
| `viewManagerReorderable()` | `true` | Allows reordering. |
| `viewManagerSaveView()` | `false` | Shows a **Save view** link in the dropdown footer. |
| `viewManagerResetView()` | `false` | Shows a **Reset** link instead of the Default row. |
| `viewManagerClickToApply()` | `true` | Applies a view when its row is clicked. |
| `viewManagerApplyButton()` | `true` | Shows an **Apply** item in the action menu. |
| `viewManagerClosesOnApply()` | `true` | Closes the view manager after applying a view. |
| `viewManagerActiveViewIndicator()` | `true` | Highlights the active view. |
| `viewManagerActiveViewBadge()` | `false` | Shows an "Active" badge on the active view. |
| `viewManagerViewTypeIcons()` | `true` | Shows icons for public, global and preset views. |
| `viewManagerViewTypeBadges()` | `false` | Shows badges instead of icons for the view type. |
| `viewManagerPublicIndicatorWhenGlobal()` | `false` | Shows the public indicator on global favorites too. |
| `viewManagerPresetViews()` | `true` | Shows the preset views section. |
| `viewManagerPublicViews()` | `true` | Shows the public views section. |
| `viewManagerGlobalViews()` | `true` | Shows the global views section. |
| `viewIcon()` | `true` | Shows each view's icon. |
| `defaultViewIcon()` | `heroicon-o-queue-list` | The icon for views without one. |

Disable the view manager for a single table:

```php
public static function viewManagerIsEnabled(): bool
{
    return false;
}
```
