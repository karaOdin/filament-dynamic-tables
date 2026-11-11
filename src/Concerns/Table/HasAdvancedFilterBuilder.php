<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use KaraOdin\FilamentDynamicTables\Filters\AdvancedFilter;

/**
 * Table-side support for the Advanced Filter Builder: removing a single
 * filter from its indicator, the "Expand view" slide-over and state
 * normalization for views.
 */
trait HasAdvancedFilterBuilder
{
    /**
     * Set once the user expands the filter dropdown into a slide-over.
     */
    public bool $isDynamicTableFilterBuilderExpanded = false;

    public function getDynamicTableAdvancedFilter(): ?AdvancedFilter
    {
        foreach ($this->getTable()->getFilters() as $filter) {
            if ($filter instanceof AdvancedFilter) {
                return $filter;
            }
        }

        return null;
    }

    public function removeTableFilter(string $filterName, ?string $field = null, bool $isRemovingAllFilters = false): void
    {
        $filter = $this->getTable()->getFilter($filterName);

        if (! $filter instanceof AdvancedFilter) {
            $this->callFilamentTableMethod('removeTableFilter', $filterName, $field, $isRemovingAllFilters);

            return;
        }

        $isDeferred = $this->getTable()->hasDeferredFilters();
        $state = $filter->withoutRule($this->getTableFilterFormState($filterName) ?? [], $field);

        $this->getTableFiltersForm()->getComponentByStatePath($filterName)?->getChildSchema()->fill($state);

        if ($isRemovingAllFilters) {
            return;
        }

        if ($isDeferred) {
            $this->applyTableFilters();

            return;
        }

        $this->handleTableFilterUpdates();
    }

    /**
     * Whether the builder shows its "Expand view" link: only when the
     * filters open in a dropdown.
     */
    public function canExpandDynamicTableFilterBuilder(): bool
    {
        $filter = $this->getDynamicTableAdvancedFilter();

        if ((! $filter) || (! $filter->hasExpandViewLink()) || $this->isDynamicTableFilterBuilderExpanded) {
            return false;
        }

        $table = $this->getTable();

        return ($table->getFiltersLayout() === FiltersLayout::Dropdown) && (! $table->getFiltersTriggerAction()->isModalSlideOver());
    }

    public function expandDynamicTableFilterBuilder(): void
    {
        if (! $this->canExpandDynamicTableFilterBuilder()) {
            return;
        }

        $this->isDynamicTableFilterBuilderExpanded = true;

        // Once the table re-renders with a slide-over, open it.
        $this->js(<<<'JS'
            (() => {
                let attempts = 0

                const open = () => {
                    const triggers = $wire.$el.querySelectorAll('.fi-modal-trigger')

                    for (const trigger of triggers) {
                        if (trigger.nextElementSibling?.classList.contains('fi-ta-filters-modal')) {
                            trigger.click()

                            return
                        }
                    }

                    if (attempts++ < 40) {
                        setTimeout(open, 50)
                    }
                }

                setTimeout(open, 50)
            })()
        JS);
    }

    /**
     * Turns the filter dropdown into a slide-over for the rest of the
     * page's life once the user has expanded it.
     */
    protected function applyDynamicTableFilterBuilderLayout(): void
    {
        if (! $this->isDynamicTableFilterBuilderExpanded) {
            return;
        }

        $table = $this->getTable();

        if ($table->getFiltersTriggerAction()->isModalSlideOver()) {
            return;
        }

        $existing = (fn () => $this->modifyFiltersTriggerActionUsing)->call($table);

        $table->filtersTriggerAction(function (Action $action) use ($existing, $table): Action {
            if ($existing) {
                $action = $table->evaluate($existing, ['action' => $action]) ?? $action;
            }

            return $action->slideOver();
        });

        if (in_array($table->getFiltersFormWidth(), [null, Width::ExtraSmall, 'xs'], true)) {
            $table->filtersFormWidth(Width::Medium);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function normalizeDynamicTableFilterBuilderState(array $filters): array
    {
        foreach ($this->getTable()->getFilters() as $name => $filter) {
            if ((! $filter instanceof AdvancedFilter) || (! array_key_exists($name, $filters))) {
                continue;
            }

            $filters[$name] = $filter->normalizeState(is_array($filters[$name]) ? $filters[$name] : []);
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function getComparableDynamicTableFilters(array $filters): array
    {
        foreach ($this->getTable()->getFilters() as $name => $filter) {
            if ((! $filter instanceof AdvancedFilter) || (! array_key_exists($name, $filters))) {
                continue;
            }

            $filters[$name] = $filter->getComparableState(is_array($filters[$name]) ? $filters[$name] : []);
        }

        return $filters;
    }
}
