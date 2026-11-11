<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use KaraOdin\FilamentDynamicTables\Components\PresetView;

/**
 * Captures the table configuration (filters, search, sort, grouping,
 * columns, pagination, advanced search) and applies it back.
 */
trait HasDynamicTableState
{
    /**
     * @return array<string, mixed>
     */
    public function captureDynamicTableState(): array
    {
        return [
            'filters' => $this->tableFilters ?? [],
            'search' => (string) ($this->tableSearch ?? ''),
            'column_searches' => $this->tableColumnSearches,
            'sort' => $this->getTableMultiSort(),
            'grouping' => $this->tableGrouping,
            'columns' => $this->tableColumns,
            'records_per_page' => $this->tableRecordsPerPage ?? null,
            'advanced_search' => $this->advancedSearch,
            'preset' => $this->getActiveDynamicTablePresetKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function applyDynamicTableState(array $state): void
    {
        if (array_key_exists('filters', $state)) {
            $this->fillDynamicTableFilters($state['filters'] ?? []);
        }

        if (array_key_exists('search', $state) || array_key_exists('advanced_search', $state)) {
            $this->advancedSearch = [];
            $this->tableSearch = (string) ($state['search'] ?? '');

            $groups = $state['advanced_search'] ?? null;

            // Views saved with the earlier single constraint / column selection.
            if ((! is_array($groups) || blank($groups)) && filled($state['search'] ?? null) && (filled($state['search_constraint'] ?? null) || filled($state['search_columns'] ?? null))) {
                $groups = [[
                    'value' => (string) $state['search'],
                    'constraint' => $state['search_constraint'] ?? null,
                    'columns' => (array) ($state['search_columns'] ?? []),
                ]];
            }

            if (is_array($groups) && filled($groups) && $this->isAdvancedSearchActive()) {
                $this->advancedSearch = $this->normalizeAdvancedSearchGroups($groups, parse: false);
                $this->syncTableSearchWithAdvancedSearch();
            } else {
                $this->persistAdvancedSearchState();
                $this->updatedTableSearch();
            }
        }

        if (array_key_exists('column_searches', $state)) {
            $this->tableColumnSearches = $this->castTableColumnSearches((array) ($state['column_searches'] ?? []));
            $this->updatedTableColumnSearches();
        }

        if (array_key_exists('sort', $state)) {
            $this->setTableMultiSort((array) ($state['sort'] ?? []));
        }

        if (array_key_exists('grouping', $state)) {
            $this->tableGrouping = $state['grouping'] ?? $this->getDefaultDynamicTableGrouping();
            $this->updatedTableGrouping();
        }

        if (array_key_exists('columns', $state)) {
            $this->applyDynamicTableColumns($state['columns'] ?? []);
        }

        if (filled($state['records_per_page'] ?? null) && $this->getTable()->isPaginated()) {
            $options = $this->getTable()->getPaginationPageOptions();

            if (in_array($state['records_per_page'], $options) || in_array((int) $state['records_per_page'], $options, true)) {
                $this->tableRecordsPerPage = is_numeric($state['records_per_page']) ? (int) $state['records_per_page'] : $state['records_per_page'];
                $this->updatedTableRecordsPerPage();
            }
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /**
     * State that resets the table to how the developer configured it.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultDynamicTableState(): array
    {
        return [
            'filters' => [],
            'search' => '',
            'column_searches' => [],
            'sort' => [],
            'grouping' => null,
            'columns' => [],
            'advanced_search' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPresetViewState(PresetView $preset): array
    {
        $state = $this->getDefaultDynamicTableState();

        if ($preset->shouldPreserveFilters()) {
            unset($state['filters']);
        } else {
            $state['filters'] = $preset->getDefaultFilters() ?? [];
        }

        if ($preset->shouldPreserveSearch()) {
            unset($state['search'], $state['column_searches'], $state['advanced_search']);
        } else {
            $search = $preset->getDefaultSearch();

            if (is_array($search)) {
                $state['advanced_search'] = array_values($search);
                $state['search'] = collect($search)->map(fn (array $group): string => (string) ($group['value'] ?? ''))->filter()->implode(' ');
            } else {
                $state['search'] = (string) ($search ?? '');
                $state['advanced_search'] = filled($search) ? [['value' => (string) $search, 'constraint' => 'contains', 'columns' => [], 'operator' => null]] : [];
            }
        }

        if ($preset->shouldPreserveSort()) {
            unset($state['sort']);
        } else {
            $state['sort'] = collect($preset->getDefaultSort() ?? [])
                ->map(fn (string $direction, string $column): string => $column . ':' . (strtolower($direction) === 'desc' ? 'desc' : 'asc'))
                ->values()
                ->all();
        }

        if ($preset->shouldPreserveGrouping()) {
            unset($state['grouping']);
        } else {
            $state['grouping'] = $preset->getDefaultGrouping();
        }

        if ($preset->shouldPreserveColumns()) {
            unset($state['columns']);
        } else {
            $state['columns'] = $this->buildPresetColumnState($preset);
        }

        return $state;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildPresetColumnState(PresetView $preset): array
    {
        $visible = $preset->getVisibleColumns();
        $order = $preset->getColumnOrder();

        if (($visible === null) && ($order === null)) {
            return [];
        }

        $columns = $this->getDefaultTableColumnState();

        if ($visible !== null) {
            $toggle = function (array $item) use ($visible): array {
                if (($item['isToggleable'] ?? false) && (($item['type'] ?? null) === 'column')) {
                    $item['isToggled'] = in_array($item['name'], $visible, true);
                }

                if (isset($item['columns'])) {
                    $item['columns'] = array_map(function (array $column) use ($visible): array {
                        if ($column['isToggleable'] ?? false) {
                            $column['isToggled'] = in_array($column['name'], $visible, true);
                        }

                        return $column;
                    }, $item['columns']);
                }

                return $item;
            };

            $columns = array_map($toggle, $columns);
        }

        if ($order !== null) {
            $position = array_flip(array_values($order));

            $columns = collect($columns)
                ->values()
                ->sortBy(fn (array $item, int $index): int => $position[$item['name']] ?? (1000 + $index))
                ->values()
                ->all();
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function fillDynamicTableFilters(array $filters): void
    {
        $table = $this->getTable();

        if (! $table->isFilterable()) {
            return;
        }

        $knownFilters = array_keys($table->getFilters());
        $filters = $this->normalizeDynamicTableFilterBuilderState(array_intersect_key($filters, array_flip($knownFilters)));

        $this->getTableFiltersForm()->fill($filters);

        if ($table->hasDeferredFilters()) {
            $this->tableFilters = $this->tableDeferredFilters;
        }

        $this->handleTableFilterUpdates();
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function applyDynamicTableColumns(array $columns): void
    {
        if ($this->getTable()->hasColumnsLayout()) {
            return;
        }

        if (blank($columns)) {
            $this->resetTableColumnManager();

            return;
        }

        $this->applyTableColumnManager($columns, $this->hasReorderableTableColumns());
    }

    protected function getDefaultDynamicTableGrouping(): ?string
    {
        $table = $this->getTable();

        if (! $table->isDefaultGroupSelectable()) {
            return null;
        }

        $direction = method_exists($table, 'getDefaultGroupDirection') ? $table->getDefaultGroupDirection() : 'asc';

        return "{$table->getDefaultGroup()->getId()}:{$direction}";
    }

    /**
     * A stable fingerprint of a state, used to detect whether the active
     * view has been modified since it was applied.
     *
     * @param  array<string, mixed>  $state
     */
    public function hashDynamicTableState(array $state): string
    {
        unset($state['preset']);

        $state['filters'] = $this->getComparableDynamicTableFilters((array) ($state['filters'] ?? []));

        $state['columns'] = collect($state['columns'] ?? [])
            ->map(fn (array $item): array => [
                'n' => $item['name'] ?? null,
                't' => (bool) ($item['isToggled'] ?? true),
                'c' => collect($item['columns'] ?? [])->map(fn (array $column): array => [$column['name'] ?? null, (bool) ($column['isToggled'] ?? true)])->all(),
            ])
            ->all();

        if ($state['columns'] === $this->hashableDefaultColumns()) {
            $state['columns'] = [];
        }

        if (($state['grouping'] ?? null) === $this->getDefaultDynamicTableGrouping()) {
            $state['grouping'] = null;
        }

        unset($state['records_per_page']);

        return md5(json_encode($this->normalizeDynamicTableState($state)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function hashableDefaultColumns(): array
    {
        return collect($this->getDefaultTableColumnState())
            ->map(fn (array $item): array => [
                'n' => $item['name'],
                't' => $item['isToggled'],
                'c' => collect($item['columns'] ?? [])->map(fn (array $column): array => [$column['name'], $column['isToggled']])->all(),
            ])
            ->all();
    }

    protected function normalizeDynamicTableState(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);

            $value = array_map(fn (mixed $item): mixed => $this->normalizeDynamicTableState($item), $value);
            $value = array_filter($value, fn (mixed $item): bool => ! ($item === null || $item === '' || $item === [] || $item === false));

            if ($isList) {
                return array_values($value);
            }

            ksort($value);

            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : false;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $value;
    }
}
