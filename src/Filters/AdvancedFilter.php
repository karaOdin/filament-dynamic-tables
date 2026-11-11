<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder as BuilderField;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\View;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use KaraOdin\FilamentDynamicTables\Filters\Operators\BooleanOperator;
use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias;
use Throwable;

/**
 * The Advanced Filter Builder: users pick filters from a filter picker,
 * use each as many times as they like and combine them in "or" groups.
 * Filters within a group must all match; a record matching any group is
 * shown.
 *
 * Column filters are generated from the table's columns (text, number,
 * date, select and yes/no, chosen from the column type and model casts).
 * Pass your own to ->filters(): a column filter with a column's name
 * replaces the generated one; any other filter, including regular Filament
 * filters, is added to the picker.
 *
 *     ->filters([
 *         AdvancedFilter::make()
 *             ->filters([
 *                 NumericFilter::make('shipping_price'),
 *                 TextFilter::make('customer.name')->relationship('customer', 'name')->multiple()->preload(),
 *                 Filter::make('is_active')->query(fn (Builder $query) => $query->where('is_active', true))->toggle(),
 *             ])
 *             ->excludeColumns(['created_at'])
 *             ->defaultFilters([['status']]),
 *     ])
 */
class AdvancedFilter extends BaseFilter
{
    public const GROUPS = 'or_groups';

    public const FILTERS = 'filters';

    /** @var array<string> | Closure | null */
    protected array | Closure | null $includedColumns = null;

    /** @var array<string> | Closure */
    protected array | Closure $excludedColumns = [];

    /** @var array<ColumnFilter | BaseFilter> | Closure */
    protected array | Closure $builderFilters = [];

    /** @var array<int, array<int, string>> | Closure | null */
    protected array | Closure | null $defaultFilterGroups = null;

    protected bool | Closure $hasOrGroups = true;

    protected bool | Closure $shouldIncludeHiddenColumns = true;

    /** @var array<string, string | BackedEnum> | Closure */
    protected array | Closure $filterIcons = [];

    protected bool | Closure $isFilterPickerSearchable = false;

    /** @var array<string, int | null> | int | Closure | null */
    protected array | int | Closure | null $filterPickerColumns = null;

    protected string | Closure | null $filterPickerWidth = null;

    protected string | Closure | null $filterPickerMaxHeight = null;

    protected bool | Closure $shouldPrependFilterGroupLabels = true;

    protected bool | Closure $shouldPrependFilterGroupLabelWhenSoleGroup = false;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $indicatorColors = null;

    protected bool | Closure $hasExpandViewLink = true;

    protected ?Closure $modifyGeneratedFilterUsing = null;

    /** @var array<string, ColumnFilter> | null */
    protected ?array $cachedFilters = null;

