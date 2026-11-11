<?php

namespace KaraOdin\FilamentDynamicTables\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use KaraOdin\FilamentDynamicTables\Support\Models;

/**
 * Add to your User model.
 */
trait HasDynamicTableViews
{
    public function dynamicTableViews(): HasMany
    {
        return $this->hasMany(Models::userView(), 'user_id');
    }

    public function dynamicTableManagedViews(): HasMany
    {
        return $this->hasMany(Models::managedView(), 'user_id');
    }

    public function dynamicTablePreferences(): HasMany
    {
        return $this->hasMany(Models::preference(), 'user_id');
    }
}
