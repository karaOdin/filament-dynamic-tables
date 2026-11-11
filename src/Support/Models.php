<?php

namespace KaraOdin\FilamentDynamicTables\Support;

use KaraOdin\FilamentDynamicTables\Models\ManagedView;
use KaraOdin\FilamentDynamicTables\Models\Preference;
use KaraOdin\FilamentDynamicTables\Models\UserView;

class Models
{
    /**
     * @return class-string<UserView>
     */
    public static function userView(): string
    {
        return config('filament-dynamic-tables.models.user_view', UserView::class);
    }

    /**
     * @return class-string<ManagedView>
     */
    public static function managedView(): string
    {
        return config('filament-dynamic-tables.models.managed_view', ManagedView::class);
    }

    /**
     * The column the package's own tables store the tenant in.
     */
    public static function tenantColumn(): string
    {
        return config('filament-dynamic-tables.tenancy.column', 'tenant_id');
    }

    /**
     * @return class-string<Preference>
     */
    public static function preference(): string
    {
        return config('filament-dynamic-tables.models.preference', Preference::class);
    }
}