    public static function getDefaultName(): ?string
    {
        return 'advanced_filter_builder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-dynamic-tables::dynamic-tables.filter_builder.label'));

        $this->form(fn (AdvancedFilter $filter): array => [
            View::make('filament-dynamic-tables::filter-builder.expand-link')
                ->visible(fn (): bool => method_exists($livewire = $filter->getLivewire(), 'canExpandDynamicTableFilterBuilder') && $livewire->canExpandDynamicTableFilterBuilder()),
            $filter->makeGroupsRepeater(),
        ]);

        $this->query(function (Builder $query, array $data): void {
            $this->applyGroupsToQuery($query, $data);
        });

        $this->indicateUsing(fn (array $state): array => $this->getGroupIndicators($state));

        $this->resetState(fn (): array => [self::GROUPS => $this->getDefaultGroupsState()]);

        $this->columnSpanFull();
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Your own filters: column filters (TextFilter, NumericFilter,
     * DateFilter, SelectFilter, BooleanFilter) or regular Filament filters.
     *
     * @param  array<ColumnFilter | BaseFilter> | Closure  $filters
     */
    public function filters(array | Closure $filters): static
    {
        $this->builderFilters = $filters;
        $this->cachedFilters = null;

        return $this;
    }

    /**
     * Only generate column filters for these columns (dotted relationship
     * names such as "customer.name" work).
     *
     * @param  array<string> | Closure | null  $columns
     */
    public function includeColumns(array | Closure | null $columns): static
    {
        $this->includedColumns = $columns;
        $this->cachedFilters = null;

        return $this;
    }

    /**
     * @param  array<string> | Closure  $columns
     */
    public function excludeColumns(array | Closure $columns): static
    {
        $this->excludedColumns = $columns;
        $this->cachedFilters = null;

        return $this;
    }

    /**
     * Columns the user has toggled off still get a filter by default.
     */
    public function includeHiddenColumns(bool | Closure $condition = true): static
    {
        $this->shouldIncludeHiddenColumns = $condition;

        return $this;
    }

    /**
     * The filters shown when the builder is empty, one array per group:
     * [['status', 'created_at']] or [['status'], ['status']]. [] shows none.
     *
     * @param  array<int, array<int, string>> | Closure | null  $groups
     */
    public function defaultFilters(array | Closure | null $groups): static
    {
        $this->defaultFilterGroups = $groups;

        return $this;
    }

    public function orGroups(bool | Closure $condition = true): static
    {
        $this->hasOrGroups = $condition;

        return $this;
    }

    public function hasOrGroups(): bool
    {
        return (bool) $this->evaluate($this->hasOrGroups);
    }

    /**
     * Icons shown in the filter picker, keyed by filter name.
     *
     * @param  array<string, string | BackedEnum> | Closure  $icons
     */
    public function icons(array | Closure $icons): static
    {
        $this->filterIcons = $icons;

        return $this;
    }

    public function filterPickerSearch(bool | Closure $condition = true): static
    {
        $this->isFilterPickerSearchable = $condition;

        return $this;
    }

    public function isFilterPickerSearchable(): bool
    {
        return (bool) $this->evaluate($this->isFilterPickerSearchable);
    }

    /**
     * @param  array<string, int | null> | int | Closure | null  $columns  an integer applies from the lg breakpoint
     */
    public function filterPickerColumns(array | int | Closure | null $columns = 2): static
    {
        $this->filterPickerColumns = $columns;

        return $this;
    }

    /**
     * @return array<string, int | null> | null
     */
    public function getFilterPickerColumns(): ?array
    {
        $columns = $this->evaluate($this->filterPickerColumns);

        if ($columns === null) {
            return null;
        }

        return is_array($columns) ? $columns : ['lg' => $columns];
    }

    public function filterPickerWidth(string | Closure | null $width): static
    {
        $this->filterPickerWidth = $width;

        return $this;
    }

    public function getFilterPickerWidth(): ?string
    {
        return $this->evaluate($this->filterPickerWidth);
    }

    /**
     * Any CSS length: the picker scrolls past it.
     */
    public function filterPickerMaxHeight(string | Closure | null $height): static
    {
        $this->filterPickerMaxHeight = $height;

        return $this;
    }

    public function getFilterPickerMaxHeight(): ?string
    {
        return $this->evaluate($this->filterPickerMaxHeight);
    }

    public function prependFilterGroupLabels(bool | Closure $condition = true, bool | Closure | null $prependFilterGroupLabelWhenSoleGroup = null): static
    {
        $this->shouldPrependFilterGroupLabels = $condition;

        if ($prependFilterGroupLabelWhenSoleGroup !== null) {
            $this->shouldPrependFilterGroupLabelWhenSoleGroup = $prependFilterGroupLabelWhenSoleGroup;
        }

        return $this;
    }

    /**
     * Badge colors of the filter groups, in order. Unlisted default colors
     * follow the ones you pass.
     *
     * @param  array<string> | Closure | null  $colors
     */
    public function indicatorColors(array | Closure | null $colors): static
    {
        $this->indicatorColors = $colors;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getIndicatorColors(): array
    {
        $defaults = ['primary', 'info', 'gray', 'success', 'danger', 'warning'];
        $colors = $this->evaluate($this->indicatorColors) ?? [];

        return array_values(array_unique([...$colors, ...$defaults]));
    }

    /**
     * The "Expand view" link that opens the builder in a slide-over when
     * the filters are shown in a dropdown.
     */
    public function expandViewLink(bool | Closure $condition = true): static
    {
        $this->hasExpandViewLink = $condition;

        return $this;
    }

    public function hasExpandViewLink(): bool
    {
        return (bool) $this->evaluate($this->hasExpandViewLink);
    }

    /**
     * Tweak every generated column filter:
     * ->modifyGeneratedFilterUsing(fn (ColumnFilter $filter, Column $column) => $filter->icon('heroicon-m-bolt')).
     */
    public function modifyGeneratedFilterUsing(?Closure $callback): static
    {
        $this->modifyGeneratedFilterUsing = $callback;
        $this->cachedFilters = null;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    /**
     * Every filter available in the picker, keyed by name.
     *
     * @return array<string, ColumnFilter>
     */
    public function getBuilderFilters(): array
    {
        if ($this->cachedFilters !== null) {
            return $this->cachedFilters;
        }

        $table = $this->getTable();
        $record = ($model = $table->getModel()) ? app($model) : null;

        $included = $this->evaluate($this->includedColumns);
        $excluded = $this->evaluate($this->excludedColumns) ?? [];

        $custom = [];

        foreach ($this->evaluate($this->builderFilters) ?? [] as $filter) {
            if ($filter instanceof BaseFilter) {
                $filter->table($table);
                $filter = WrappedFilter::wrap($filter);
            }

            if (! $filter instanceof ColumnFilter) {
                continue;
            }

            $custom[$filter->getName()] = $filter;
        }

        $filters = [];

        foreach ($table->getColumns() as $column) {
            $name = $column->getName();

            if (($included !== null) && (! in_array($name, $included, true))) {
                continue;
            }

            if (in_array($name, $excluded, true) || $column->isHidden()) {
                continue;
            }

            if ((! $this->evaluate($this->shouldIncludeHiddenColumns)) && $column->isToggledHidden()) {
                continue;
            }

            if (array_key_exists($name, $custom)) {
                $filters[$name] = $custom[$name]->column($column);
                unset($custom[$name]);

                continue;
            }

            try {
                $filter = $this->makeFilterForColumn($column, $record);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if (! $filter) {
                continue;
            }

            if ($this->modifyGeneratedFilterUsing) {
                $filter = $this->evaluate($this->modifyGeneratedFilterUsing, [
                    'filter' => $filter,
                    'column' => $column,
                ]) ?? $filter;
            }

            $filters[$name] = $filter;
        }

        foreach ($custom as $name => $filter) {
            if (in_array($name, $excluded, true)) {
                continue;
            }

            $filters[$name] = $filter;
        }

        $icons = $this->evaluate($this->filterIcons) ?? [];

        foreach ($filters as $name => $filter) {
            $filter->advancedFilter($this);

            if (array_key_exists($name, $icons) && blank($filter->getIcon())) {
                $filter->icon($icons[$name]);
            }
        }

        return $this->cachedFilters = $filters;
    }

    public function getBuilderFilter(string $name): ?ColumnFilter
    {
        return $this->getBuilderFilters()[$name] ?? null;
    }

    protected function makeFilterForColumn(Column $column, ?Model $record): ?ColumnFilter
    {
        if ($column instanceof ColorColumn) {
            return null;
        }

        $name = $column->getName();
        $attribute = str_contains($name, '.') ? (string) str($name)->afterLast('.') : $name;
        $isRelationship = str_contains($name, '.');
        $castAttribute = $isRelationship ? null : $attribute;

        if ($aggregate = $this->getColumnAggregate($column)) {
            return NumericFilter::make($name)->column($column)->aggregate($aggregate)->integer($aggregate['type'] === 'count');
        }

        if ($this->hasCustomState($column)) {
            return null;
        }

        $filter = match (true) {
            ($column instanceof TextColumn) && ($column->isDate() || $column->isDateTime()) => DateFilter::make($name),
            ($column instanceof TextColumn) && ($column->isNumeric() || $column->isMoney()) => NumericFilter::make($name),
            $column instanceof SelectColumn => SelectFilter::make($name)->options($this->getSelectColumnOptions($column)),
            ($column instanceof ToggleColumn) || ($column instanceof CheckboxColumn) => BooleanFilter::make($name),
            ($column instanceof IconColumn) && $column->isBoolean() => BooleanFilter::make($name),
            ($column instanceof IconColumn) || ($column instanceof ImageColumn) => BooleanFilter::make($name)->includeOperators([BooleanOperator::IS_NOT_EMPTY, BooleanOperator::IS_EMPTY]),
            ($enumOptions = $this->getEnumOptions($record, $castAttribute)) !== null => SelectFilter::make($name)->options($enumOptions),
            $this->isCastAs($record, $castAttribute, ['bool', 'boolean']) => BooleanFilter::make($name),
            $this->isCastAs($record, $castAttribute, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp']) => DateFilter::make($name),
            $this->isCastAs($record, $castAttribute, ['int', 'integer', 'float', 'double', 'real', 'decimal']) => NumericFilter::make($name),
            $column instanceof TextColumn => TextFilter::make($name),
            default => null,
        };

        return $filter?->column($column);
    }

    /**
     * @return array{type: string, relationship: string, column: string | null} | null
     */
    protected function getColumnAggregate(Column $column): ?array
    {
        $first = fn (mixed $relationships): ?string => is_array($relationships)
            ? (is_string($key = array_key_first($relationships)) ? $key : (is_string($relationships[$key] ?? null) ? $relationships[$key] : null))
            : (is_string($relationships) ? $relationships : null);

        if (filled($relationship = $first($column->getRelationshipsToCount()))) {
            return ['type' => 'count', 'relationship' => $relationship, 'column' => null];
        }

        foreach (['avg' => 'Avg', 'sum' => 'Sum', 'min' => 'Min', 'max' => 'Max'] as $type => $method) {
            $relationship = $first($column->{"getRelationshipTo{$method}"}());
            $aggregatedColumn = $column->{"getColumnTo{$method}"}();

            if (filled($relationship) && is_string($aggregatedColumn)) {
                return ['type' => $type, 'relationship' => $relationship, 'column' => $aggregatedColumn];
            }
        }

        return null;
    }

    protected function hasCustomState(Column $column): bool
    {
        try {
            return (fn (): bool => isset($this->getStateUsing))->call($column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function getSelectColumnOptions(SelectColumn $column): array
    {
        return array_map(
            fn (mixed $label): string => is_array($label) ? '' : strip_tags((string) $label),
            $column->getOptions(),
        );
    }

    /**
     * @return array<string, string> | null
     */
    protected function getEnumOptions(?Model $record, ?string $attribute): ?array
    {
        if ((! $record) || blank($attribute)) {
            return null;
        }

        $cast = $record->getCasts()[$attribute] ?? null;

        if ((! is_string($cast)) || (! enum_exists($cast)) || (! is_subclass_of($cast, BackedEnum::class))) {
            return null;
        }

        return collect($cast::cases())
            ->mapWithKeys(fn (BackedEnum $case): array => [
                $case->value => $case instanceof HasLabel ? (string) $case->getLabel() : (string) str($case->name)->headline(),
            ])
            ->all();
    }

    /**
     * @param  array<string>  $types
     */
    protected function isCastAs(?Model $record, ?string $attribute, array $types): bool
    {
        if ((! $record) || blank($attribute)) {
            return false;
        }

        $cast = $record->getCasts()[$attribute] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        return in_array(strtolower((string) str($cast)->before(':')), $types, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public function makeGroupsRepeater(): Repeater
    {
        $hasOrGroups = $this->hasOrGroups();

        return Repeater::make(self::GROUPS)
            ->hiddenLabel()
            ->schema([$this->makeFiltersBuilder()])
            ->default(fn (): array => $this->getDefaultGroupsState())
            ->addActionLabel(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.add_group'))
            ->addable(fn (Repeater $component): bool => $hasOrGroups || blank($component->getRawState()))
            ->maxItems($hasOrGroups ? null : 1)
            ->deletable(fn (Repeater $component): bool => $hasOrGroups && (count($component->getRawState() ?? []) > 1))
            ->reorderable(false)
            ->collapsible(false)
            ->itemLabel(function (Repeater $component, string $uuid): ?string {
                if (! $this->hasOrGroups()) {
                    return null;
                }

                $position = array_search($uuid, array_keys($component->getRawState() ?? []), true);

                return __('filament-dynamic-tables::dynamic-tables.filter_builder.group', ['number' => ($position === false ? 0 : $position) + 1]);
            })
            ->labelBetweenItems(__('filament-dynamic-tables::dynamic-tables.filter_builder.or'))
            ->deleteAction(fn (Action $action): Action => $action->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::FILTER_BUILDER_DELETE, 'heroicon-m-trash')))
            ->extraAttributes(['class' => 'fdt-filter-builder' . ($hasOrGroups ? '' : ' fdt-filter-builder-single')]);
    }

    protected function makeFiltersBuilder(): BuilderField
    {
        $builder = BuilderField::make(self::FILTERS)
            ->hiddenLabel()
            ->blocks(fn (): array => $this->getBlocks())
            ->addActionLabel(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.add_filter'))
            ->reorderable(false)
            ->collapsible(false)
            ->blockNumbers(false)
            ->labelBetweenItems(__('filament-dynamic-tables::dynamic-tables.filter_builder.and'))
            ->deleteAction(fn (Action $action): Action => $action->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::FILTER_BUILDER_DELETE)))
            ->extraAttributes(fn (): array => array_filter([
                'class' => 'fdt-filter-builder-group',
                'style' => filled($height = $this->getFilterPickerMaxHeight()) ? "--fdt-filter-picker-max-height: {$height}" : null,
            ]));

        if (filled($columns = $this->getFilterPickerColumns())) {
            $builder->blockPickerColumns($columns);
        }

        if (filled($width = $this->getFilterPickerWidth())) {
            $builder->blockPickerWidth($width);
        }

        if ($this->isFilterPickerSearchable() && method_exists($builder, 'searchable')) {
            $builder->searchable();
        }

        return $builder;
    }

    /**
     * @return array<Block>
     */
    protected function getBlocks(): array
    {
        return collect($this->getBuilderFilters())
            ->map(fn (ColumnFilter $filter, string $name): Block => Block::make($name)
                ->label($filter->getLabel())
                ->icon($filter->getIcon())
                ->schema($filter->getFormSchema()))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDefaultGroupsState(): array
    {
        $groups = $this->evaluate($this->defaultFilterGroups);

        if ($groups === null) {
            $custom = collect($this->evaluate($this->builderFilters) ?? [])
                ->map(fn (mixed $filter): ?string => ($filter instanceof BaseFilter) || ($filter instanceof ColumnFilter) ? $filter->getName() : null)
                ->filter()
                ->reject(fn (string $name): bool => $this->isColumnFilterOverride($name))
                ->values()
                ->all();

            $groups = [$custom];
        }

        $state = [];

        foreach ($groups as $group) {
            $filters = [];

            foreach ((array) $group as $name) {
                $filter = is_string($name) ? $this->getBuilderFilter($name) : null;

                if (! $filter) {
                    continue;
                }

                $filters[(string) Str::uuid()] = [
                    'type' => $name,
                    'data' => $this->getDefaultFilterData($filter),
                ];
            }

            $state[(string) Str::uuid()] = [self::FILTERS => $filters];

            if (! $this->hasOrGroups()) {
                break;
            }
        }

        if (blank($state)) {
            $state[(string) Str::uuid()] = [self::FILTERS => []];
        }

        return $state;
    }

    protected function isColumnFilterOverride(string $name): bool
    {
        try {
            return $this->getTable()->getColumn($name) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultFilterData(ColumnFilter $filter): array
    {
        if ($filter instanceof WrappedFilter) {
            return [];
        }

        return ['operator' => $filter->getDefaultOperator()];
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    /**
     * Accepts the builder's own state, or the short form used by preset
     * views and saved views:
     *
     *     [
     *         ['status' => ['value' => 'new'], 'created_at' => ['operator' => 'in_the_last', 'value' => 1, 'unit' => 'quarters']],
     *         ['status' => ['value' => 'cancelled']],
     *     ]
     *
     * @param  array<mixed>  $state
     * @return array<string, mixed>
     */
    public function normalizeState(array $state): array
    {
        if (array_key_exists(self::GROUPS, $state)) {
            $groups = is_array($state[self::GROUPS]) ? $state[self::GROUPS] : [];
            $normalized = [];

            foreach ($groups as $groupKey => $group) {
                $filters = [];

                foreach ((array) ($group[self::FILTERS] ?? []) as $ruleKey => $rule) {
                    if ((! is_array($rule)) || (! is_string($rule['type'] ?? null))) {
                        continue;
                    }

                    $filters[is_string($ruleKey) ? $ruleKey : (string) Str::uuid()] = [
                        'type' => $rule['type'],
                        'data' => is_array($rule['data'] ?? null) ? $rule['data'] : [],
                    ];
                }

                $normalized[is_string($groupKey) ? $groupKey : (string) Str::uuid()] = [self::FILTERS => $filters];
            }

            return [self::GROUPS => $normalized];
        }

        if (blank($state)) {
            return [self::GROUPS => $this->getDefaultGroupsState()];
        }

        // The short form: a list of groups, each "filter name => data".
        $groups = array_is_list($state) ? $state : [$state];
        $normalized = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $filters = [];

            foreach ($group as $name => $data) {
                if (is_int($name) && is_array($data) && is_string($data['type'] ?? null)) {
                    $name = $data['type'];
                    $data = $data['data'] ?? [];
                }

                if ((! is_string($name)) || (! $this->getBuilderFilter($name))) {
                    continue;
                }

                $data = is_array($data) ? $data : ['value' => $data];

                $filters[(string) Str::uuid()] = ['type' => $name, 'data' => Arr::except($data, ['column'])];
            }

            $normalized[(string) Str::uuid()] = [self::FILTERS => $filters];
        }

        return [self::GROUPS => filled($normalized) ? $normalized : $this->getDefaultGroupsState()];
    }

    /**
     * The groups and filters that actually filter something.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, array{filter: ColumnFilter, data: array<string, mixed>}>>
     */
    public function getActiveGroups(array $data): array
    {
        $groups = [];

        foreach ((array) ($data[self::GROUPS] ?? []) as $groupKey => $group) {
            $rules = [];

            foreach ((array) ($group[self::FILTERS] ?? []) as $ruleKey => $rule) {
                $filter = is_string($rule['type'] ?? null) ? $this->getBuilderFilter($rule['type']) : null;
                $ruleData = is_array($rule['data'] ?? null) ? $rule['data'] : [];

                if ((! $filter) || (! $filter->isActive($ruleData))) {
                    continue;
                }

                $rules[(string) $ruleKey] = ['filter' => $filter, 'data' => $ruleData];
            }

            if (filled($rules)) {
                $groups[(string) $groupKey] = $rules;
            }

            if (! $this->hasOrGroups()) {
                break;
            }
        }

        return $groups;
    }

    /**
     * A stable form of the state without keys or unused filters, used to
     * compare views.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<int, array{type: string, data: array<string, mixed>}>>
     */
    public function getComparableState(array $data): array
    {
        return collect($this->getActiveGroups($data))
            ->map(fn (array $rules): array => collect($rules)
                ->map(fn (array $rule): array => ['type' => $rule['filter']->getName(), 'data' => $rule['data']])
                ->values()
                ->all())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutRule(array $data, ?string $field): array
    {
        if (blank($field)) {
            return [self::GROUPS => $this->getDefaultGroupsState()];
        }

        [$groupKey, $ruleKey] = array_pad(explode('.', $field, 2), 2, null);

        $groups = (array) ($data[self::GROUPS] ?? []);

        if (filled($ruleKey)) {
            unset($groups[$groupKey][self::FILTERS][$ruleKey]);
        }

        if ((blank($ruleKey) || blank($groups[$groupKey][self::FILTERS] ?? [])) && (count($groups) > 1)) {
            unset($groups[$groupKey]);
        }

        return [self::GROUPS => filled($groups) ? $groups : $this->getDefaultGroupsState()];
    }

    /*
    |--------------------------------------------------------------------------
    | Query & indicators
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyGroupsToQuery(Builder $query, array $data): void
    {
        $groups = $this->getActiveGroups($data);

        if (blank($groups)) {
            return;
        }

        $query->where(function (Builder $query) use ($groups): void {
            $isFirst = true;

            foreach ($groups as $rules) {
                $query->{$isFirst ? 'where' : 'orWhere'}(function (Builder $query) use ($rules): void {
                    foreach ($rules as $rule) {
                        $rule['filter']->apply($query, $rule['data']);
                    }
                });

                $isFirst = false;
            }
        });
    }

    /**
     * One indicator per filter, prefixed with its group and colored by
     * group. Keys are "group.filter", so each can be removed on its own.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, Indicator>
     */
    public function getGroupIndicators(array $data): array
    {
        $groups = $this->getActiveGroups($data);
        $colors = $this->getIndicatorColors();
        $shouldPrepend = $this->evaluate($this->shouldPrependFilterGroupLabels)
            && ((count($groups) > 1) || $this->evaluate($this->shouldPrependFilterGroupLabelWhenSoleGroup));

        $indicators = [];
        $position = 0;

        foreach ($groups as $groupKey => $rules) {
            $color = $colors[$position % count($colors)];

            foreach ($rules as $ruleKey => $rule) {
                $label = $rule['filter']->getIndicatorLabel($rule['data']);

                if (blank($label)) {
                    continue;
                }

                if ($shouldPrepend) {
                    $label = __('filament-dynamic-tables::dynamic-tables.filter_builder.group', ['number' => $position + 1]) . ': ' . $label;
                }

                $indicators["{$groupKey}.{$ruleKey}"] = Indicator::make($label)->color($color);
            }

            $position++;
        }

        return $indicators;
    }
}
