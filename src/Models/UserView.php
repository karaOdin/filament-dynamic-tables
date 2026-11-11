<?php

namespace KaraOdin\FilamentDynamicTables\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;

/**
 * @property int $id
 * @property int|string $user_id
 * @property ?string $tenant_id
 * @property ?string $panel_id
 * @property string $table_key
 * @property ?string $url
 * @property string $name
 * @property ?string $icon
 * @property ?string $color
 * @property bool $is_public
 * @property bool $is_global_favorite
 * @property ViewStatus $status
 * @property array<string, mixed> $state
 * @property ?int $sort_order
 */
class UserView extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'approved',
        'is_public' => false,
        'is_global_favorite' => false,
    ];

    public function getTable(): string
    {
        return config('filament-dynamic-tables.tables.user_views', 'dynamic_table_user_views');
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_global_favorite' => 'boolean',
            'status' => ViewStatus::class,
            'state' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), 'user_id');
    }

    public function scopeForTable(Builder $query, string $tableKey): Builder
    {
        return $query->where('table_key', $tableKey);
    }

    public function scopeForTenant(Builder $query, int | string | null $tenantId): Builder
    {
        return $query->where(fn (Builder $query) => filled($tenantId)
            ? $query->where(Models::tenantColumn(), (string) $tenantId)
            : $query->whereNull(Models::tenantColumn()));
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ViewStatus::Approved->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ViewStatus::Pending->value);
    }

    /**
     * Views the user may see: their own views, approved public views and
     * approved global favorites.
     */
    /**
     * @param  array<string> | null  $statuses  statuses other users' views must have; approved only by default
     */
    public function scopeVisibleTo(Builder $query, Model $user, bool $includePublic = true, bool $includeGlobal = true, ?array $statuses = null): Builder
    {
        $statuses ??= [ViewStatus::Approved->value];

        return $query->where(function (Builder $query) use ($user, $includePublic, $includeGlobal, $statuses): void {
            $query->where('user_id', $user->getKey());

            if ($includePublic) {
                $query->orWhere(fn (Builder $query) => $query->where('is_public', true)->whereIn('status', $statuses));
            }

            if ($includeGlobal) {
                $query->orWhere(fn (Builder $query) => $query->where('is_global_favorite', true)->whereIn('status', $statuses));
            }
        });
    }

    public function isOwnedBy(?Model $user): bool
    {
        return $user && ((string) $this->user_id === (string) $user->getKey());
    }

    public function isApproved(): bool
    {
        return $this->status === ViewStatus::Approved;
    }

    public function getKeyString(): string
    {
        return 'user:' . $this->getKey();
    }
}
