<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use KaraOdin\FilamentDynamicTables\Filters\AdvancedFilter;
use KaraOdin\FilamentDynamicTables\Models\Preference;
use KaraOdin\FilamentDynamicTables\Support\FilterOptions;
use KaraOdin\FilamentDynamicTables\Support\Models;
use Throwable;

/**
 * Turns Filament's filter indicators into clickable chips that open the
 * filter's own form inline. Filters can be pinned so their chip is always
 * shown, even when the filter is not active.
 */
trait HasQuickFilters
{
    protected ?Preference $cachedDynamicTablePreference = null;

    protected bool $hasLoadedDynamicTablePreference = false;

    public function isQuickFiltersActive(): bool
    {
        return $this->getDynamicTablesConfig()->isQuickFiltersEnabled() && $this->getTable()->isFilterable();
    }

    /**
     * One chip per filter (or per indicator for filters with multiple
     * indicators and for the Advanced Filter Builder).
     *
     * @return array<int, array{name: string, key: string, label: string, summary: ?string, indicators: array<Indicator>, isActive: bool, isPinned: bool, canEdit: bool, canPin: bool, removeHandler: ?string, color: mixed}>
     */
    public function getQuickFilterItems(): array
    {
        $table = $this->getTable();
        $pinned = $this->getPinnedQuickFilters();
        $items = [];

        foreach ($table->getFilters() as $name => $filter) {
            /** @var BaseFilter $filter */
            if ($filter->isHidden()) {
                continue;
            }

            $name = (string) $name;

            $indicators = collect($filter->getIndicators())
                ->map(function (Indicator $indicator) use ($filter): Indicator {
                    $removeField = $indicator->getRemoveField();

                    return $indicator->removeLivewireClickHandler("removeTableFilter('{$filter->getName()}'" . (filled($removeField) ? ", '{$removeField}'" : '') . ')');
                })
                ->values()
                ->all();

            $isActive = filled($indicators);
            $isPinned = in_array($name, $pinned, true);
            $isBuilder = $filter instanceof AdvancedFilter;

            if ((! $isActive) && (! $isPinned)) {
                continue;
            }

            $multiple = FilterOptions::hasMultipleIndicators($filter)
                ?? ($isBuilder || ((count($indicators) > 1) && collect($indicators)->every(fn (Indicator $indicator): bool => filled($indicator->getRemoveField()))));

            if ($isActive && $multiple && (count($indicators) > 1 || $isBuilder)) {
                foreach ($indicators as $index => $indicator) {
                    $items[] = [
                        'name' => $name,
                        'key' => "{$name}.{$index}",
                        'label' => strip_tags((string) $filter->getLabel()),
                        'summary' => strip_tags((string) $indicator->getLabel()),
                        'indicators' => [$indicator],
                        'isActive' => true,
                        'isPinned' => $isPinned && ($index === 0),
                        'canEdit' => ! $isBuilder,
                        'canPin' => ! $isBuilder,
                        'removeHandler' => $indicator->isRemovable() ? $indicator->getRemoveLivewireClickHandler() : null,
                        'color' => $indicator->getColor(),
                    ];
                }

                continue;
            }

            $items[] = [
                'name' => $name,
                'key' => $name,
                'label' => strip_tags((string) $filter->getLabel()),
                'summary' => $isActive ? $this->getQuickFilterSummary($filter, $indicators) : null,
                'indicators' => $indicators,
                'isActive' => $isActive,
                'isPinned' => $isPinned,
                'canEdit' => (! ($filter instanceof QueryBuilder)) && (! $isBuilder),
                'canPin' => ! $isBuilder,
                'removeHandler' => collect($indicators)->contains(fn (Indicator $indicator): bool => $indicator->isRemovable())
                    ? 'removeTableFilter(' . Js::from($name) . ')'
                    : null,
                'color' => collect($indicators)->first()?->getColor(),
            ];
        }

        // Pinned filters keep their pinned order; active, unpinned ones follow.
        usort($items, function (array $a, array $b) use ($pinned): int {
            $positionA = array_search($a['name'], $pinned, true);
            $positionB = array_search($b['name'], $pinned, true);

            return ($positionA === false ? PHP_INT_MAX : $positionA) <=> ($positionB === false ? PHP_INT_MAX : $positionB);
        });

        return $items;
    }

