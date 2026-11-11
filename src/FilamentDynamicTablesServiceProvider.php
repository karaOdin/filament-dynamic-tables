<?php

namespace KaraOdin\FilamentDynamicTables;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use KaraOdin\FilamentDynamicTables\Commands\AddTenancyCommand;
use KaraOdin\FilamentDynamicTables\Support\FilterOptions;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\RenderHooks;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDynamicTablesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-dynamic-tables';

    public static string $viewNamespace = 'filament-dynamic-tables';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigration('create_filament_dynamic_tables_tables')
            ->hasCommand(AddTenancyCommand::class)
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('karaOdin/filament-dynamic-tables');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('filament-dynamic-tables.fallback-plugin', fn (): DynamicTablesPlugin => new DynamicTablesPlugin);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-dynamic-tables', __DIR__ . '/../resources/dist/filament-dynamic-tables.css'),
            AlpineComponent::make('advanced-search', __DIR__ . '/../resources/dist/components/advanced-search.js'),
        ], 'karaodin/filament-dynamic-tables');

        RenderHooks::register();
        FilterOptions::register();

        $this->registerPolicy();
    }

    protected function registerPolicy(): void
    {
        $model = Models::userView();
        $policy = config('filament-dynamic-tables.policy');

        // Only explicitly registered policies count: Laravel would otherwise
        // auto-discover this package's default policy and hide the config.
        if (blank($policy) || array_key_exists($model, Gate::policies())) {
            return;
        }

        Gate::policy($model, $policy);
    }
}
