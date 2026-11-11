<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Workbench\App\Livewire\ProductsTable;
use Workbench\App\Models\User;
use Workbench\App\Policies\WorkbenchUserViewPolicy;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'auth.providers.users.model' => User::class,
            'filament-dynamic-tables.policy' => WorkbenchUserViewPolicy::class,
        ]);
    }

    public function boot(): void
    {
        Livewire::component('products-table', ProductsTable::class);
    }
}
