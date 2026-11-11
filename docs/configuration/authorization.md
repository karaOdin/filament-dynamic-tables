# Authorization

Every ability goes through a Laravel policy on the user view model.

## Default policy

The default `KaraOdin\FilamentDynamicTables\Policies\UserViewPolicy`:

- lets any user create views
- lets owners update and delete their own views
- lets nobody approve views

## Custom policy

Extend the default policy and register it in the configuration file:

```php
// config/filament-dynamic-tables.php
'policy' => App\Policies\TableViewPolicy::class,
```

```php
namespace App\Policies;

use App\Models\User;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Policies\UserViewPolicy;

class TableViewPolicy extends UserViewPolicy
{
    public function makeGlobalFavorite(User $user): bool
    {
        return $user->is_admin;
    }

    public function approve(User $user, ?UserView $view = null): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, UserView $view): bool
    {
        return $view->isOwnedBy($user) || $user->is_admin;
    }
}
```

A policy registered with `Gate::policy()` for the user view model is also used.

## Abilities

| Ability | Controls |
| --- | --- |
| `viewAny` | Access to the user views resource. |
| `create` | Quick save. |
| `update` | Editing, replacing and sharing a view. |
| `delete` | Deleting a view. |
| `makePublic` | The **Public** toggle. |
| `makeFavorite` | The **Add to favorites** toggle. |
| `makeGlobalFavorite` | The **Global favorite** toggle. |
| `selectIcon` | The icon picker. |
| `selectColor` | The color picker. |
| `approve` | Approving and rejecting views, and skipping the approval queue. |

## Server-side validation

Everything the browser sends is validated on the server:

- icons and colors must come from the allowed lists
- view keys must belong to the current table and be visible to the user
- sorts must name sortable columns
