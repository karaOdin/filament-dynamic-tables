<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Enums\ViewType;
use KaraOdin\FilamentDynamicTables\Models\ManagedView;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;
use KaraOdin\FilamentDynamicTables\Support\ViewItem;
use Throwable;

trait HasDynamicTableViews
{
    /**
     * The active view: "default", "preset:{key}" or "user:{id}".
     */
    public ?string $activeDynamicTableView = null;

    /**
     * Fingerprint of the table state right after the active view was applied.
     */
    public ?string $dynamicTableViewHash = null;

    /** @var array<string, PresetView> | null */
    protected ?array $cachedDynamicPresetViews = null;

    /** @var Collection<int, UserView> | null */
    protected ?Collection $cachedDynamicUserViews = null;

    /** @var Collection<string, ManagedView> | null */
    protected ?Collection $cachedDynamicManagedViews = null;

    /** @var Collection<int, ViewItem> | null */
    protected ?Collection $cachedDynamicViewItems = null;

    /**
     * Override to define preset views.
     *
     * @return array<string, PresetView>
     */
    public function getPresetViews(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Resolving views
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, PresetView>
     */
    public function getDynamicTablePresetViews(): array
    {
        if ($this->cachedDynamicPresetViews !== null) {
            return $this->cachedDynamicPresetViews;
        }

        if (! $this->getDynamicTablesConfig()->isPresetViewsEnabled()) {
            return $this->cachedDynamicPresetViews = [];
        }

        $presets = [];

        foreach ($this->getPresetViews() as $key => $preset) {
            $key = (string) $key;
            $preset->key($key)->livewire($this);

            if ($preset->isHidden()) {
                continue;
            }

            $presets[$key] = $preset;
        }

        return $this->cachedDynamicPresetViews = $presets;
    }

    public function getDynamicTablePresetView(?string $key): ?PresetView
    {
        if (blank($key)) {
            return null;
        }

        return $this->getDynamicTablePresetViews()[$key] ?? null;
    }

    /**
     * User views visible to the current user for this table.
     *
     * @return Collection<int, UserView>
     */
    public function getDynamicTableUserViews(): Collection
    {
        if ($this->cachedDynamicUserViews !== null) {
            return $this->cachedDynamicUserViews;
        }

        $config = $this->getDynamicTablesConfig();
        $user = $this->getDynamicTablesUser();

        if ((! $user) || (! $config->isUserViewsEnabled())) {
            return $this->cachedDynamicUserViews = collect();
        }

        try {
            return $this->cachedDynamicUserViews = Models::userView()::query()
                ->forTable($this->getDynamicTableKey())
                ->forTenant($this->getDynamicTablesTenantKey())
                ->visibleTo($user, $config->isPublicViewsEnabled(), $config->isGlobalFavoritesEnabled(), $config->getDisplayableStatuses())
                ->with('user')
                ->orderByRaw('sort_order is null')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return $this->cachedDynamicUserViews = collect();
        }
    }

    public function findDynamicTableUserView(int | string $id): ?UserView
    {
        return $this->getDynamicTableUserViews()->first(fn (UserView $view): bool => (string) $view->getKey() === (string) $id);
    }

    /**
     * @return Collection<string, ManagedView>
     */
    protected function getDynamicTableManagedViews(): Collection
    {
        if ($this->cachedDynamicManagedViews !== null) {
            return $this->cachedDynamicManagedViews;
        }

        $user = $this->getDynamicTablesUser();

        if (! $user) {
            return $this->cachedDynamicManagedViews = collect();
        }

        try {
            return $this->cachedDynamicManagedViews = Models::managedView()::query()
                ->forContext($user, $this->getDynamicTableKey(), $this->getDynamicTablesTenantKey())
                ->get()
                ->keyBy(fn (ManagedView $view): string => $view->getViewKeyString());
        } catch (Throwable $exception) {
            report($exception);

            return $this->cachedDynamicManagedViews = collect();
        }
    }

    protected function flushDynamicTableViewsCache(): void
    {
        $this->cachedDynamicUserViews = null;
        $this->cachedDynamicManagedViews = null;
        $this->cachedDynamicViewItems = null;
    }

    /**
     * Every view the current user can apply, normalised for rendering.
     *
     * @return Collection<int, ViewItem>
     */
    public function getDynamicTableViewItems(): Collection
    {
        if ($this->cachedDynamicViewItems !== null) {
            return $this->cachedDynamicViewItems;
        }

        $config = $this->getDynamicTablesConfig();
        $managed = $this->getDynamicTableManagedViews();
        $user = $this->getDynamicTablesUser();
        $isManagedDefault = $config->isManagedDefaultViewsEnabled();
        $hasUserDefault = $isManagedDefault && $managed->contains(fn (ManagedView $view): bool => $view->is_default);
        $isModified = $this->isActiveDynamicTableViewModified();
        $order = 0;
        $items = collect();

        $arePresetsManageable = $config->arePresetViewsManageable();
        $areGlobalsManageable = $config->areGlobalUserViewsManageable();
        $newPresetPosition = $config->getNewPresetViewSortPosition() === 'before' ? -1 : null;
        $newGlobalPosition = $config->getNewGlobalUserViewSortPosition() === 'before' ? -1 : null;

        foreach ($this->getDynamicTablePresetViews() as $key => $preset) {
            // A preset keyed "default" customizes the Default view instead.
            if ($key === 'default') {
                continue;
            }

            $viewKey = "preset:{$key}";
            $managedView = $arePresetsManageable ? $managed->get($viewKey) : null;

            $items->push(new ViewItem(
                key: $viewKey,
                type: ViewType::Preset,
                label: $preset->getLabel(),
                icon: $preset->getIcon(),
                activeIcon: $preset->getActiveIcon(),
                color: $preset->getColor(),
                tooltip: $preset->getTooltip(),
                badge: $preset->hasBadge() ? $this->resolvePresetViewBadge($preset) : null,
                badgeColor: $preset->getBadgeColor(),
                isFavorite: $managedView->is_favorite ?? $preset->isFavorite(),
                isDefault: $hasUserDefault ? (bool) $managed->get($viewKey)?->is_default : $preset->isDefault(),
                isActive: $this->activeDynamicTableView === $viewKey,
                isModified: ($this->activeDynamicTableView === $viewKey) && $isModified,
                sortOrder: $managedView->sort_order ?? ($managedView ? null : $newPresetPosition),
                isManageable: $arePresetsManageable,
                naturalOrder: $order++,
                presetView: $preset,
            ));
        }

        $userViews = $this->getDynamicTableUserViews()
            ->sortBy(fn (UserView $view): int => match (true) {
                $view->is_global_favorite => 0,
                $view->isOwnedBy($user) => 1,
                default => 2,
            });

        foreach ($userViews as $view) {
            $viewKey = $view->getKeyString();
            $managedView = $managed->get($viewKey);
            $isOwn = $view->isOwnedBy($user);
            $isGlobal = $view->is_global_favorite && $config->isGlobalFavoritesEnabled() && ($isOwn || $config->isStatusDisplayable($view->status));
            $isManageable = (! $isGlobal) || $areGlobalsManageable || $isOwn;

            if (! $isManageable) {
                $managedView = null;
            }

            $items->push(new ViewItem(
                key: $viewKey,
                type: ViewType::User,
                label: $view->name,
                icon: $view->icon,
                color: $view->color,
                isFavorite: $managedView->is_favorite ?? $isGlobal,
                isDefault: $isManagedDefault && (bool) $managedView?->is_default,
                isActive: $this->activeDynamicTableView === $viewKey,
                isModified: ($this->activeDynamicTableView === $viewKey) && $isModified,
                isOwn: $isOwn,
                isPublic: $view->is_public,
                isGlobalFavorite: $view->is_global_favorite,
                status: $view->status,
                ownerName: Users::nameOf($view->user),
                canEdit: $this->dynamicTablesCan('update', $view),
                canDelete: $this->dynamicTablesCan('delete', $view),
                sortOrder: $managedView->sort_order ?? ($isGlobal ? ($view->sort_order ?? $newGlobalPosition) : null),
                naturalOrder: 100 + $order++,
                isManageable: $isManageable,
                userView: $view,
            ));
        }

        return $this->cachedDynamicViewItems = $items;
    }

    protected function resolvePresetViewBadge(PresetView $preset): mixed
    {
        try {
            $query = $this->getTable()->getQuery();

            return $preset->getBadge($query instanceof Builder ? $query : null);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return Collection<int, ViewItem>
     */
    public function getDynamicTableFavoriteViewItems(): Collection
    {
        return $this->getDynamicTableViewItems()
            ->filter(fn (ViewItem $item): bool => $item->isFavorite && ($item->isOwn || (! $item->isRejected()) || $this->getDynamicTablesConfig()->isStatusDisplayable(ViewStatus::Rejected)))
            ->sortBy([
                // Preset views first, then global favorites users can't move, then the rest.
                fn (ViewItem $a, ViewItem $b): int => $a->getFavoritesGroup() <=> $b->getFavoritesGroup(),
                fn (ViewItem $a, ViewItem $b): int => ($a->sortOrder ?? PHP_INT_MAX) <=> ($b->sortOrder ?? PHP_INT_MAX),
                fn (ViewItem $a, ViewItem $b): int => $a->naturalOrder <=> $b->naturalOrder,
            ])
            ->values();
    }

    public function getDefaultDynamicTableViewItem(): ?ViewItem
    {
        $config = $this->getDynamicTablesConfig();

        if (! $config->isDefaultViewEnabled()) {
            return null;
        }

        $preset = $this->getDynamicTablePresetView('default');

        return new ViewItem(
            key: 'default',
            type: ViewType::Default,
            label: $preset?->hasCustomLabel() ? $preset->getLabel() : $config->getDefaultViewLabel(),
            icon: $preset?->getIcon() ?? $config->getDefaultViewIcon(),
            color: $preset?->getColor(),
            tooltip: $preset?->getTooltip(),
            badge: ($preset && $preset->hasBadge()) ? $this->resolvePresetViewBadge($preset) : null,
            badgeColor: $preset?->getBadgeColor(),
            presetView: $preset,
            isActive: blank($this->activeDynamicTableView) || ($this->activeDynamicTableView === 'default'),
            isModified: (blank($this->activeDynamicTableView) || ($this->activeDynamicTableView === 'default')) && $this->isActiveDynamicTableViewModified(),
        );
    }

    public function getDynamicTableViewItem(string $key): ?ViewItem
    {
        if ($key === 'default') {
            return $this->getDefaultDynamicTableViewItem();
        }

        return $this->getDynamicTableViewItems()->first(fn (ViewItem $item): bool => $item->key === $key);
    }

    public function getActiveDynamicTableViewItem(): ?ViewItem
    {
        if (blank($this->activeDynamicTableView)) {
            return null;
        }

        return $this->getDynamicTableViewItem($this->activeDynamicTableView);
    }

    public function getActiveDynamicTablePresetKey(): ?string
    {
        $active = $this->activeDynamicTableView;

        if (blank($active) || ($active === 'default')) {
            return $this->getDynamicTablePresetView('default') ? 'default' : null;
        }

        if (str_starts_with($active, 'preset:')) {
            return Str::after($active, 'preset:');
        }

        if (str_starts_with($active, 'user:')) {
            return $this->findDynamicTableUserView(Str::after($active, 'user:'))?->state['preset'] ?? null;
        }

        return null;
    }

    public function getActiveDynamicTablePresetView(): ?PresetView
    {
        return $this->getDynamicTablePresetView($this->getActiveDynamicTablePresetKey());
    }

    public function isActiveDynamicTableViewModified(): bool
    {
        if (! $this->getDynamicTablesConfig()->hasModifiedIndicator()) {
            return false;
        }

        if (blank($this->dynamicTableViewHash)) {
            return false;
        }

        return $this->hashDynamicTableState($this->captureDynamicTableState()) !== $this->dynamicTableViewHash;
    }

    /*
    |--------------------------------------------------------------------------
    | Applying views
    |--------------------------------------------------------------------------
    */

    public function applyDynamicTableView(string $key, bool $fromViewManager = false): void
    {
        if ($key === 'default') {
            $this->resetDynamicTableView();
        } elseif (str_starts_with($key, 'preset:')) {
            $preset = $this->getDynamicTablePresetView(Str::after($key, 'preset:'));

            if (! $preset) {
                return;
            }

            $this->activeDynamicTableView = $key;
            $this->applyDynamicTableState($this->getPresetViewState($preset));
        } elseif (str_starts_with($key, 'user:')) {
            $view = $this->findDynamicTableUserView(Str::after($key, 'user:'));

            if (! $view) {
                return;
            }

            if ((! $view->isOwnedBy($this->getDynamicTablesUser())) && (! $this->getDynamicTablesConfig()->isStatusDisplayable($view->status))) {
                return;
            }

            $this->activeDynamicTableView = $key;
            $this->applyDynamicTableState($view->state ?? []);
        } else {
            return;
        }

        $this->rememberAppliedDynamicTableView();

        if ($fromViewManager && $this->getDynamicTablesConfig()->doesViewManagerCloseOnApply()) {
            $this->unmountAction();
        }
    }

    public function resetDynamicTableView(): void
    {
        $this->activeDynamicTableView = $this->getDynamicTablesConfig()->isDefaultViewEnabled() ? 'default' : null;

        $preset = $this->getDynamicTablePresetView('default');

        $this->applyDynamicTableState($preset ? $this->getPresetViewState($preset) : $this->getDefaultDynamicTableState());
        $this->rememberAppliedDynamicTableView();
    }

    protected function rememberAppliedDynamicTableView(): void
    {
        $this->dynamicTableViewHash = $this->hashDynamicTableState($this->captureDynamicTableState());
        $this->cachedDynamicViewItems = null;

        session()->put($this->getDynamicTablesSessionKey('active_view'), [
            'key' => $this->activeDynamicTableView,
            'hash' => $this->dynamicTableViewHash,
        ]);
    }

    /**
     * Runs on the first render. When state already exists (query string or
     * Filament session persistence) the last active view is only re-selected,
     * so the modified indicator keeps working. Otherwise the session view
     * (if persisted), the user's default view or the preset marked
     * ->default() is applied.
     */
    protected function initializeDynamicTableView(bool $hasExistingState = false): void
    {
        $config = $this->getDynamicTablesConfig();
        $remembered = session()->get($this->getDynamicTablesSessionKey('active_view'));
        $rememberedKey = is_array($remembered) ? ($remembered['key'] ?? null) : null;

        $requested = $this->requestedDynamicTableView;

        if (filled($requested) && (($requested === 'default') || $this->getDynamicTableViewItem($requested))) {
            $this->applyDynamicTableView($requested);

            return;
        }

        if ($hasExistingState) {
            if (filled($rememberedKey) && (($rememberedKey === 'default') || $this->getDynamicTableViewItem($rememberedKey))) {
                $this->activeDynamicTableView = $rememberedKey;
                $this->dynamicTableViewHash = $remembered['hash'] ?? null;
            } else {
                $this->activeDynamicTableView = null;
                $this->dynamicTableViewHash = null;
            }

            return;
        }

        $candidates = [];

        if ($config->persistsActiveViewInSession()) {
            $candidates[] = $rememberedKey;
        }

        if ($config->isManagedDefaultViewsEnabled()) {
            $candidates[] = $this->getDynamicTableManagedViews()
                ->first(fn (ManagedView $view): bool => $view->is_default)
                ?->getViewKeyString();
        }

        $candidates[] = collect($this->getDynamicTablePresetViews())
            ->filter(fn (PresetView $preset): bool => $preset->isDefault())
            ->keys()
            ->map(fn (string $key): string => ($key === 'default') ? 'default' : "preset:{$key}")
            ->first();

        foreach (array_filter($candidates) as $candidate) {
            if ($candidate === 'default') {
                break;
            }

            if ($this->getDynamicTableViewItem($candidate)) {
                $this->applyDynamicTableView($candidate);

                return;
            }
        }

        if ($this->getDynamicTablePresetView('default')) {
            $this->resetDynamicTableView();

            return;
        }

        $this->activeDynamicTableView = $config->isDefaultViewEnabled() ? 'default' : null;
        $this->dynamicTableViewHash = $this->hashDynamicTableState($this->captureDynamicTableState());
    }

    /*
    |--------------------------------------------------------------------------
    | User views
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createDynamicTableUserView(array $data): ?UserView
    {
        $config = $this->getDynamicTablesConfig();
        $user = $this->getDynamicTablesUser();

        if ((! $user) || (! $config->isUserViewsEnabled()) || (! $this->dynamicTablesCan('create'))) {
            return null;
        }

        if ($this->isDynamicTableSaveBlockedByPresetView()) {
            $this->sendDynamicTablesNotification(
                __('filament-dynamic-tables::dynamic-tables.quick_save.preset_view_blocked'),
                'warning',
                __('filament-dynamic-tables::dynamic-tables.quick_save.preset_view_blocked_body'),
            );

            return null;
        }

        $attributes = $this->sanitizeDynamicTableUserViewData($data);
        $modelClass = Models::userView();

        /** @var UserView $view */
        $view = new $modelClass;
        $view->fill([
            ...$attributes,
            'user_id' => $user->getKey(),
            Models::tenantColumn() => filled($tenant = $this->getDynamicTablesTenantKey()) ? (string) $tenant : null,
            'table_key' => $this->getDynamicTableKey(),
            'panel_id' => $this->getDynamicTablesPanelId(),
            'url' => $this->getDynamicTablesPageUrl(),
            'state' => $this->captureDynamicTableState(),
        ]);
        $view->status = $this->resolveDynamicTableUserViewStatus($view);
        $view->save();

        $this->updateDynamicTableManagedView($view->getKeyString(), [
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
        ]);

        if ($config->isManagedDefaultViewsEnabled() && ($data['is_default'] ?? false)) {
            $this->setDynamicTableDefaultView($view->getKeyString());
        }

        $this->flushDynamicTableViewsCache();
        $this->activeDynamicTableView = $view->getKeyString();
        $this->rememberAppliedDynamicTableView();

        $this->sendDynamicTablesNotification(
            __('filament-dynamic-tables::dynamic-tables.notifications.saved'),
            body: $view->status === ViewStatus::Pending ? __('filament-dynamic-tables::dynamic-tables.notifications.pending_approval') : null,
        );

        return $view;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function updateDynamicTableUserView(UserView $view, array $data): void
    {
        if (! $this->dynamicTablesCan('update', $view)) {
            return;
        }

        $view->fill($this->sanitizeDynamicTableUserViewData($data, $view));
        $view->status = $this->resolveDynamicTableUserViewStatus($view);
        $view->save();

        if (array_key_exists('is_favorite', $data)) {
            $this->updateDynamicTableManagedView($view->getKeyString(), ['is_favorite' => (bool) $data['is_favorite']]);
        }

        if ($this->getDynamicTablesConfig()->isManagedDefaultViewsEnabled() && array_key_exists('is_default', $data)) {
            $isDefault = (bool) $data['is_default'];
            $currentDefault = $this->getDynamicTableManagedViews()->get($view->getKeyString())?->is_default;

            if ($isDefault !== (bool) $currentDefault) {
                $this->setDynamicTableDefaultView($isDefault ? $view->getKeyString() : null);
            }
        }

        $this->flushDynamicTableViewsCache();

        $this->sendDynamicTablesNotification(
            __('filament-dynamic-tables::dynamic-tables.notifications.updated'),
            body: $view->status === ViewStatus::Pending ? __('filament-dynamic-tables::dynamic-tables.notifications.pending_approval') : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeDynamicTableUserViewData(array $data, ?UserView $view = null): array
    {
        $config = $this->getDynamicTablesConfig();

        $attributes = [
            'name' => Str::limit(trim((string) ($data['name'] ?? $view->name ?? '')), $config->getViewNameMaxLength(), ''),
        ];

        if ($config->isIconSelectEnabled() && $this->dynamicTablesCan('selectIcon')) {
            $icon = $data['icon'] ?? null;
            $attributes['icon'] = (filled($icon) && array_key_exists($icon, $config->getIcons())) ? $icon : null;
        }

        if ($config->isColorSelectEnabled() && $this->dynamicTablesCan('selectColor')) {
            $color = $data['color'] ?? null;
            $attributes['color'] = (filled($color) && in_array($color, $config->getColors(), true)) ? $color : null;
        }

        if (array_key_exists('is_public', $data)) {
            $attributes['is_public'] = $config->isPublicViewsEnabled()
                && $this->dynamicTablesCan('makePublic')
                && (bool) $data['is_public'];
        }

        if (array_key_exists('is_global_favorite', $data)) {
            $attributes['is_global_favorite'] = $config->isGlobalFavoritesEnabled()
                && $this->dynamicTablesCan('makeGlobalFavorite')
                && (bool) $data['is_global_favorite'];
        }

        return $attributes;
    }

    protected function getDynamicTablesPanelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The page the table lives on, so the User Views resource can link to it.
     */
    protected function getDynamicTablesPageUrl(): ?string
    {
        $url = request()->hasHeader('X-Livewire') || request()->hasHeader('X-Livewire-Request')
            ? (string) request()->header('Referer')
            : request()->fullUrl();

        $path = parse_url($url, PHP_URL_PATH);

        return filled($path) ? (string) str($path)->limit(2000, '') : null;
    }

    /**
     * Whether quick save is refused because a preset view is active and
     * createUsingPresetView(false) is set.
     */
    public function isDynamicTableSaveBlockedByPresetView(): bool
    {
        return (! $this->getDynamicTablesConfig()->canCreateUsingPresetView())
            && filled($this->activeDynamicTableView)
            && str_starts_with($this->activeDynamicTableView, 'preset:')
            && ($this->getActiveDynamicTablePresetView()?->hasQueryModification() ?? false);
    }

    /**
     * Private views are always approved. Shared (public or global) views
     * start with initialStatus(), or pending when approval is required, and
     * keep their status until their sharing changes.
     */
    protected function resolveDynamicTableUserViewStatus(UserView $view): ViewStatus
    {
        $config = $this->getDynamicTablesConfig();

        if ($this->dynamicTablesCan('approve', $view)) {
            return $view->exists ? $view->status : ViewStatus::Approved;
        }

        if ((! $view->is_public) && (! $view->is_global_favorite)) {
            return ViewStatus::Approved;
        }

        if ($view->exists && (! $view->isDirty(['is_public', 'is_global_favorite']))) {
            return $view->status;
        }

        if ($config->hasExplicitInitialStatus()) {
            return $config->getInitialStatus();
        }

        $needsApproval = ($view->is_public && $config->doPublicViewsRequireApproval())
            || ($view->is_global_favorite && $config->doGlobalFavoritesRequireApproval());

        return $needsApproval ? ViewStatus::Pending : ViewStatus::Approved;
    }

    public function replaceDynamicTableUserView(string $key): void
    {
        $view = $this->findDynamicTableUserView(Str::after($key, 'user:'));

        if ((! $view) || (! $this->dynamicTablesCan('update', $view))) {
            return;
        }

        $view->state = $this->captureDynamicTableState();
        $view->save();

        $this->flushDynamicTableViewsCache();
        $this->activeDynamicTableView = $view->getKeyString();
        $this->rememberAppliedDynamicTableView();

        $this->sendDynamicTablesNotification(__('filament-dynamic-tables::dynamic-tables.notifications.replaced'));
    }

    public function deleteDynamicTableUserView(string $key): void
    {
        $view = $this->findDynamicTableUserView(Str::after($key, 'user:'));

        if ((! $view) || (! $this->dynamicTablesCan('delete', $view))) {
            return;
        }

        Models::managedView()::query()
            ->where('table_key', $view->table_key)
            ->where('view_type', ViewType::User->value)
            ->where('view_key', (string) $view->getKey())
            ->delete();

        $view->delete();

        if ($this->activeDynamicTableView === $key) {
            $this->activeDynamicTableView = $this->getDynamicTablesConfig()->isDefaultViewEnabled() ? 'default' : null;
            $this->rememberAppliedDynamicTableView();
        }

        $this->flushDynamicTableViewsCache();

        $this->sendDynamicTablesNotification(__('filament-dynamic-tables::dynamic-tables.notifications.deleted'));
    }

    public function toggleDynamicTableUserViewPublic(string $key): void
    {
        $view = $this->findDynamicTableUserView(Str::after($key, 'user:'));

        if ((! $view) || (! $this->dynamicTablesCan('update', $view))) {
            return;
        }

        $this->updateDynamicTableUserView($view, ['is_public' => ! $view->is_public]);
    }

    /*
    |--------------------------------------------------------------------------
    | Favorites, ordering and default views (per user)
    |--------------------------------------------------------------------------
    */

    public function toggleDynamicTableViewFavorite(string $key): void
    {
        $item = $this->getDynamicTableViewItem($key);

        if ((! $item) || $item->isDefaultView() || (! $item->isManageable)) {
            return;
        }

        $this->updateDynamicTableManagedView($key, ['is_favorite' => ! $item->isFavorite]);
        $this->flushDynamicTableViewsCache();
    }

    public function setDynamicTableDefaultView(?string $key): void
    {
        $user = $this->getDynamicTablesUser();

        if ((! $user) || (! $this->getDynamicTablesConfig()->isManagedDefaultViewsEnabled())) {
            return;
        }

        if (filled($key) && ($key !== 'default') && (! $this->getDynamicTableViewItem($key))) {
            return;
        }

        Models::managedView()::query()
            ->forContext($user, $this->getDynamicTableKey(), $this->getDynamicTablesTenantKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (filled($key) && ($key !== 'default')) {
            $this->updateDynamicTableManagedView($key, ['is_default' => true]);
        }

        $this->flushDynamicTableViewsCache();
    }

    public function toggleDynamicTableDefaultView(string $key): void
    {
        $item = $this->getDynamicTableViewItem($key);

        if (! $item) {
            return;
        }

        $currentDefault = $this->getDynamicTableManagedViews()->first(fn (ManagedView $view): bool => $view->is_default)?->getViewKeyString();

        $this->setDynamicTableDefaultView($currentDefault === $key ? null : $key);
    }

    /**
     * @param  array<int, mixed>  $keys  view keys in their new order, straight from the browser
     */
    public function reorderDynamicTableFavoriteViews(array $keys): void
    {
        if (! $this->getDynamicTablesConfig()->isViewManagerReorderable()) {
            return;
        }

        $keys = array_values(array_filter($keys, fn (mixed $key): bool => is_string($key) && (bool) $this->getDynamicTableViewItem($key)?->isManageable));

        foreach ($keys as $index => $key) {
            $this->updateDynamicTableManagedView($key, ['sort_order' => $index + 1]);
        }

        $this->flushDynamicTableViewsCache();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateDynamicTableManagedView(string $key, array $attributes): void
    {
        $user = $this->getDynamicTablesUser();

        if ((! $user) || (! str_contains($key, ':'))) {
            return;
        }

        [$type, $viewKey] = explode(':', $key, 2);

        if (! in_array($type, [ViewType::Preset->value, ViewType::User->value], true)) {
            return;
        }

        $tenant = $this->getDynamicTablesTenantKey();

        Models::managedView()::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                Models::tenantColumn() => filled($tenant) ? (string) $tenant : null,
                'table_key' => $this->getDynamicTableKey(),
                'view_type' => $type,
                'view_key' => $viewKey,
            ],
            $attributes,
        );

        $this->cachedDynamicManagedViews = null;
        $this->cachedDynamicViewItems = null;
    }
}
