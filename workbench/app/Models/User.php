<?php

namespace Workbench\App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use KaraOdin\FilamentDynamicTables\Concerns\HasDynamicTableViews;
use Workbench\Database\Factories\UserFactory;

/**
 * @property bool $is_admin
 */
class User extends Authenticatable implements FilamentUser
{
    use HasDynamicTableViews;
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
