<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;

trait InteractsWithDynamicTablesContext
{
    protected ?DynamicTablesPlugin $cachedDynamicTablesConfig = null;

    /**
     * Override on the page, widget or Livewire component to configure this
     * table only:
     *
     *     public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
     *     {
     *         return $config->favoritesBarTheme(FavoritesBarTheme::Tabs)->multiSort(false);
     *     }
     */
    public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
    {
        return $config;
    }

    public function getDynamicTablesConfig(): DynamicTablesPlugin
    {
        if ($this->cachedDynamicTablesConfig) {
            return $this->cachedDynamicTablesConfig;
        }

        $config = $this->configureDynamicTables(clone DynamicTablesPlugin::current());

        // Per-table switches, defined as (static) methods on the page:
        // public static function quickSaveIsEnabled(): bool { return false; }
        foreach ([
            'userViewsAreEnabled' => 'userViews',
            'presetViewsAreEnabled' => 'presetViews',
            'favoritesBarIsEnabled' => 'favoritesBar',
            'hasDefaultView' => 'defaultView',
            'quickSaveIsEnabled' => 'quickSave',
            'viewManagerIsEnabled' => 'viewManager',
            'multiSortIsEnabled' => 'multiSort',
            'advancedSearchIsEnabled' => 'advancedSearch',
            'quickFiltersAreEnabled' => 'quickFilters',
            'tableLoadingOverlayIsEnabled' => 'loadingOverlay',
        ] as $method => $option) {
            if (method_exists($this, $method)) {
                $config->{$option}((bool) $this->{$method}());
            }
        }

        return $this->cachedDynamicTablesConfig = $config;
    }

    /**
     * The key that user views, favorites and preferences are stored under.
     * Defaults to the component class plus the table identifier.
     */
    public function getDynamicTableKey(): string
    {
        $key = static::class;

        // Anonymous subclasses share the views of the class they extend.
        while ((new \ReflectionClass($key))->isAnonymous() && ($parent = get_parent_class($key))) {
            $key = $parent;
        }

        if (method_exists($this->getTable(), 'getIdentifier') && filled($identifier = $this->getTable()->getIdentifier())) {
            $key .= ':' . $identifier;
        }

        return $key;
    }

    public function getDynamicTablesUser(): ?Model
    {
        return Users::current();
    }

    public function getDynamicTablesTenantKey(): int | string | null
    {
        return $this->getDynamicTablesConfig()->getTenantKey();
    }

    protected function getDynamicTablesSessionKey(string $key): string
    {
        $tenant = $this->getDynamicTablesTenantKey();

        return 'filament-dynamic-tables.' . hash('sha256', $this->getDynamicTableKey() . '|' . $tenant) . '.' . $key;
    }

    protected function dynamicTablesCan(string $ability, mixed $arguments = null): bool
    {
        $user = $this->getDynamicTablesUser();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $arguments ?? Models::userView());
    }

    /**
     * Whether the table renders the right-hand toolbar container (search,
     * filter trigger, column manager) that our toolbar controls attach to.
     */
    public function hasDynamicTablesToolbarContainer(): bool
    {
        $table = $this->getTable();

        if ($table->isSearchable() || $table->hasColumnManager()) {
            return true;
        }

        if (! $table->isFilterable()) {
            return false;
        }

        return in_array($table->getFiltersLayout(), [
            FiltersLayout::Dropdown,
            FiltersLayout::Modal,
            FiltersLayout::BeforeContent,
            FiltersLayout::BeforeContentCollapsible,
            FiltersLayout::AfterContent,
            FiltersLayout::AfterContentCollapsible,
        ], true);
    }

    protected function sendDynamicTablesNotification(string $title, string $status = 'success', ?string $body = null): void
    {
        if (! $this->getDynamicTablesConfig()->hasNotifications()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->status($status)
            ->send();
    }
}
