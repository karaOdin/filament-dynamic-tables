<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sort by several columns at once. The first sort is mirrored into
 * Filament's own $tableSort so header icons stay in sync, and clicking a
 * column header replaces the whole sort list with that column.
 */
trait HasMultiSort
{
    /**
     * Sorts in priority order, each as "column:direction".
     *
     * @var array<int, string>
     */
    public array $tableMultiSort = [];

    /**
     * @return array<int, string>
     */
    public function getTableMultiSort(): array
    {
        $this->syncTableMultiSortWithTableSort();

        return array_values($this->tableMultiSort);
    }

    /**
     * @return array<int, array{column: string, direction: string, label: string}>
     */
    public function getTableMultiSortItems(): array
    {
        $table = $this->getTable();

        return collect($this->getTableMultiSort())
            ->map(function (string $sort) use ($table): ?array {
                [$column, $direction] = $this->parseTableMultiSort($sort);
                $columnInstance = $table->getColumn($column);

                if (! $columnInstance) {
                    return null;
                }

                return [
                    'column' => $column,
                    'direction' => $direction,
                    'label' => strip_tags((string) $columnInstance->getLabel()),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Sortable columns that are not part of the current sort yet.
     *
     * @return array<string, string>
     */
    public function getTableMultiSortAvailableColumns(): array
    {
        $used = collect($this->getTableMultiSort())->map(fn (string $sort): string => $this->parseTableMultiSort($sort)[0])->all();

        return collect($this->getTable()->getColumns())
            ->filter(fn (Column $column): bool => $column->isSortable() && (! $column->isHidden()) && (! $column->isToggledHidden()))
            ->reject(fn (Column $column): bool => in_array($column->getName(), $used, true))
            ->mapWithKeys(fn (Column $column): array => [$column->getName() => strip_tags((string) $column->getLabel())])
            ->all();
    }

    public function canAddTableMultiSort(): bool
    {
        $max = $this->getDynamicTablesConfig()->getMultiSortMaxColumns();

        if (($max !== null) && (count($this->getTableMultiSort()) >= $max)) {
            return false;
        }

        return filled($this->getTableMultiSortAvailableColumns());
    }

    public function addTableMultiSort(string $column, string $direction = 'asc'): void
    {
        if (! array_key_exists($column, $this->getTableMultiSortAvailableColumns())) {
            return;
        }

        if (! $this->canAddTableMultiSort()) {
            return;
        }

        $sorts = $this->getTableMultiSort();
        $sorts[] = $column . ':' . ($direction === 'desc' ? 'desc' : 'asc');

        $this->setTableMultiSort($sorts);
    }

    public function toggleTableMultiSortDirection(int $index): void
    {
        $sorts = $this->getTableMultiSort();

        if (! isset($sorts[$index])) {
            return;
        }

        [$column, $direction] = $this->parseTableMultiSort($sorts[$index]);
        $sorts[$index] = $column . ':' . ($direction === 'asc' ? 'desc' : 'asc');

        $this->setTableMultiSort($sorts);
    }

    public function removeTableMultiSort(int $index): void
    {
        $sorts = $this->getTableMultiSort();

        unset($sorts[$index]);

        $this->setTableMultiSort(array_values($sorts));
    }

    /**
     * @param  array<int, mixed>  $columns  column names in their new order, straight from the browser
     */
    public function reorderTableMultiSort(array $columns): void
    {
        $sorts = collect($this->getTableMultiSort())
            ->keyBy(fn (string $sort): string => $this->parseTableMultiSort($sort)[0]);

        $reordered = collect($columns)
            ->filter(fn (mixed $column): bool => is_string($column) && $sorts->has($column))
            ->map(fn (string $column): string => $sorts->get($column))
            ->values();

        $this->setTableMultiSort($reordered->merge($sorts->except($reordered->map(fn (string $sort): string => $this->parseTableMultiSort($sort)[0])->all())->values())->all());
    }

    public function resetTableMultiSort(): void
    {
        $this->setTableMultiSort([]);
    }

    /**
     * @param  array<int, mixed>  $sorts  "column:direction" strings or ['column' => ..., 'direction' => ...] arrays
     */
    public function setTableMultiSort(array $sorts): void
    {
        $table = $this->getTable();

        $normalized = collect($sorts)
            ->map(function (mixed $sort): ?string {
                if (is_array($sort)) {
                    $sort = ($sort['column'] ?? '') . ':' . ($sort['direction'] ?? 'asc');
                }

                if (! is_string($sort) || blank($sort)) {
                    return null;
                }

                [$column, $direction] = $this->parseTableMultiSort($sort);

                return "{$column}:{$direction}";
            })
            ->filter(fn (?string $sort): bool => filled($sort) && $table->getSortableVisibleColumn($this->parseTableMultiSort($sort)[0]))
            ->unique(fn (string $sort): string => $this->parseTableMultiSort($sort)[0])
            ->values();

        if (! $this->getDynamicTablesConfig()->isMultiSortEnabled()) {
            $normalized = $normalized->take(1);
        } elseif (filled($max = $this->getDynamicTablesConfig()->getMultiSortMaxColumns())) {
            $normalized = $normalized->take($max);
        }

        $this->tableMultiSort = $normalized->all();
        $this->tableSort = $this->tableMultiSort[0] ?? null;

        $this->updatedTableSort();
        $this->persistTableMultiSort();
    }

    /**
     * Header clicks change $tableSort directly; when the primary sort no
     * longer matches, the header click wins and replaces the list.
     */
    protected function syncTableMultiSortWithTableSort(): void
    {
        $primary = $this->tableMultiSort[0] ?? null;

        if (blank($this->tableSort)) {
            if (filled($primary)) {
                $this->tableMultiSort = [];
                $this->persistTableMultiSort();
            }

            return;
        }

        [$column, $direction] = $this->parseTableMultiSort($this->tableSort);
        $sort = "{$column}:{$direction}";

        if ($primary === $sort) {
            return;
        }

        if (filled($primary) && ($this->parseTableMultiSort($primary)[0] === $column)) {
            // Same column, direction toggled from the header.
            $this->tableMultiSort[0] = $sort;
        } else {
            $this->tableMultiSort = [$sort];
        }

        $this->persistTableMultiSort();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseTableMultiSort(string $sort): array
    {
        $column = (string) str($sort)->before(':');
        $direction = str_contains($sort, ':') ? (string) str($sort)->afterLast(':') : 'asc';

        return [$column, $direction === 'desc' ? 'desc' : 'asc'];
    }

    protected function persistTableMultiSort(): void
    {
        if ($this->getTable()->persistsSortInSession()) {
            session()->put($this->getDynamicTablesSessionKey('multi_sort'), $this->tableMultiSort);
        }
    }

    protected function restoreTableMultiSortFromSession(): void
    {
        if (! $this->getTable()->persistsSortInSession()) {
            return;
        }

        $sorts = session()->get($this->getDynamicTablesSessionKey('multi_sort'));

        if (is_array($sorts) && filled($sorts) && (($sorts[0] ?? null) === $this->tableSort)) {
            $this->tableMultiSort = array_values(array_filter($sorts, 'is_string'));
        }
    }

    /**
     * Applies every sort in priority order. Returns false when the regular
     * Filament sorting should be used instead.
     */
    protected function applyTableMultiSortToQuery(Builder $query): bool
    {
        if (! $this->getDynamicTablesConfig()->isMultiSortEnabled()) {
            return false;
        }

        $sorts = $this->getTableMultiSort();

        if (count($sorts) < 2) {
            return false;
        }

        $table = $this->getTable();

        if ($table->isGroupsOnly() || $this->isTableReordering()) {
            return false;
        }

        $direction = 'asc';

        foreach ($sorts as $sort) {
            [$columnName, $direction] = $this->parseTableMultiSort($sort);
            $column = $table->getSortableVisibleColumn($columnName);

            if ((! $column) || $column->isToggledHidden()) {
                continue;
            }

            $column->applySort($query, $direction);
        }

        if (! $table->hasDefaultKeySort()) {
            return true;
        }

        $qualifiedKeyName = $query->getModel()->getQualifiedKeyName();

        foreach ($query->getQuery()->orders ?? [] as $order) {
            if (in_array($order['column'] ?? null, [$qualifiedKeyName, $query->getModel()->getKeyName()], true)) {
                return true;
            }
        }

        $query->orderBy($qualifiedKeyName, $direction);

        return true;
    }
}
