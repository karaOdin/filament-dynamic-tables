<?php

namespace KaraOdin\FilamentDynamicTables\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;

/**
 * Per user, per table settings such as pinned quick filters.
 *
 * @property int $id
 * @property int|string $user_id
 * @property ?string $tenant_id
 * @property string $table_key
 * @property array<string, mixed> $settings
 */
class Preference extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('filament-dynamic-tables.tables.preferences', 'dynamic_table_preferences');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
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
}
