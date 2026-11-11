<?php

namespace KaraOdin\FilamentDynamicTables\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;

/**
 * A user's personal settings for one view (preset or user view): whether it
 * is in their favorites bar, its position there and whether it is their
 * default view for the table.
 *
 * @property int $id
 * @property int|string $user_id
 * @property ?string $tenant_id
 * @property string $table_key
 * @property string $view_type
 * @property string $view_key
 * @property ?bool $is_favorite
 * @property bool $is_default
 * @property ?int $sort_order
 */
class ManagedView extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'is_default' => false,
    ];

    public function getTable(): string
    {
        return config('filament-dynamic-tables.tables.managed_views', 'dynamic_table_managed_views');
    }

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), 'user_id');
    }

    public function scopeForContext(Builder $query, Model $user, string $tableKey, int | string | null $tenantId): Builder
    {
        return $query
            ->where('user_id', $user->getKey())
            ->where('table_key', $tableKey)
            ->where(fn (Builder $query) => filled($tenantId)
                ? $query->where(Models::tenantColumn(), (string) $tenantId)
                : $query->whereNull(Models::tenantColumn()));
    }

    public function getViewKeyString(): string
    {
        return "{$this->view_type}:{$this->view_key}";
    }
}
