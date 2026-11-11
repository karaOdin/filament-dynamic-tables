<?php

namespace KaraOdin\FilamentDynamicTables\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Users
{
    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        if ($model = config('filament-dynamic-tables.users.model')) {
            return $model;
        }

        $guard = static::guardName();
        $provider = config("auth.guards.{$guard}.provider", 'users');

        return config("auth.providers.{$provider}.model", 'App\\Models\\User');
    }

    public static function guardName(): string
    {
        if ($guard = config('filament-dynamic-tables.users.guard')) {
            return $guard;
        }

        try {
            if (class_exists(Filament::class) && ($panel = Filament::getCurrentPanel())) {
                return $panel->getAuthGuard();
            }
        } catch (Throwable) {
            // Not inside a panel.
        }

        return config('auth.defaults.guard', 'web');
    }

    public static function current(): ?Model
    {
        $user = auth()->guard(static::guardName())->user();

        return $user instanceof Model ? $user : null;
    }

    public static function nameOf(?Model $user): ?string
    {
        if (! $user) {
            return null;
        }

        $attribute = config('filament-dynamic-tables.users.name_attribute', 'name');

        return (string) ($user->getAttribute($attribute) ?? $user->getKey());
    }
}