    /**
     * The chip text. Select filters with ->limitIndicatorLabels(n) show the
     * first n options and "+ :count".
     *
     * @param  array<Indicator>  $indicators
     */
    protected function getQuickFilterSummary(BaseFilter $filter, array $indicators): string
    {
        $limit = FilterOptions::getIndicatorLabelLimit($filter);

        if (($limit !== null) && ($filter instanceof FilamentSelectFilter) && $filter->isMultiple()) {
            $values = (array) ($this->getTableFilterState($filter->getName())['values'] ?? []);
            $options = $filter->getOptions();
            $labels = collect($values)->map(fn (mixed $value): string => strip_tags((string) (Arr::get($options, (string) $value) ?? $value)));

            if ($labels->count() > $limit) {
                return strip_tags((string) $filter->getLabel()) . ': ' . $labels->take($limit)->implode(', ') . ' '
                    . __('filament-dynamic-tables::dynamic-tables.quick_filters.more_indicator_labels', ['count' => $labels->count() - $limit]);
            }
        }

        return collect($indicators)->map(fn (Indicator $indicator): string => strip_tags((string) $indicator->getLabel()))->implode(', ');
    }

    /**
     * Search and column search indicators shown before the filter chips.
     *
     * @return array<Indicator>
     */
    public function getQuickFilterSearchIndicators(): array
    {
        $table = $this->getTable();

        return [
            ...($table->hasSearch() ? [$this->getTableSearchIndicator()] : []),
            ...$this->getTableColumnSearchIndicators(),
        ];
    }

    /**
     * The filter's own fields, as a schema that can be rendered on its own.
     */
    public function getQuickFilterSchema(string $name): ?Schema
    {
        try {
            return $this->getTableFiltersForm()->getComponentByStatePath($name)?->getChildSchema();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getPinnedQuickFilters(): array
    {
        $config = $this->getDynamicTablesConfig();
        $filters = array_keys($this->getTable()->getFilters());

        $favorites = collect($this->getTable()->getFilters())
            ->filter(fn (BaseFilter $filter): bool => FilterOptions::isFavorite($filter))
            ->keys()
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        $pinned = $this->getDynamicTablePreference()->settings['pinned_filters'] ?? [...$config->getPinnedFilters(), ...$favorites];

        if (($preset = $this->getActiveDynamicTablePresetView()) && (($presetFavorites = $preset->getDefaultFavoriteFilters()) !== null)) {
            $pinned = [...(array) $pinned, ...$presetFavorites];
        }

        return array_values(array_unique(array_intersect((array) $pinned, $filters)));
    }

    public function isQuickFilterPinned(string $name): bool
    {
        return in_array($name, $this->getPinnedQuickFilters(), true);
    }

    public function toggleQuickFilterPin(string $name): void
    {
        if (! $this->getDynamicTablesConfig()->isQuickFilterPinningEnabled()) {
            return;
        }

        if (! array_key_exists($name, $this->getTable()->getFilters()) || ($this->getTable()->getFilter($name) instanceof AdvancedFilter)) {
            return;
        }

        $pinned = $this->getPinnedQuickFilters();

        $pinned = in_array($name, $pinned, true)
            ? array_values(array_diff($pinned, [$name]))
            : [...$pinned, $name];

        $this->updateDynamicTablePreference(['pinned_filters' => $pinned]);
    }

    protected function getDynamicTablePreference(): ?Preference
    {
        if ($this->hasLoadedDynamicTablePreference) {
            return $this->cachedDynamicTablePreference;
        }

        $this->hasLoadedDynamicTablePreference = true;
        $user = $this->getDynamicTablesUser();

        if (! $user) {
            return null;
        }

        try {
            return $this->cachedDynamicTablePreference = Models::preference()::query()
                ->forContext($user, $this->getDynamicTableKey(), $this->getDynamicTablesTenantKey())
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function updateDynamicTablePreference(array $settings): void
    {
        $user = $this->getDynamicTablesUser();

        if (! $user) {
            return;
        }

        $tenant = $this->getDynamicTablesTenantKey();
        $preference = $this->getDynamicTablePreference();

        $this->cachedDynamicTablePreference = Models::preference()::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                Models::tenantColumn() => filled($tenant) ? (string) $tenant : null,
                'table_key' => $this->getDynamicTableKey(),
            ],
            [
                'settings' => [...($preference->settings ?? []), ...$settings],
            ],
        );
    }
}
