<?php

namespace Workbench\App\Policies;

use Illuminate\Database\Eloquent\Model;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Policies\UserViewPolicy;
use Workbench\App\Models\User;

class WorkbenchUserViewPolicy extends UserViewPolicy
{
    public function makeGlobalFavorite(Model $user): bool
    {
        return $user instanceof User && $user->is_admin;
    }

    public function approve(Model $user, ?UserView $view = null): bool
    {
        return $user instanceof User && $user->is_admin;
    }

    public function update(Model $user, UserView $view): bool
    {
        return $view->isOwnedBy($user) || ($user instanceof User && $user->is_admin);
    }

    public function delete(Model $user, UserView $view): bool
    {
        return $view->isOwnedBy($user) || ($user instanceof User && $user->is_admin);
    }
}
