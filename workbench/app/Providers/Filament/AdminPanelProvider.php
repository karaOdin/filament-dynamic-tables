<?php

namespace Workbench\App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use Workbench\App\Filament\Resources\Products\ProductResource;
use Workbench\App\Filament\Widgets\LowStockProducts;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->resources([
                ProductResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                LowStockProducts::class,
            ])
            ->plugins([
                DynamicTablesPlugin::make()
                    ->favoritesBarTheme(FavoritesBarTheme::Github)
                    ->favoritesBarDivider()
                    ->favoritesBarLoadingIndicator()
                    ->quickSaveMakeGlobalFavorite()
                    ->managedDefaultViewsEnabled()
                    ->initialStatus(ViewStatus::Pending)
                    ->minimumStatusForDisplay(ViewStatus::Approved)
                    ->advancedSearchEnabled()
                    ->advancedSearchColumnsEnabled()
                    ->advancedSearchGroupingEnabled()
                    ->advancedSearchBooleanSyntaxEnabled()
                    ->advancedSearchKeybindings(['command+k', 'ctrl+k'])
                    ->quickFiltersEnabled()
                    ->tableLoadingOverlay()
                    ->viewManagerSaveView()
                    ->resourceEnabled()
                    ->resourceNavigationGroup('Settings'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
