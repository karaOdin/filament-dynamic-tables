# Public Views, Global Favorites and Approval

## Public views

A public view is listed in the **Public views** section of every user's view manager. Other users can apply it and add it to their favorites, but only the owner can edit or delete it.

Public views are enabled by default. Remove the **Public** toggle with:

```php
DynamicTablesPlugin::make()->quickSaveMakePublic(false)
```

## Global favorites

A global favorite is added to every user's favorites bar. Users can remove it from their own favorites, after which it is listed in the **Global views** section of the view manager.

Global favorites are disabled by default:

```php
DynamicTablesPlugin::make()
    ->quickSaveMakeGlobalFavorite()
    ->globalUserViewsManageable(false)      // users cannot reorder or remove global favorites
    ->newGlobalUserViewSortPosition('after') // place new global favorites after the user's views
```

Restrict who can create global favorites with the `makeGlobalFavorite` policy ability. See [Authorization](../configuration/authorization.md).

## Approval

Views shared with other users, either public views or global favorites, have an approval status: `approved`, `pending` or `rejected`. Private views are always approved.

```php
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;

DynamicTablesPlugin::make()
    ->initialStatus(ViewStatus::Pending)
    ->minimumStatusForDisplay(ViewStatus::Approved)
```

- `initialStatus()` sets the status of a newly shared view.
- `minimumStatusForDisplay()` sets the lowest status that is shown to other users. The order is rejected, pending, approved.

Both default to `pending`, which shows shared views to other users immediately. The owner always sees their own views.

The following shortcuts require approval before a shared view is shown:

| Method | Effect |
| --- | --- |
| `approval()` | Requires approval for public views and global favorites. |
| `publicViewsRequireApproval()` | Requires approval for public views. |
| `globalFavoritesRequireApproval()` | Requires approval for global favorites. |

Users who pass the policy's `approve` ability skip the queue: their shared views are approved immediately.

Approve and reject views in the [user views resource](user-views-resource.md). To notify users of status changes, register a model observer on `UserView` and react to changes of the `status` attribute.
