<?php

namespace KaraOdin\FilamentDynamicTables;

use Filament\Facades\Filament;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasAdvancedFilterBuilder;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasAdvancedSearch;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasDynamicTableActions;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasDynamicTableState;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasDynamicTableViews;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasMultiSort;
use KaraOdin\FilamentDynamicTables\Concerns\Table\HasQuickFilters;
use KaraOdin\FilamentDynamicTables\Concerns\Table\InteractsWithDynamicTablesContext;
use Livewire\Attributes\Url;

/**
 * Add to a resource List page, a Manage page, a relation manager, a table
 * widget or any page with a table:
 *
 *     class ListProducts extends ListRecords
 *     {
 *         use DynamicTables;
 *     }
 *
 * Standalone Livewire table components use InteractsWithDynamicTable in
 * place of Filament's InteractsWithTable instead.
 */
trait DynamicTables
{
    use HasAdvancedFilterBuilder;
    use HasAdvancedSearch;
    use HasDynamicTableActions;
    use HasDynamicTableState;
    use HasDynamicTableViews;
    use HasMultiSort;
    use HasQuickFilters;
    use InteractsWithDynamicTablesContext;

    protected bool $shouldInitializeDynamicTables = false;

    protected bool $hasExistingDynamicTableState = false;

    /**
     * A view requested in the URL (?table_view=user:12), e.g. by the User
     * Views resource's "Open" action.
     */
    protected ?string $requestedDynamicTableView = null;

    public function mountDynamicTables(): void
    {
        $this->shouldInitializeDynamicTables = true;

        $requested = request()->query('table_view');
        $this->requestedDynamicTableView = is_string($requested) && filled($requested) ? $requested : null;

        // Mount runs before Filament restores (and re-writes) persisted
        // state, so this is the moment to see whether any exists.
        $this->hasExistingDynamicTableState = $this->hasDynamicTablesUrlState() || $this->hasFilamentSessionTableState();
    }

    /**
     * Whether the request carries table state in its query string, read
     * from each state property's #[Url] attribute (ListRecords maps
     * $tableFilters to "filters", $tableSearch to "search", ...).
     */
    protected function hasDynamicTablesUrlState(): bool
    {
        $query = request()->query();

        if (blank($query)) {
            return false;
        }

        foreach (['tableFilters', 'tableGrouping', 'tableSearch', 'tableSort', 'tableColumnSearches'] as $property) {
            $keys = [$property];

            if (property_exists($this, $property)) {
                foreach ((new \ReflectionProperty($this, $property))->getAttributes(Url::class) as $attribute) {
                    $arguments = $attribute->getArguments();
                    $keys[] = $arguments['as'] ?? $arguments[0] ?? $property;
                }
            }

            foreach (array_unique(array_filter($keys)) as $key) {
                if (filled($this->normalizeDynamicTableState($query[$key] ?? null))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detects filters, search, sort or grouping that Filament persisted in
     * the session (columns are ignored: they persist by default).
     */
    protected function hasFilamentSessionTableState(): bool
    {
        $keys = ['filters', 'sort', 'search', 'column_search', 'grouping'];

        // Filament 4: "tables.{md5}_{key}".
        $legacy = md5(static::class);

        foreach ($keys as $key) {
            if (filled($this->normalizeDynamicTableState(session()->get("tables.{$legacy}_{$key}")))) {
                return true;
            }
        }

        // Filament 5: "filament.tables.{sha256}[.{identifier}].{key}", tenant scoped for some keys.
        $namespaces = [static::class];

        try {
            if (function_exists('Filament\\Support\\encode_key_segments') && filled($tenant = Filament::getTenant()?->getKey())) {
                $namespaces[] = \Filament\Support\encode_key_segments(static::class, (string) $tenant);
            }
        } catch (\Throwable) {
            // No tenant.
        }

        foreach ($namespaces as $namespace) {
            $tables = session()->get('filament.tables.' . hash('sha256', $namespace));

            if (! is_array($tables)) {
                continue;
            }

            foreach ([$tables, ...array_filter($tables, 'is_array')] as $scope) {
                foreach ($keys as $key) {
                    if (filled($this->normalizeDynamicTableState($scope[$key] ?? null))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function renderingDynamicTables(): void
    {
        if ($this->shouldInitializeDynamicTables) {
            $this->shouldInitializeDynamicTables = false;

            $this->restoreTableMultiSortFromSession();
            $this->restoreAdvancedSearchStateFromSession();

            $this->initializeDynamicTableView($this->hasExistingDynamicTableState);
        }

        $this->syncTableMultiSortWithTableSort();
        $this->applyDynamicTableFilterBuilderLayout();
    }

    /**
     * Runs the base Filament implementation of an overridden method, both for
     * classes that inherit InteractsWithTable (pages, widgets, relation
     * managers) and for standalone components that alias it.
     */
    protected function callFilamentTableMethod(string $method, mixed ...$arguments): mixed
    {
        $alias = 'filament' . ucfirst($method);

        if (method_exists($this, $alias)) {
            return $this->{$alias}(...$arguments);
        }

        return parent::$method(...$arguments);
    }

    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        $query = $this->callFilamentTableMethod('applyFiltersToTableQuery', $query, $isResolvingRecord);

        if ($preset = $this->getActiveDynamicTablePresetView()) {
            $query = $preset->modifyQuery($query);
        }

        return $query;
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        if ($this->applyTableMultiSortToQuery($query)) {
            return $query;
        }

        return $this->callFilamentTableMethod('applySortingToTableQuery', $query);
    }

    protected function applyGlobalSearchToTableQuery(Builder $query): Builder
    {
        if (! $this->isAdvancedSearchActive()) {
            return $this->callFilamentTableMethod('applyGlobalSearchToTableQuery', $query);
        }

        return $this->applyAdvancedSearchToTableQuery($query);
    }

    public function getTableSearchIndicator(): Indicator
    {
        if ($this->isAdvancedSearchActive()) {
            return $this->getAdvancedSearchIndicator();
        }

        return $this->callFilamentTableMethod('getTableSearchIndicator');
    }
}
