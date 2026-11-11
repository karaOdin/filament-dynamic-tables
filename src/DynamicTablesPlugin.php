<?php

namespace KaraOdin\FilamentDynamicTables;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use KaraOdin\FilamentDynamicTables\Enums\ActionPosition;
use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use KaraOdin\FilamentDynamicTables\Enums\SearchConstraint;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Models\ManagedView;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource;
use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias;
use Throwable;

/**
 * Panel plugin and configuration object.
 *
 * Every getter falls back to config/filament-dynamic-tables.php, so the
 * same object also configures standalone Table Builder components. A copy
 * of it is handed to each table's configureDynamicTables() method, which
 * lets a single table override any option.
 */
class DynamicTablesPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'filament-dynamic-tables';

    /** @var array<string, mixed> */
    protected array $options = [];

    protected ?Closure $resolveTenantUsing = null;

    protected ?Closure $canAccessResourceUsing = null;

    protected ?Closure $modifyQuickSaveActionUsing = null;

    protected ?Closure $modifyViewManagerActionUsing = null;

    protected ?Closure $modifyEditViewActionUsing = null;

    public function getId(): string
    {
        return static::ID;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    /**
     * The plugin registered on the current panel, or a config-backed
     * instance when the table is rendered outside of a panel.
     */
    public static function current(): self
    {
        try {
            $panel = class_exists(Filament::class) ? Filament::getCurrentPanel() : null;

            if ($panel?->hasPlugin(static::ID) && (($plugin = $panel->getPlugin(static::ID)) instanceof self)) {
                return $plugin;
            }
        } catch (Throwable) {
            // No panel available.
        }

        if (! app()->bound('filament-dynamic-tables.fallback-plugin')) {
            app()->instance('filament-dynamic-tables.fallback-plugin', new self);
        }

        return app('filament-dynamic-tables.fallback-plugin');
    }

    public function register(Panel $panel): void
    {
        if ($this->isUserViewsResourceEnabled()) {
            $panel->resources([$this->getUserViewsResource()]);
        }
    }

    public function boot(Panel $panel): void {}

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    protected function set(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    protected function value(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->options)) {
            $value = $this->evaluate($this->options[$key]);

            if ($value !== null) {
                return $value;
            }
        }

        return config("filament-dynamic-tables.{$key}") ?? $default;
    }

    protected function bool(string $key, bool $default): bool
    {
        return (bool) $this->value($key, $default);
    }

    /*
    |--------------------------------------------------------------------------
    | Users & tenancy
    |--------------------------------------------------------------------------
    */

    public function tenancy(bool | Closure $condition = true): static
    {
        return $this->set('tenancy.enabled', $condition);
    }

    public function isTenancyEnabled(): bool
    {
        return $this->bool('tenancy.enabled', filled(config('filament-dynamic-tables.tenancy.model')));
    }

    /**
     * Resolve the current tenant key yourself, for example for
     * spatie/laravel-multitenancy: fn () => Tenant::current()?->getKey().
     */
    public function resolveTenantUsing(?Closure $callback): static
    {
        $this->resolveTenantUsing = $callback;

        if ($callback) {
            $this->tenancy();
        }

        return $this;
    }

    public function getTenantKey(): int | string | null
    {
        if ((! $this->isTenancyEnabled()) || (! $this->isScopedToTenancy())) {
            return null;
        }

        if ($this->resolveTenantUsing) {
            return $this->evaluate($this->resolveTenantUsing);
        }

        try {
            if (filled($key = Filament::getTenant()?->getKey())) {
                return $key;
            }
        } catch (Throwable) {
            // No Filament tenant.
        }

        $model = $this->getTenantModel() ?? config('filament-dynamic-tables.tenancy.model');

        if (blank($model) || (! class_exists($model))) {
            return null;
        }

        try {
            // spatie/laravel-multitenancy
            if (method_exists($model, 'current') && (($current = $model::current()) instanceof Model)) {
                return $current->getKey();
            }

            // stancl/tenancy
            if (function_exists('tenant') && (($current = tenant()) instanceof Model)) {
                return method_exists($current, 'getTenantKey') ? $current->getTenantKey() : $current->getKey();
            }

            // Simple one-to-many tenancy: the tenant model tells which tenant is current.
            if (method_exists($model, 'getTenantId')) {
                return app($model)->getTenantId();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | User views
    |--------------------------------------------------------------------------
    */

    public function userViews(bool | Closure $condition = true): static
    {
        return $this->set('user_views.enabled', $condition);
    }

    public function isUserViewsEnabled(): bool
    {
        return $this->bool('user_views.enabled', true);
    }

    public function publicViews(bool | Closure $condition = true): static
    {
        return $this->set('user_views.public_views', $condition);
    }

    public function isPublicViewsEnabled(): bool
    {
        return $this->bool('user_views.public_views', true);
    }

    public function globalFavorites(bool | Closure $condition = true): static
    {
        return $this->set('user_views.global_favorites', $condition);
    }

    public function isGlobalFavoritesEnabled(): bool
    {
        return $this->bool('user_views.global_favorites', false);
    }

    /**
     * Shortcut that turns on approval for both public views and global favorites.
     */
    public function approval(bool | Closure $condition = true): static
    {
        return $this
            ->publicViewsRequireApproval($condition)
            ->globalFavoritesRequireApproval($condition);
    }

    public function publicViewsRequireApproval(bool | Closure $condition = true): static
    {
        return $this->set('user_views.public_views_require_approval', $condition);
    }

    public function doPublicViewsRequireApproval(): bool
    {
        return $this->bool('user_views.public_views_require_approval', false);
    }

    public function globalFavoritesRequireApproval(bool | Closure $condition = true): static
    {
        return $this->set('user_views.global_favorites_require_approval', $condition);
    }

    public function doGlobalFavoritesRequireApproval(): bool
    {
        return $this->bool('user_views.global_favorites_require_approval', false);
    }

    public function iconSelect(bool | Closure $condition = true): static
    {
        return $this->set('user_views.icon_select', $condition);
    }

    public function isIconSelectEnabled(): bool
    {
        return $this->bool('user_views.icon_select', true);
    }

    public function colorSelect(bool | Closure $condition = true): static
    {
        return $this->set('user_views.color_select', $condition);
    }

    public function isColorSelectEnabled(): bool
    {
        return $this->bool('user_views.color_select', true);
    }

    /**
     * @param  array<string, string> | array<string> | Closure | null  $icons  icon name => label, or a list of icon names
     */
    public function icons(array | Closure | null $icons): static
    {
        return $this->set('user_views.icons', $icons);
    }

    /**
     * @return array<string, string>
     */
    public function getIcons(): array
    {
        $icons = $this->value('user_views.icons') ?? static::heroicons($this->includesOutlineIcons(), $this->includesSolidIcons());

        if (array_is_list($icons)) {
            $icons = collect($icons)
                ->mapWithKeys(fn (string $icon): array => [$icon => (string) str($icon)->after('heroicon-')->after('o-')->after('s-')->headline()])
                ->all();
        }

        return $icons;
    }

    /**
     * @param  array<string> | Closure | null  $colors
     */
    public function colors(array | Closure | null $colors): static
    {
        return $this->set('user_views.colors', $colors);
    }

    /**
     * @return array<string>
     */
    public function getColors(): array
    {
        return $this->value('user_views.colors') ?? ['primary', 'success', 'warning', 'danger', 'info', 'gray'];
    }

    public function persistActiveViewInSession(bool | Closure $condition = true): static
    {
        return $this->set('user_views.persist_active_view_in_session', $condition);
    }

    public function persistsActiveViewInSession(): bool
    {
        return $this->bool('user_views.persist_active_view_in_session', false);
    }

    public function modifiedIndicator(bool | Closure $condition = true): static
    {
        return $this->set('user_views.modified_indicator', $condition);
    }

    public function hasModifiedIndicator(): bool
    {
        return $this->bool('user_views.modified_indicator', true);
    }

    public function viewNameMaxLength(int | Closure $length): static
    {
        return $this->set('user_views.name_max_length', $length);
    }

    public function getViewNameMaxLength(): int
    {
        return (int) $this->value('user_views.name_max_length', 60);
    }

    /*
    |--------------------------------------------------------------------------
    | Preset views & default view
    |--------------------------------------------------------------------------
    */

    public function presetViews(bool | Closure $condition = true): static
    {
        return $this->set('preset_views.enabled', $condition);
    }

    public function isPresetViewsEnabled(): bool
    {
        return $this->bool('preset_views.enabled', true);
    }

    /**
     * The built-in "Default" view that resets the table to its original state.
     */
    public function defaultView(bool | Closure $condition = true): static
    {
        return $this->set('default_view.enabled', $condition);
    }

    public function isDefaultViewEnabled(): bool
    {
        return $this->bool('default_view.enabled', true);
    }

    public function defaultViewLabel(string | Closure | null $label): static
    {
        return $this->set('default_view.label', $label);
    }

    public function getDefaultViewLabel(): string
    {
        return $this->value('default_view.label') ?? __('filament-dynamic-tables::dynamic-tables.default_view.label');
    }

    /**
     * The icon used for views that have none.
     */
    public function defaultViewIcon(string | BackedEnum | Closure | null $icon): static
    {
        return $this->set('view_manager.fallback_icon', $icon);
    }

    public function getFallbackViewIcon(): string | BackedEnum | null
    {
        return $this->value('view_manager.fallback_icon', 'heroicon-o-queue-list');
    }

    /**
     * The icon of the built-in Default view. Pass null to show none.
     */
    public function favoritesBarDefaultIcon(string | BackedEnum | Closure | null $icon = null): static
    {
        return $this->set('default_view.icon', $icon ?? false);
    }

    public function getDefaultViewIcon(): string | BackedEnum | null
    {
        $icon = $this->value('default_view.icon', DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::FAVORITES_BAR_DEFAULT));

        return ($icon === false) ? null : $icon;
    }

    public function managedDefaultViews(bool | Closure $condition = true): static
    {
        return $this->set('managed_default_views.enabled', $condition);
    }

    /**
     * Kept for parity with the naming used in the docs.
     */
    public function managedDefaultViewsEnabled(bool | Closure $condition = true): static
    {
        return $this->managedDefaultViews($condition);
    }

    public function isManagedDefaultViewsEnabled(): bool
    {
        return $this->bool('managed_default_views.enabled', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Favorites bar
    |--------------------------------------------------------------------------
    */

    public function favoritesBar(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.enabled', $condition);
    }

    public function isFavoritesBarEnabled(): bool
    {
        return $this->bool('favorites_bar.enabled', true);
    }

    public function favoritesBarTheme(FavoritesBarTheme | string | Closure $theme): static
    {
        return $this->set('favorites_bar.theme', $theme);
    }

    public function getFavoritesBarTheme(): FavoritesBarTheme
    {
        $theme = $this->value('favorites_bar.theme', FavoritesBarTheme::Github);

        return $theme instanceof FavoritesBarTheme ? $theme : (FavoritesBarTheme::tryFrom((string) $theme) ?? FavoritesBarTheme::Github);
    }

    public function favoritesBarSize(string | BackedEnum | Closure $size): static
    {
        return $this->set('favorites_bar.size', $size);
    }

    public function getFavoritesBarSize(): string
    {
        $size = $this->value('favorites_bar.size', 'md');
        $size = $size instanceof BackedEnum ? (string) $size->value : (string) $size;
        $size = ['small' => 'sm', 'medium' => 'md', 'large' => 'lg'][$size] ?? $size;

        return in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    }

    public function favoritesBarIconPosition(string | BackedEnum | Closure $position): static
    {
        return $this->set('favorites_bar.icon_position', $position);
    }

    public function getFavoritesBarIconPosition(): string
    {
        $position = $this->value('favorites_bar.icon_position', 'before');
        $position = $position instanceof BackedEnum ? (string) $position->value : (string) $position;

        return $position === 'after' ? 'after' : 'before';
    }

    public function favoritesBarIcons(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.icons', $condition);
    }

    public function hasFavoritesBarIcons(): bool
    {
        return $this->bool('favorites_bar.icons', true) && $this->getFavoritesBarTheme()->showsIcons();
    }

    public function favoritesBarDivider(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.divider', $condition);
    }

    public function hasFavoritesBarDivider(): bool
    {
        return $this->bool('favorites_bar.divider', false);
    }

    public function favoritesBarBadges(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.badges', $condition);
    }

    public function hasFavoritesBarBadges(): bool
    {
        return $this->bool('favorites_bar.badges', true);
    }

    public function favoritesBarLoadingIndicator(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.loading_indicator', $condition);
    }

    public function hasFavoritesBarLoadingIndicator(): bool
    {
        return $this->bool('favorites_bar.loading_indicator', false);
    }

    public function favoritesBarHiddenWhenEmpty(bool | Closure $condition = true): static
    {
        return $this->set('favorites_bar.hidden_when_empty', $condition);
    }

    public function isFavoritesBarHiddenWhenEmpty(): bool
    {
        return $this->bool('favorites_bar.hidden_when_empty', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Quick save
    |--------------------------------------------------------------------------
    */

    public function quickSave(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.enabled', $condition);
    }

    public function isQuickSaveEnabled(): bool
    {
        return $this->isUserViewsEnabled() && $this->bool('quick_save.enabled', true);
    }

    public function quickSavePosition(ActionPosition | string | Closure $position): static
    {
        return $this->set('quick_save.position', $position);
    }

    public function getQuickSavePosition(): ActionPosition
    {
        return $this->resolvePosition($this->value('quick_save.position', ActionPosition::FavoritesBar));
    }

    public function quickSaveInToolbar(): static
    {
        return $this->quickSavePosition(ActionPosition::Toolbar);
    }

    public function quickSaveSlideOver(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.slide_over', $condition);
    }

    public function isQuickSaveSlideOver(): bool
    {
        return $this->bool('quick_save.slide_over', true);
    }

    public function quickSaveIcon(string | BackedEnum | Closure $icon): static
    {
        return $this->set('quick_save.icon', $icon);
    }

    public function getQuickSaveIcon(): string | BackedEnum
    {
        return $this->value('quick_save.icon', 'heroicon-o-plus');
    }

    public function quickSaveFavoriteByDefault(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.favorite_by_default', $condition);
    }

    public function isQuickSaveFavoriteByDefault(): bool
    {
        return $this->bool('quick_save.favorite_by_default', true);
    }

    public function quickSavePublicByDefault(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.public_by_default', $condition);
    }

    public function isQuickSavePublicByDefault(): bool
    {
        return $this->bool('quick_save.public_by_default', false);
    }

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function modifyQuickSaveActionUsing(?Closure $callback): static
    {
        $this->modifyQuickSaveActionUsing = $callback;

        return $this;
    }

    public function getModifyQuickSaveActionUsing(): ?Closure
    {
        return $this->modifyQuickSaveActionUsing;
    }

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function modifyEditViewActionUsing(?Closure $callback): static
    {
        $this->modifyEditViewActionUsing = $callback;

        return $this;
    }

    public function getModifyEditViewActionUsing(): ?Closure
    {
        return $this->modifyEditViewActionUsing;
    }

    /*
    |--------------------------------------------------------------------------
    | View manager
    |--------------------------------------------------------------------------
    */

    public function viewManager(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.enabled', $condition);
    }

    public function isViewManagerEnabled(): bool
    {
        return $this->bool('view_manager.enabled', true);
    }

    public function viewManagerPosition(ActionPosition | string | Closure $position): static
    {
        return $this->set('view_manager.position', $position);
    }

    public function getViewManagerPosition(): ActionPosition
    {
        return $this->resolvePosition($this->value('view_manager.position', ActionPosition::FavoritesBar));
    }

    public function viewManagerInToolbar(): static
    {
        return $this->viewManagerPosition(ActionPosition::Toolbar);
    }

    public function viewManagerSlideOver(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.slide_over', $condition);
    }

    public function isViewManagerSlideOver(): bool
    {
        return $this->bool('view_manager.slide_over', false);
    }

    public function viewManagerIcon(string | BackedEnum | Closure $icon): static
    {
        return $this->set('view_manager.icon', $icon);
    }

    public function getViewManagerIcon(): string | BackedEnum
    {
        return $this->value('view_manager.icon', 'heroicon-o-queue-list');
    }

    public function viewManagerSearchable(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.searchable', $condition);
    }

    public function isViewManagerSearchable(): bool
    {
        return $this->bool('view_manager.searchable', true);
    }

    public function viewManagerReorderable(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.reorderable', $condition);
    }

    public function isViewManagerReorderable(): bool
    {
        return $this->bool('view_manager.reorderable', true);
    }

    public function viewManagerPresetViews(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.preset_views', $condition);
    }

    public function showsPresetViewsInViewManager(): bool
    {
        return $this->bool('view_manager.preset_views', true);
    }

    public function viewManagerPublicViews(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.public_views', $condition);
    }

    public function showsPublicViewsInViewManager(): bool
    {
        return $this->isPublicViewsEnabled() && $this->bool('view_manager.public_views', true);
    }

    public function viewManagerGlobalViews(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.global_views', $condition);
    }

    public function showsGlobalViewsInViewManager(): bool
    {
        return $this->isGlobalFavoritesEnabled() && $this->bool('view_manager.global_views', true);
    }

    public function viewManagerClosesOnApply(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.close_on_apply', $condition);
    }

    public function doesViewManagerCloseOnApply(): bool
    {
        return $this->bool('view_manager.close_on_apply', true);
    }

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function modifyViewManagerActionUsing(?Closure $callback): static
    {
        $this->modifyViewManagerActionUsing = $callback;

        return $this;
    }

    public function getModifyViewManagerActionUsing(): ?Closure
    {
        return $this->modifyViewManagerActionUsing;
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-sort
    |--------------------------------------------------------------------------
    */

    public function multiSort(bool | Closure $condition = true): static
    {
        return $this->set('multi_sort.enabled', $condition);
    }

    public function isMultiSortEnabled(): bool
    {
        return $this->bool('multi_sort.enabled', true);
    }

    public function multiSortMaxColumns(int | Closure | null $max): static
    {
        return $this->set('multi_sort.max_columns', $max);
    }

    public function getMultiSortMaxColumns(): ?int
    {
        $max = $this->value('multi_sort.max_columns', 5);

        return $max === null ? null : (int) $max;
    }

    public function multiSortIcon(string | BackedEnum | Closure $icon): static
    {
        return $this->set('multi_sort.icon', $icon);
    }

    public function getMultiSortIcon(): string | BackedEnum
    {
        return $this->value('multi_sort.icon', 'heroicon-o-arrows-up-down');
    }

    /*
    |--------------------------------------------------------------------------
    | Quick filters
    |--------------------------------------------------------------------------
    */

    public function quickFilters(bool | Closure $condition = true): static
    {
        return $this->set('quick_filters.enabled', $condition);
    }

    public function isQuickFiltersEnabled(): bool
    {
        return $this->bool('quick_filters.enabled', false);
    }

    public function quickFilterPinning(bool | Closure $condition = true): static
    {
        return $this->set('quick_filters.pinning', $condition);
    }

    public function isQuickFilterPinningEnabled(): bool
    {
        return $this->bool('quick_filters.pinning', true);
    }

    /**
     * @param  array<string> | Closure  $filters
     */
    public function pinnedFilters(array | Closure $filters): static
    {
        return $this->set('quick_filters.pinned', $filters);
    }

    /**
     * @return array<string>
     */
    public function getPinnedFilters(): array
    {
        return array_values((array) $this->value('quick_filters.pinned', []));
    }

    /*
    |--------------------------------------------------------------------------
    | Advanced search
    |--------------------------------------------------------------------------
    */

    public function advancedSearch(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.enabled', $condition);
    }

    public function isAdvancedSearchEnabled(): bool
    {
        return $this->bool('advanced_search.enabled', false);
    }

    public function advancedSearchDefaultConstraint(SearchConstraint | string | Closure $constraint): static
    {
        return $this->set('advanced_search.default_constraint', $constraint);
    }

    public function getAdvancedSearchDefaultConstraint(): SearchConstraint
    {
        $constraint = $this->value('advanced_search.default_constraint', SearchConstraint::Contains);

        return $constraint instanceof SearchConstraint ? $constraint : (SearchConstraint::tryFrom((string) $constraint) ?? SearchConstraint::Contains);
    }

    /**
     * @param  array<SearchConstraint | string> | Closure | null  $constraints
     */
    public function advancedSearchConstraints(array | Closure | null $constraints): static
    {
        return $this->set('advanced_search.constraints', $constraints);
    }

    /**
     * @return array<SearchConstraint>
     */
    public function getAdvancedSearchConstraints(): array
    {
        $constraints = $this->value('advanced_search.constraints') ?? SearchConstraint::cases();

        return collect($constraints)
            ->map(fn (SearchConstraint | string $constraint): ?SearchConstraint => $constraint instanceof SearchConstraint ? $constraint : SearchConstraint::tryFrom($constraint))
            ->filter()
            ->values()
            ->all();
    }

    public function advancedSearchColumnSelection(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.column_selection', $condition);
    }

    public function isAdvancedSearchColumnSelectionEnabled(): bool
    {
        return $this->bool('advanced_search.column_selection', false);
    }

    public function advancedSearchKeyboardSyntax(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.keyboard_syntax', $condition);
    }

    public function isAdvancedSearchKeyboardSyntaxEnabled(): bool
    {
        return $this->bool('advanced_search.keyboard_syntax', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Loading overlay
    |--------------------------------------------------------------------------
    */

    public function loadingOverlay(bool | Closure $condition = true): static
    {
        return $this->set('loading_overlay.enabled', $condition);
    }

    public function isLoadingOverlayEnabled(): bool
    {
        return $this->bool('loading_overlay.enabled', false);
    }

    public function loadingOverlaySkeletonRows(int | Closure $rows): static
    {
        return $this->set('loading_overlay.skeleton_rows', $rows);
    }

    public function getLoadingOverlaySkeletonRows(): int
    {
        return max(1, (int) $this->value('loading_overlay.skeleton_rows', 5));
    }

    /*
    |--------------------------------------------------------------------------
    | User views resource
    |--------------------------------------------------------------------------
    */

    public function userViewsResource(bool | Closure $condition = true): static
    {
        return $this->set('resource.enabled', $condition);
    }

    public function isUserViewsResourceEnabled(): bool
    {
        return $this->bool('resource.enabled', false);
    }

    /**
     * @param  class-string<UserViewResource> | Closure  $resource
     */
    public function userViewsResourceClass(string | Closure $resource): static
    {
        return $this->set('resource.class', $resource);
    }

    /**
     * @return class-string<UserViewResource>
     */
    public function getUserViewsResource(): string
    {
        return $this->value('resource.class', UserViewResource::class);
    }

    public function userViewsNavigationGroup(string | Closure | null $group): static
    {
        return $this->set('resource.navigation_group', $group);
    }

    public function getUserViewsNavigationGroup(): ?string
    {
        return $this->value('resource.navigation_group');
    }

    public function userViewsNavigationLabel(string | Closure | null $label): static
    {
        return $this->set('resource.navigation_label', $label);
    }

    public function getUserViewsNavigationLabel(): string
    {
        return $this->value('resource.navigation_label') ?? __('filament-dynamic-tables::dynamic-tables.resource.navigation_label');
    }

    public function userViewsNavigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        return $this->set('resource.navigation_icon', $icon);
    }

    public function getUserViewsNavigationIcon(): string | BackedEnum | null
    {
        return $this->value('resource.navigation_icon', 'heroicon-o-bookmark-square');
    }

    public function userViewsNavigationSort(int | Closure | null $sort): static
    {
        return $this->set('resource.navigation_sort', $sort);
    }

    public function getUserViewsNavigationSort(): ?int
    {
        $sort = $this->value('resource.navigation_sort');

        return $sort === null ? null : (int) $sort;
    }

    public function userViewsNavigationBadge(bool | Closure $condition = true): static
    {
        return $this->set('resource.navigation_badge', $condition);
    }

    public function hasUserViewsNavigationBadge(): bool
    {
        return $this->bool('resource.navigation_badge', true);
    }

    public function userViewsSlug(string | Closure $slug): static
    {
        return $this->set('resource.slug', $slug);
    }

    public function getUserViewsSlug(): string
    {
        return $this->value('resource.slug', 'dynamic-table-views');
    }

    /**
     * Restrict who can open the user views resource, e.g.
     * ->canAccessUserViewsResourceUsing(fn ($user) => $user->is_admin).
     */
    public function canAccessUserViewsResourceUsing(?Closure $callback): static
    {
        $this->canAccessResourceUsing = $callback;

        return $this;
    }

    public function getCanAccessUserViewsResourceUsing(): ?Closure
    {
        return $this->canAccessResourceUsing;
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    public function notifications(bool | Closure $condition = true): static
    {
        return $this->set('notifications.enabled', $condition);
    }

    public function hasNotifications(): bool
    {
        return $this->bool('notifications.enabled', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Users, models & tenancy (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  class-string<Model>  $model
     */
    public function user(string $model): static
    {
        config(['filament-dynamic-tables.users.model' => $model]);

        return $this;
    }

    public function userTable(string $table): static
    {
        config(['filament-dynamic-tables.users.table' => $table]);

        return $this;
    }

    public function userTableNameColumn(string $column): static
    {
        config(['filament-dynamic-tables.users.name_attribute' => $column]);

        return $this;
    }

    public function userTableKeyColumn(string $column): static
    {
        config(['filament-dynamic-tables.users.key_column' => $column]);

        return $this;
    }

    public function authGuard(?string $guard): static
    {
        config(['filament-dynamic-tables.users.guard' => $guard]);

        return $this;
    }

    /**
     * @param  class-string<UserView>  $model
     */
    public function userView(string $model): static
    {
        config(['filament-dynamic-tables.models.user_view' => $model]);

        return $this;
    }

    /**
     * Stores favorites, sort order and default views per user, for both
     * user views and preset views.
     *
     * @param  class-string<ManagedView>  $model
     */
    public function managedUserView(string $model): static
    {
        config(['filament-dynamic-tables.models.managed_view' => $model]);

        return $this;
    }

    /**
     * @param  class-string<ManagedView>  $model
     */
    public function managedPresetView(string $model): static
    {
        return $this->managedUserView($model);
    }

    /**
     * Scope views to a tenant: Filament's tenancy, simple one-to-many
     * tenancy (the tenant model's getTenantId()), spatie/laravel-multitenancy
     * (Tenant::current()) or stancl/tenancy (tenant()).
     *
     * @param  class-string<Model> | null  $model
     */
    public function tenant(?string $model): static
    {
        $this->set('tenancy.model', $model);

        return $this->tenancy(filled($model));
    }

    public function getTenantModel(): ?string
    {
        return $this->value('tenancy.model');
    }

    public function tenantColumn(string $column): static
    {
        config(['filament-dynamic-tables.tenancy.column' => $column]);

        return $this;
    }

    /**
     * Pass false to share views across all tenants.
     */
    public function scopeToTenancy(bool | Closure $condition = true): static
    {
        return $this->set('tenancy.scope', $condition);
    }

    public function isScopedToTenancy(): bool
    {
        return $this->bool('tenancy.scope', true);
    }

    /*
    |--------------------------------------------------------------------------
    | User views (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function userViewsEnabled(bool | Closure $condition = true): static
    {
        return $this->userViews($condition);
    }

    public function quickSaveIconSelect(bool | Closure $condition = true): static
    {
        return $this->iconSelect($condition);
    }

    public function quickSaveIncludeOutlineIcons(bool | Closure $condition = true): static
    {
        return $this->set('user_views.include_outline_icons', $condition);
    }

    public function includesOutlineIcons(): bool
    {
        return $this->bool('user_views.include_outline_icons', true);
    }

    public function quickSaveIncludeSolidIcons(bool | Closure $condition = true): static
    {
        return $this->set('user_views.include_solid_icons', $condition);
    }

    public function includesSolidIcons(): bool
    {
        return $this->bool('user_views.include_solid_icons', true);
    }

    /**
     * @param  array<string> | Closure | null  $colors
     */
    public function quickSaveColors(array | Closure | null $colors): static
    {
        return $this->colors($colors);
    }

    public function quickSaveColorPicker(bool | Closure $condition = true): static
    {
        return $this->colorSelect($condition);
    }

    public function quickSaveMakeFavorite(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.make_favorite', $condition);
    }

    public function canQuickSaveMakeFavorite(): bool
    {
        return $this->bool('quick_save.make_favorite', true);
    }

    public function quickSaveMakePublic(bool | Closure $condition = true): static
    {
        return $this->publicViews($condition);
    }

    public function quickSaveMakeGlobalFavorite(bool | Closure $condition = true): static
    {
        return $this->globalFavorites($condition);
    }

    /**
     * Whether users may reorder global favorites and remove them from their
     * own favorites bar.
     */
    public function globalUserViewsManageable(bool | Closure $condition = true): static
    {
        return $this->set('user_views.global_manageable', $condition);
    }

    public function areGlobalUserViewsManageable(): bool
    {
        return $this->bool('user_views.global_manageable', true);
    }

    /**
     * Where a new global favorite lands among a user's favorites: 'before' or 'after'.
     */
    public function newGlobalUserViewSortPosition(string | Closure $position): static
    {
        return $this->set('user_views.new_global_sort_position', $position);
    }

    public function getNewGlobalUserViewSortPosition(): string
    {
        return $this->value('user_views.new_global_sort_position', 'before') === 'after' ? 'after' : 'before';
    }

    public function quickSaveNameHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.name', $condition);
    }

    public function quickSaveFiltersHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.filters', $condition);
    }

    public function quickSavePublicHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.public', $condition);
    }

    public function quickSaveFavoriteHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.favorite', $condition);
    }

    public function quickSaveGlobalFavoriteHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.global_favorite', $condition);
    }

    public function quickSaveActivePresetViewHelperText(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.helper_texts.active_preset_view', $condition);
    }

    /**
     * @param  'name'|'filters'|'public'|'favorite'|'global_favorite'|'active_preset_view'  $field
     */
    public function hasQuickSaveHelperText(string $field): bool
    {
        return $this->bool("quick_save.helper_texts.{$field}", in_array($field, ['public', 'favorite', 'global_favorite'], true));
    }

    /**
     * The status new public views and global favorites start with.
     */
    public function initialStatus(ViewStatus | string | Closure $status): static
    {
        return $this->set('user_views.initial_status', $status);
    }

    public function getInitialStatus(): ViewStatus
    {
        $status = $this->value('user_views.initial_status');

        if ($status !== null) {
            return $status instanceof ViewStatus ? $status : (ViewStatus::tryFrom((string) $status) ?? ViewStatus::Pending);
        }

        return ViewStatus::Pending;
    }

    /**
     * Public views and global favorites are shown to others once their
     * status is at least this one (rejected < pending < approved).
     */
    public function minimumStatusForDisplay(ViewStatus | string | Closure $status): static
    {
        return $this->set('user_views.minimum_status_for_display', $status);
    }

    public function getMinimumStatusForDisplay(): ViewStatus
    {
        $status = $this->value('user_views.minimum_status_for_display');

        if ($status !== null) {
            return $status instanceof ViewStatus ? $status : (ViewStatus::tryFrom((string) $status) ?? ViewStatus::Pending);
        }

        return ($this->doPublicViewsRequireApproval() || $this->doGlobalFavoritesRequireApproval())
            ? ViewStatus::Approved
            : ViewStatus::Pending;
    }

    /**
     * @return array<string>
     */
    public function getDisplayableStatuses(): array
    {
        return collect(ViewStatus::cases())
            ->filter(fn (ViewStatus $status): bool => $this->isStatusDisplayable($status))
            ->map(fn (ViewStatus $status): string => $status->value)
            ->values()
            ->all();
    }

    public function hasExplicitInitialStatus(): bool
    {
        return $this->value('user_views.initial_status') !== null;
    }

    /**
     * Whether a status is high enough to show a view to other users.
     */
    public function isStatusDisplayable(ViewStatus $status): bool
    {
        return $status->rank() >= $this->getMinimumStatusForDisplay()->rank();
    }

    /*
    |--------------------------------------------------------------------------
    | Preset views (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    /**
     * Whether users may reorder preset views and add or remove them from
     * their favorites bar.
     */
    public function presetViewsManageable(bool | Closure $condition = true): static
    {
        return $this->set('preset_views.manageable', $condition);
    }

    public function arePresetViewsManageable(): bool
    {
        return $this->bool('preset_views.manageable', true);
    }

    public function newPresetViewSortPosition(string | Closure $position): static
    {
        return $this->set('preset_views.new_sort_position', $position);
    }

    public function getNewPresetViewSortPosition(): string
    {
        return $this->value('preset_views.new_sort_position', 'before') === 'after' ? 'after' : 'before';
    }

    /**
     * Pass false to stop users from saving a view while a preset view is
     * active (its query can't be stored in a user view).
     */
    public function createUsingPresetView(bool | Closure $condition = true): static
    {
        return $this->set('preset_views.create_using', $condition);
    }

    public function canCreateUsingPresetView(): bool
    {
        return $this->bool('preset_views.create_using', true);
    }

    /**
     * Show a lock icon next to preset views.
     */
    public function presetViewLockIcon(string | BackedEnum | bool | Closure | null $icon = true): static
    {
        return $this->set('preset_views.lock_icon', $icon);
    }

    public function getPresetViewLockIcon(): string | BackedEnum | null
    {
        $icon = $this->value('preset_views.lock_icon', false);

        if (($icon === false) || ($icon === null)) {
            return null;
        }

        if ($icon === true) {
            return DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::PRESET_LOCKED_INDICATOR, 'heroicon-o-lock-closed');
        }

        return $icon;
    }

    /*
    |--------------------------------------------------------------------------
    | Favorites bar (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function favoritesBarEnabled(bool | Closure $condition = true): static
    {
        return $this->favoritesBar($condition);
    }

    public function favoritesBarDefaultView(bool | Closure $condition = true): static
    {
        return $this->defaultView($condition);
    }

    /*
    |--------------------------------------------------------------------------
    | Quick save (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function quickSaveEnabled(bool | Closure $condition = true): static
    {
        return $this->quickSave($condition);
    }

    /**
     * Show the quick save button in the favorites bar, optionally with
     * another icon or at the 'start' of the bar instead of the 'end'.
     */
    public function quickSaveInFavoritesBar(bool | Closure $condition = true, string | BackedEnum | null $icon = null, ?string $position = null): static
    {
        $this->set('quick_save.in_favorites_bar', $condition);

        if ($icon !== null) {
            $this->quickSaveIcon($icon);
        }

        if ($position !== null) {
            $this->set('quick_save.favorites_bar_position', $position);
        }

        return $this;
    }

    public function isQuickSaveInFavoritesBar(): bool
    {
        return $this->bool('quick_save.in_favorites_bar', $this->getQuickSavePosition() === ActionPosition::FavoritesBar);
    }

    public function getQuickSaveFavoritesBarPosition(): string
    {
        return $this->value('quick_save.favorites_bar_position', 'end') === 'start' ? 'start' : 'end';
    }

    /**
     * Show the quick save button in the table toolbar.
     */
    public function quickSaveInTable(bool | Closure $condition = true): static
    {
        return $this->set('quick_save.in_table', $condition);
    }

    public function isQuickSaveInTable(): bool
    {
        return $this->bool('quick_save.in_table', $this->getQuickSavePosition() === ActionPosition::Toolbar);
    }

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function quickSaveTriggerAction(?Closure $callback): static
    {
        return $this->modifyQuickSaveActionUsing($callback);
    }

    /*
    |--------------------------------------------------------------------------
    | View manager (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function viewManagerEnabled(bool | Closure $condition = true): static
    {
        return $this->viewManager($condition);
    }

    public function viewManagerInFavoritesBar(bool | Closure $condition = true, ?string $position = null): static
    {
        $this->set('view_manager.in_favorites_bar', $condition);

        if ($position !== null) {
            $this->set('view_manager.favorites_bar_position', $position);
        }

        return $this;
    }

    public function isViewManagerInFavoritesBar(): bool
    {
        return $this->bool('view_manager.in_favorites_bar', $this->getViewManagerPosition() === ActionPosition::FavoritesBar);
    }

    public function getViewManagerFavoritesBarPosition(): string
    {
        return $this->value('view_manager.favorites_bar_position', 'end') === 'start' ? 'start' : 'end';
    }

    /**
     * Show the view manager in the table toolbar. $position is a Filament
     * table render hook, such as 'tables::toolbar.toggle-column-trigger.before'.
     */
    public function viewManagerInTable(bool | Closure $condition = true, ?string $position = null): static
    {
        $this->set('view_manager.in_table', $condition);

        if ($position !== null) {
            $this->set('view_manager.table_position', $position);
        }

        return $this;
    }

    public function isViewManagerInTable(): bool
    {
        return $this->bool('view_manager.in_table', $this->getViewManagerPosition() === ActionPosition::Toolbar);
    }

    public function getViewManagerTablePosition(): ?string
    {
        return $this->value('view_manager.table_position');
    }

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function viewManagerTriggerAction(?Closure $callback): static
    {
        return $this->modifyViewManagerActionUsing($callback);
    }

    public function viewManagerSearch(bool | Closure $condition = true): static
    {
        return $this->viewManagerSearchable($condition);
    }

    /**
     * A "Save view" link in the view manager dropdown.
     */
    public function viewManagerSaveView(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.save_view', $condition);
    }

    public function hasViewManagerSaveView(): bool
    {
        return $this->bool('view_manager.save_view', false);
    }

    /**
     * A "Reset" link in the view manager that loads the default view.
     */
    public function viewManagerResetView(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.reset_view', $condition);
    }

    public function hasViewManagerResetView(): bool
    {
        return $this->bool('view_manager.reset_view', false);
    }

    public function viewManagerClickToApply(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.click_to_apply', $condition);
    }

    public function isViewManagerClickToApply(): bool
    {
        return $this->bool('view_manager.click_to_apply', true);
    }

    public function viewManagerApplyButton(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.apply_button', $condition);
    }

    public function hasViewManagerApplyButton(): bool
    {
        return $this->bool('view_manager.apply_button', true);
    }

    public function viewManagerActiveViewIndicator(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.active_view_indicator', $condition);
    }

    public function hasViewManagerActiveViewIndicator(): bool
    {
        return $this->bool('view_manager.active_view_indicator', true);
    }

    public function viewManagerActiveViewBadge(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.active_view_badge', $condition);
    }

    public function hasViewManagerActiveViewBadge(): bool
    {
        return $this->bool('view_manager.active_view_badge', false);
    }

    public function viewManagerViewTypeIcons(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.view_type_icons', $condition);
    }

    public function hasViewManagerViewTypeIcons(): bool
    {
        return $this->bool('view_manager.view_type_icons', true) && (! $this->hasViewManagerViewTypeBadges());
    }

    public function viewManagerViewTypeBadges(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.view_type_badges', $condition);
    }

    public function hasViewManagerViewTypeBadges(): bool
    {
        return $this->bool('view_manager.view_type_badges', false);
    }

    public function viewManagerPublicIndicatorWhenGlobal(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.public_indicator_when_global', $condition);
    }

    public function showsViewManagerPublicIndicatorWhenGlobal(): bool
    {
        return $this->bool('view_manager.public_indicator_when_global', false);
    }

    /**
     * Show each view's own icon in the view manager.
     */
    public function viewIcon(bool | Closure $condition = true): static
    {
        return $this->set('view_manager.view_icon', $condition);
    }

    public function hasViewIcon(): bool
    {
        return $this->bool('view_manager.view_icon', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-sort, quick filters, loading overlay (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function multiSortEnabled(bool | Closure $condition = true): static
    {
        return $this->multiSort($condition);
    }

    protected ?Closure $modifyMultiSortActionUsing = null;

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function multiSortTriggerAction(?Closure $callback): static
    {
        $this->modifyMultiSortActionUsing = $callback;

        return $this;
    }

    public function getModifyMultiSortActionUsing(): ?Closure
    {
        return $this->modifyMultiSortActionUsing;
    }

    public function quickFiltersEnabled(bool | Closure $condition = true): static
    {
        return $this->quickFilters($condition);
    }

    public function tableLoadingOverlay(bool | Closure $condition = true): static
    {
        return $this->loadingOverlay($condition);
    }

    /*
    |--------------------------------------------------------------------------
    | Advanced search (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function advancedSearchEnabled(bool | Closure $condition = true): static
    {
        return $this->advancedSearch($condition);
    }

    /**
     * Let users pick which columns to search.
     */
    public function advancedSearchColumnsEnabled(bool | Closure $condition = true): static
    {
        return $this->advancedSearchColumnSelection($condition);
    }

    /**
     * AND / OR search groups.
     */
    public function advancedSearchGroupingEnabled(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.grouping', $condition);
    }

    public function isAdvancedSearchGroupingEnabled(): bool
    {
        return $this->bool('advanced_search.grouping', false);
    }

    public function advancedSearchMaxGroups(int | Closure $max): static
    {
        return $this->set('advanced_search.max_groups', $max);
    }

    public function getAdvancedSearchMaxGroups(): int
    {
        return max(1, (int) $this->value('advanced_search.max_groups', 5));
    }

    /**
     * Typing & (and) or | (or) creates a new group.
     */
    public function advancedSearchBooleanSyntaxEnabled(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.boolean_syntax', $condition);
    }

    public function isAdvancedSearchBooleanSyntaxEnabled(): bool
    {
        return $this->isAdvancedSearchGroupingEnabled() && $this->bool('advanced_search.boolean_syntax', false);
    }

    /**
     * The = - ^ $ shortcuts typed in front of a search term.
     */
    public function advancedSearchConstraintSyntaxEnabled(bool | Closure $condition = true): static
    {
        return $this->advancedSearchKeyboardSyntax($condition);
    }

    public function advancedSearchMaxWords(int | Closure | null $max): static
    {
        return $this->set('advanced_search.max_words', $max);
    }

    public function getAdvancedSearchMaxWords(): ?int
    {
        $max = $this->value('advanced_search.max_words');

        return $max === null ? null : max(1, (int) $max);
    }

    public function advancedSearchDropdownColumnsFirst(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.dropdown_columns_first', $condition);
    }

    public function isAdvancedSearchDropdownColumnsFirst(): bool
    {
        return $this->bool('advanced_search.dropdown_columns_first', false);
    }

    public function advancedSearchDropdownOpenOnFocus(bool | Closure $condition = true): static
    {
        return $this->set('advanced_search.dropdown_open_on_focus', $condition);
    }

    public function doesAdvancedSearchDropdownOpenOnFocus(): bool
    {
        return $this->bool('advanced_search.dropdown_open_on_focus', false);
    }

    /**
     * Keyboard shortcuts that focus the search input, e.g. ['command+k', 'ctrl+k'].
     *
     * @param  array<string> | Closure  $keybindings
     */
    public function advancedSearchKeybindings(array | Closure $keybindings): static
    {
        return $this->set('advanced_search.keybindings', $keybindings);
    }

    /**
     * @return array<string>
     */
    public function getAdvancedSearchKeybindings(): array
    {
        return array_values((array) $this->value('advanced_search.keybindings', []));
    }

    protected ?Closure $modifyAdvancedSearchActionUsing = null;

    /**
     * @param  Closure(Action): mixed  $callback
     */
    public function advancedSearchTriggerAction(?Closure $callback): static
    {
        $this->modifyAdvancedSearchActionUsing = $callback;

        return $this;
    }

    public function getModifyAdvancedSearchActionUsing(): ?Closure
    {
        return $this->modifyAdvancedSearchActionUsing;
    }

    /*
    |--------------------------------------------------------------------------
    | Filter builder
    |--------------------------------------------------------------------------
    */

    /**
     * CSS declarations for the absolutely positioned "Expand view" link,
     * e.g. ['right: 100px', 'top: 24px'].
     *
     * @param  array<string> | Closure  $styles
     */
    public function filterBuilderExpandViewStyles(array | Closure $styles): static
    {
        return $this->set('filter_builder.expand_view_styles', $styles);
    }

    /**
     * @return array<string>
     */
    public function getFilterBuilderExpandViewStyles(): array
    {
        return array_values(array_filter((array) $this->value('filter_builder.expand_view_styles', [])));
    }

    /*
    |--------------------------------------------------------------------------
    | User views resource (Advanced Tables compatible names)
    |--------------------------------------------------------------------------
    */

    public function resourceEnabled(bool | Closure $condition = true): static
    {
        return $this->userViewsResource($condition);
    }

    public function resourceNavigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        return $this->userViewsNavigationIcon($icon);
    }

    public function resourceNavigationGroup(string | Closure | null $group): static
    {
        return $this->userViewsNavigationGroup($group);
    }

    public function resourceNavigationSort(int | Closure | null $sort): static
    {
        return $this->userViewsNavigationSort($sort);
    }

    public function resourceNavigationBadge(bool | Closure $condition = true): static
    {
        return $this->userViewsNavigationBadge($condition);
    }

    /**
     * Load every user up front in the resource's user filter. Turn off for
     * large user bases: the filter then searches as you type.
     */
    public function resourceLoadAllUsers(bool | Closure $condition = true): static
    {
        return $this->set('resource.load_all_users', $condition);
    }

    public function shouldResourceLoadAllUsers(): bool
    {
        return $this->bool('resource.load_all_users', true);
    }

    /**
     * Show views of tables from these panels in this panel's resource.
     *
     * @param  array<string> | Closure  $panels
     */
    public function resourcePanels(array | Closure $panels): static
    {
        return $this->set('resource.panels', $panels);
    }

    /**
     * @return array<string> | null
     */
    public function getResourcePanels(): ?array
    {
        $panels = $this->value('resource.panels');

        return $panels === null ? null : array_values((array) $panels);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function resolvePosition(mixed $position): ActionPosition
    {
        return $position instanceof ActionPosition ? $position : (ActionPosition::tryFrom((string) $position) ?? ActionPosition::FavoritesBar);
    }

    /**
     * Every Heroicon, outline and / or solid, as icon name => label.
     *
     * @return array<string, string>
     */
    public static function heroicons(bool $outline = true, bool $solid = true): array
    {
        static $cache = [];

        $cacheKey = ($outline ? 'o' : '') . ($solid ? 's' : '');

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (! enum_exists(Heroicon::class)) {
            return $cache[$cacheKey] = static::defaultIcons();
        }

        $names = collect(Heroicon::cases())
            ->map(fn (Heroicon $icon): string => (string) str($icon->value)->after('o-'))
            ->filter(fn (string $name): bool => ! str_contains($name, '-') || ! preg_match('/^[a-z]-/', $name))
            ->unique()
            ->sort()
            ->values();

        $icons = [];

        foreach ($names as $name) {
            $label = (string) str($name)->replace('-', ' ')->ucfirst();

            if ($outline) {
                $icons["heroicon-o-{$name}"] = $label . ($solid ? ' (' . __('filament-dynamic-tables::dynamic-tables.form.icon_outline') . ')' : '');
            }

            if ($solid) {
                $icons["heroicon-s-{$name}"] = $label . ($outline ? ' (' . __('filament-dynamic-tables::dynamic-tables.form.icon_solid') . ')' : '');
            }
        }

        return $cache[$cacheKey] = filled($icons) ? $icons : static::defaultIcons();
    }

    /**
     * @return array<string, string>
     */
    public static function defaultIcons(): array
    {
        $icons = [
            'heroicon-o-star', 'heroicon-o-heart', 'heroicon-o-bookmark', 'heroicon-o-flag', 'heroicon-o-bolt',
            'heroicon-o-fire', 'heroicon-o-sparkles', 'heroicon-o-check-circle', 'heroicon-o-x-circle',
            'heroicon-o-exclamation-triangle', 'heroicon-o-clock', 'heroicon-o-calendar', 'heroicon-o-calendar-days',
            'heroicon-o-user', 'heroicon-o-users', 'heroicon-o-user-group', 'heroicon-o-building-office',
            'heroicon-o-shopping-cart', 'heroicon-o-shopping-bag', 'heroicon-o-truck', 'heroicon-o-banknotes',
            'heroicon-o-currency-dollar', 'heroicon-o-credit-card', 'heroicon-o-chart-bar', 'heroicon-o-chart-pie',
            'heroicon-o-arrow-trending-up', 'heroicon-o-arrow-trending-down', 'heroicon-o-inbox', 'heroicon-o-archive-box',
            'heroicon-o-document-text', 'heroicon-o-folder', 'heroicon-o-tag', 'heroicon-o-map-pin', 'heroicon-o-globe-alt',
            'heroicon-o-envelope', 'heroicon-o-phone', 'heroicon-o-bell', 'heroicon-o-eye', 'heroicon-o-eye-slash',
            'heroicon-o-lock-closed', 'heroicon-o-shield-check', 'heroicon-o-trophy', 'heroicon-o-rocket-launch',
            'heroicon-o-light-bulb', 'heroicon-o-funnel', 'heroicon-o-adjustments-horizontal', 'heroicon-o-table-cells',
            'heroicon-o-queue-list', 'heroicon-o-squares-2x2', 'heroicon-o-cube', 'heroicon-o-wrench-screwdriver',
        ];

        return collect($icons)
            ->mapWithKeys(fn (string $icon): array => [$icon => (string) str($icon)->after('heroicon-o-')->headline()])
            ->all();
    }
}
