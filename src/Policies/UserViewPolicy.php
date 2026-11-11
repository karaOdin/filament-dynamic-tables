<?php

namespace KaraOdin\FilamentDynamicTables\Policies;

use Illuminate\Database\Eloquent\Model;
use KaraOdin\FilamentDynamicTables\Models\UserView;

/**
 * Default policy. Publish your own and point config('filament-dynamic-tables.policy')
 * at it (or register one with Gate::policy()) to change any rule:
 *
 *     public function makeGlobalFavorite(User $user): bool
 *     {
 *         return $user->is_admin;
 *     }
 */
class UserViewPolicy
{
    /**
     * Opening the user views resource.
     */
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, UserView $view): bool
    {
        return $view->isOwnedBy($user) || (($view->is_public || $view->is_global_favorite) && $view->isApproved());
    }

    public function create(Model $user): bool
    {
        return true;
    }

    public function update(Model $user, UserView $view): bool
    {
        return $view->isOwnedBy($user);
    }

    public function delete(Model $user, UserView $view): bool
    {
        return $view->isOwnedBy($user);
    }

    public function deleteAny(Model $user): bool
    {
        return true;
    }

    /**
     * Share a view with every user of the table.
     */
    public function makePublic(Model $user): bool
    {
        return true;
    }

    /**
     * Add a view to one's own favorites bar.
     */
    public function makeFavorite(Model $user): bool
    {
        return true;
    }

    /**
     * Put a view in everyone's favorites bar.
     */
    public function makeGlobalFavorite(Model $user): bool
    {
        return true;
    }

    public function selectIcon(Model $user): bool
    {
        return true;
    }

    public function selectColor(Model $user): bool
    {
        return true;
    }

    /**
     * Approve or reject public views and global favorites. Users who can
     * approve skip the approval queue for their own views.
     */
    public function approve(Model $user, ?UserView $view = null): bool
    {
        return false;
    }
}
