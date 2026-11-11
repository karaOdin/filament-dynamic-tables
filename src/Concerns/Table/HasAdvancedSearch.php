<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use KaraOdin\FilamentDynamicTables\Enums\SearchConstraint;
use Throwable;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * Replaces the table search with a query builder: eight constraints,
 * column selection and AND / OR groups, each group being
 *
 *     ['columns' => ['name'], 'constraint' => 'contains', 'value' => 'John', 'operator' => null]
 *
 * Shortcuts typed in front of a term pick the constraint:
 *
 *     term  contains        -term   does not contain
 *     =term equals          -=term  does not equal
 *     ^term starts with     -^term  does not start with
 *     $term ends with       -$term  does not end with
 *
 * and, with boolean syntax, & and | start a new AND / OR group.
 */
trait HasAdvancedSearch
{
    /**
     * @var array<int, array{columns?: array<int, string>, constraint?: ?string, value?: string, operator?: ?string}>
     */
    public array $advancedSearch = [];

    public function isAdvancedSearchActive(): bool
    {
        return $this->getDynamicTablesConfig()->isAdvancedSearchEnabled()
            && $this->getTable()->isSearchable()
            && (! $this->getTable()->hasSearchUsingCallback());
    }

    /**
     * Search is kept in the URL on resource List pages and Manage Related
     * Records pages. Override to change that.
     */
    protected function shouldBindAdvancedSearchToUrl(): bool
    {
        return ($this instanceof ListRecords)
            || (class_exists(ManageRelatedRecords::class) && ($this instanceof ManageRelatedRecords));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function queryStringHasAdvancedSearch(): array
    {
        if (! $this->shouldBindAdvancedSearchToUrl()) {
            return [];
        }

        return [
            'advancedSearch' => ['as' => 'advanced_search', 'except' => [], 'history' => false],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Columns & constraints
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string> column name => label
     */
    public function getAdvancedSearchableColumns(): array
    {
        return collect($this->getTable()->getColumns())
            ->filter(fn (Column $column): bool => $column->isGloballySearchable() && (! $column->isHidden()) && (! $column->isToggledHidden()))
            ->mapWithKeys(fn (Column $column): array => [$column->getName() => strip_tags((string) $column->getLabel())])
            ->all();
    }

    /**
     * Extra searchable database columns (->searchable(['customer.email'])
     * on the table), shown in the "Database" section.
     *
     * @return array<string, string>
     */
    public function getAdvancedSearchExtraColumns(): array
    {
        $table = $this->getTable();

        if (! method_exists($table, 'getExtraSearchableColumns')) { // @phpstan-ignore function.alreadyNarrowedType
            return [];
        }

        return collect($table->getExtraSearchableColumns())
            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
            ->reject(fn (string $column): bool => array_key_exists($column, $this->getAdvancedSearchableColumns()))
            ->mapWithKeys(fn (string $column): array => [$column => (string) str($column)->replace(['.', '_'], ' ')->ucfirst()])
            ->all();
    }

    /**
     * @return array<string, SearchConstraint>
     */
    public function getAdvancedSearchConstraintOptions(): array
    {
        return collect($this->getDynamicTablesConfig()->getAdvancedSearchConstraints())
            ->mapWithKeys(fn (SearchConstraint $constraint): array => [$constraint->value => $constraint])
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    /**
     * The active search groups. A plain Filament search (?search=..., the
     * searchTable() test helper, views saved before advanced search was on)
     * becomes a single "contains" group.
     *
     * @return array<int, array{columns: array<int, string>, constraint: string, value: string, operator: ?string}>
     */
    public function getAdvancedSearchGroups(): array
    {
        if (filled($this->advancedSearch)) {
            return $this->normalizeAdvancedSearchGroups($this->advancedSearch, parse: false);
        }

        $search = trim((string) ($this->tableSearch ?? ''));

        if ($search === '') {
            return [];
        }

        return $this->normalizeAdvancedSearchGroups([['value' => $search]], parse: true);
    }

    /**
     * Called by the search input with the groups the user built. Constraint
     * shortcuts and boolean syntax in the values are resolved here.
     *
     * @param  array<int, mixed>  $groups
     */
    public function setAdvancedSearch(array $groups): void
    {
        if (! $this->isAdvancedSearchActive()) {
            return;
        }

        $this->advancedSearch = $this->normalizeAdvancedSearchGroups($groups, parse: true);
        $this->syncTableSearchWithAdvancedSearch();
    }

    public function resetAdvancedSearch(): void
    {
        $this->advancedSearch = [];
        $this->syncTableSearchWithAdvancedSearch();
    }

    /**
     * Keeps Filament's $tableSearch filled while a search is active, so the
     * search indicator, empty state and session persistence keep working.
     */
    protected function syncTableSearchWithAdvancedSearch(): void
    {
        $this->tableSearch = collect($this->advancedSearch)
            ->map(fn (array $group): string => (string) ($group['value'] ?? ''))
            ->filter()
            ->implode(' ');

        $this->persistAdvancedSearchState();

        $this->updatedTableSearch();

        $this->flushCachedTableRecords();
    }

    public function updatedTableSearch(): void
    {
        // A search typed into Filament's own input replaces the groups.
        if ($this->isAdvancedSearchActive() && filled($this->advancedSearch)) {
            $summary = collect($this->advancedSearch)->map(fn (array $group): string => (string) ($group['value'] ?? ''))->filter()->implode(' ');

            if ($summary !== (string) $this->tableSearch) {
                $this->advancedSearch = [];
                $this->persistAdvancedSearchState();
            }
        }

        $this->callFilamentTableMethod('updatedTableSearch');
    }

    /**
     * @param  array<int, mixed>  $groups
     * @return array<int, array{columns: array<int, string>, constraint: string, value: string, operator: ?string}>
     */
    protected function normalizeAdvancedSearchGroups(array $groups, bool $parse): array
    {
        $config = $this->getDynamicTablesConfig();
        $allowedConstraints = $this->getAdvancedSearchConstraintOptions();
        $allowedColumns = $config->isAdvancedSearchColumnSelectionEnabled()
            ? [...array_keys($this->getAdvancedSearchableColumns()), ...array_keys($this->getAdvancedSearchExtraColumns())]
            : [];
        $default = $config->getAdvancedSearchDefaultConstraint();
        $hasGrouping = $config->isAdvancedSearchGroupingEnabled();

        $normalized = [];

        foreach (array_values($groups) as $group) {
            if (is_string($group)) {
                $group = ['value' => $group];
            }

            if (! is_array($group)) {
                continue;
            }

            $value = is_scalar($group['value'] ?? null) ? trim((string) $group['value']) : '';
            $columns = array_values(array_intersect(array_filter((array) ($group['columns'] ?? []), 'is_string'), $allowedColumns));
            $constraint = is_string($group['constraint'] ?? null) && isset($allowedConstraints[$group['constraint']]) ? $group['constraint'] : null;
            $operator = in_array($group['operator'] ?? null, ['and', 'or'], true) ? $group['operator'] : 'and';

            $chunks = ($parse && $config->isAdvancedSearchBooleanSyntaxEnabled())
                ? $this->splitAdvancedSearchBooleanSyntax($value)
                : [[null, $value]];

            foreach ($chunks as $index => [$chunkOperator, $chunkValue]) {
                $chunkConstraint = $constraint;

                if ($parse && $config->isAdvancedSearchKeyboardSyntaxEnabled()) {
                    [$parsedConstraint, $chunkValue] = $this->parseAdvancedSearchConstraintSyntax($chunkValue);

                    if ($parsedConstraint && isset($allowedConstraints[$parsedConstraint->value])) {
                        $chunkConstraint = $parsedConstraint->value;
                    }
                }

                if ($chunkValue === '') {
                    continue;
                }

                $normalized[] = [
                    'columns' => $columns,
                    'constraint' => $chunkConstraint ?? $default->value,
                    'value' => $chunkValue,
                    'operator' => ($index === 0) ? $operator : $chunkOperator,
                ];
            }
        }

        if (! $hasGrouping) {
            $normalized = array_slice($normalized, 0, 1);
        }

        $normalized = array_slice($normalized, 0, $config->getAdvancedSearchMaxGroups());

        if (filled($normalized)) {
            $normalized[0]['operator'] = null;
        }

        return $normalized;
    }

    /**
     * Splits "a & b | c" into groups. Quoted text is kept as is.
     *
     * @return array<int, array{0: ?string, 1: string}>
     */
    protected function splitAdvancedSearchBooleanSyntax(string $value): array
    {
        $chunks = [];
        $current = '';
        $operator = null;
        $inQuotes = false;

        foreach (mb_str_split($value) as $character) {
            if ($character === '"') {
                $inQuotes = ! $inQuotes;
            }

            if ((! $inQuotes) && in_array($character, ['&', '|'], true)) {
                $chunks[] = [$operator, trim($current)];
                $operator = $character === '&' ? 'and' : 'or';
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $chunks[] = [$operator, trim($current)];

        return $chunks;
    }

    /**
     * @return array{0: ?SearchConstraint, 1: string}
     */
    protected function parseAdvancedSearchConstraintSyntax(string $value): array
    {
        if (! preg_match('/^(-?)([=^$]?)(.*)$/su', $value, $matches)) {
            return [null, $value];
        }

        [, $negation, $symbol, $rest] = $matches;

        if (($negation === '') && ($symbol === '')) {
            return [null, $value];
        }

        $rest = trim($rest);

        // A lone "-" or a number such as "-250" is a value, not a shortcut.
        if (($rest === '') || (($symbol === '') && is_numeric($rest))) {
            return [null, $value];
        }

        $constraint = match ($symbol) {
            '=' => SearchConstraint::Equals,
            '^' => SearchConstraint::StartsWith,
            '$' => SearchConstraint::EndsWith,
            default => SearchConstraint::Contains,
        };

        return [$negation === '-' ? $constraint->negate() : $constraint, $rest];
    }

    protected function persistAdvancedSearchState(): void
    {
        if (! $this->getTable()->persistsSearchInSession()) {
            return;
        }

        session()->put($this->getDynamicTablesSessionKey('advanced_search'), $this->advancedSearch);
    }

    protected function restoreAdvancedSearchStateFromSession(): void
    {
        if (filled($this->advancedSearch) || (! $this->getTable()->persistsSearchInSession())) {
            return;
        }

        $state = session()->get($this->getDynamicTablesSessionKey('advanced_search'));

        if (is_array($state) && array_is_list($state)) {
            $this->advancedSearch = $this->normalizeAdvancedSearchGroups($state, parse: false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    /**
     * AND groups share one where clause; OR starts a new one:
     * (name contains John AND email contains gmail) OR (status equals active).
     */
    protected function applyAdvancedSearchToTableQuery(Builder $query): Builder
    {
        $groups = $this->getAdvancedSearchGroups();

        if (blank($groups)) {
            return $query;
        }

        $bundles = [];

        foreach ($groups as $group) {
            if (($group['operator'] === 'or') || blank($bundles)) {
                $bundles[] = [];
            }

            $bundles[array_key_last($bundles)][] = $group;
        }

        $query->where(function (Builder $query) use ($bundles): void {
            foreach ($bundles as $index => $bundle) {
                $query->{$index === 0 ? 'where' : 'orWhere'}(function (Builder $query) use ($bundle): void {
                    foreach ($bundle as $group) {
                        $query->where(fn (Builder $query) => $this->applyAdvancedSearchGroupToQuery($query, $group));
                    }
                });
            }
        });

        return $query;
    }

    /**
     * @param  array{columns: array<int, string>, constraint: string, value: string, operator: ?string}  $group
     */
    protected function applyAdvancedSearchGroupToQuery(Builder $query, array $group): void
    {
        $table = $this->getTable();
        $constraint = SearchConstraint::tryFrom($group['constraint']) ?? SearchConstraint::Contains;
        $positive = $constraint->isNegated() ? $constraint->negate() : $constraint;
        $selected = $group['columns'];
        $searchesAll = blank($selected);

        $columns = collect($table->getColumns())
            ->filter(fn (Column $column): bool => $column->isGloballySearchable() && (! $column->isHidden()))
            ->when(! $searchesAll, fn ($columns) => $columns->filter(fn (Column $column): bool => in_array($column->getName(), $selected, true)))
            ->values()
            ->all();

        $extraColumns = $searchesAll ? [] : array_values(array_intersect($selected, array_keys($this->getAdvancedSearchExtraColumns())));

        $terms = in_array($positive, [SearchConstraint::Contains], true)
            ? $this->splitAdvancedSearchWords($group['value'])
            : [trim($group['value'], '"')];

        $condition = function (Builder $query) use ($terms, $columns, $extraColumns, $positive, $searchesAll): void {
            foreach ($terms as $term) {
                $query->where(fn (Builder $query) => $this->applyAdvancedSearchTermToColumns($query, $columns, $extraColumns, $positive, $term, $searchesAll));
            }
        };

        if ($constraint->isNegated()) {
            $query->whereNot($condition);

            return;
        }

        $condition($query);
    }

    /**
     * Words of a "contains" search: split like Filament's search (unless the
     * table uses splitSearchTerms(false)), "quoted phrases" kept together.
     *
     * @return array<int, string>
     */
    protected function splitAdvancedSearchWords(string $value): array
    {
        $value = trim($value);

        if (! $this->getTable()->shouldSplitSearchTerms()) {
            return [trim($value, '"')];
        }

        preg_match_all('/"([^"]*)"|(\S+)/u', $value, $matches, PREG_SET_ORDER);

        $words = collect($matches)
            ->map(fn (array $match): string => filled($match[2] ?? null) ? $match[2] : $match[1])
            ->map(fn (string $word): string => trim($word))
            ->filter()
            ->values();

        if (filled($max = $this->getDynamicTablesConfig()->getAdvancedSearchMaxWords())) {
            $words = $words->take($max);
        }

        return $words->all();
    }

    /**
     * Matches when any of the columns matches the term.
     *
     * @param  array<int, Column>  $columns
     * @param  array<int, string>  $extraColumns
     */
    protected function applyAdvancedSearchTermToColumns(Builder $query, array $columns, array $extraColumns, SearchConstraint $constraint, string $term, bool $searchesAll): void
    {
        $isFirst = true;

        foreach ($columns as $column) {
            $this->applyAdvancedSearchToColumn($query, $column, $constraint, $term, $isFirst);
        }

        foreach ($extraColumns as $column) {
            $this->applyAdvancedSearchToDatabaseColumn($query, $column, $constraint, $term, $isFirst);
        }

        if ($searchesAll) {
            $this->applyAdvancedSearchToExtraColumns($query, $constraint, $term, $isFirst);
        }

        // Nothing could be searched for this term: match nothing.
        if ($isFirst) {
            $query->whereRaw('1 = 0');
        }
    }

    protected function applyAdvancedSearchToColumn(Builder $query, Column $column, SearchConstraint $constraint, string $term, bool &$isFirst): void
    {
        $model = $query->getModel();

        if ($searchQuery = $this->getColumnCustomSearchQuery($column)) {
            $query->{$isFirst ? 'where' : 'orWhere'}(fn (Builder $query) => $column->evaluate($searchQuery, [
                'query' => $query,
                'search' => $term,
                'searchQuery' => $term,
                'constraint' => $constraint,
            ]));

            $isFirst = false;

            return;
        }

        $type = $this->getAdvancedSearchColumnType($column, $model);

        if (in_array($type, ['date', 'number'], true) && ($constraint === SearchConstraint::Equals)) {
            $this->applyAdvancedSearchEqualityToColumn($query, $column, $type, $term, $isFirst);

            return;
        }

        foreach ($column->getSearchColumns($model) as $searchColumn) {
            $condition = fn (Builder $query, string $table) => $this->whereAdvancedSearchLike(
                $query,
                str_contains($searchColumn, '.') ? $searchColumn : "{$table}.{$searchColumn}",
                $constraint,
                $term,
                $column->isSearchForcedCaseInsensitive(),
            );

            if ($column->hasRelationship($model)) {
                $relationshipName = $column->getRelationshipName($model);
                $relatedTable = $column->getRelationship($model, $relationshipName)?->getRelated()->getTable() ?? $model->getTable();

                $query->has($relationshipName, '>=', 1, $isFirst ? 'and' : 'or', fn (Builder $query) => $condition($query, $relatedTable));
            } else {
                $query->where(fn (Builder $query) => $condition($query, $model->getTable()), boolean: $isFirst ? 'and' : 'or');
            }

            $isFirst = false;
        }
    }

    protected function applyAdvancedSearchToDatabaseColumn(Builder $query, string $column, SearchConstraint $constraint, string $term, bool &$isFirst): void
    {
        $model = $query->getModel();

        if (str_contains($column, '.')) {
            $relationship = (string) str($column)->beforeLast('.');
            $attribute = (string) str($column)->afterLast('.');

            if (method_exists($model, (string) str($relationship)->before('.'))) {
                $query->has($relationship, '>=', 1, $isFirst ? 'and' : 'or', fn (Builder $query) => $this->whereAdvancedSearchLike($query, $query->qualifyColumn($attribute), $constraint, $term, null));
                $isFirst = false;

                return;
            }
        }

        $query->where(fn (Builder $query) => $this->whereAdvancedSearchLike($query, $query->qualifyColumn($column), $constraint, $term, null), boolean: $isFirst ? 'and' : 'or');
        $isFirst = false;
    }

    /**
     * Closures and columns passed to the table's ->searchable([...]).
     */
    protected function applyAdvancedSearchToExtraColumns(Builder $query, SearchConstraint $constraint, string $term, bool &$isFirst): void
    {
        $table = $this->getTable();

        if (! method_exists($table, 'getExtraSearchableColumns')) { // @phpstan-ignore function.alreadyNarrowedType
            return;
        }

        foreach ($table->getExtraSearchableColumns() as $column) {
            if ($column instanceof Closure) {
                $query->{$isFirst ? 'where' : 'orWhere'}(fn (Builder $query) => $table->evaluate($column, [
                    'query' => $query,
                    'search' => $term,
                    'searchQuery' => $term,
                    'constraint' => $constraint,
                ]));

                $isFirst = false;

                continue;
            }

            if (filled($column) && (! array_key_exists($column, $this->getAdvancedSearchableColumns()))) {
                $this->applyAdvancedSearchToDatabaseColumn($query, $column, $constraint, $term, $isFirst);
            }
        }
    }

    /**
     * Case-insensitive LIKE that never matches NULL, so negating it keeps
     * records without a value.
     */
    protected function whereAdvancedSearchLike(Builder $query, string $column, SearchConstraint $constraint, string $term, ?bool $isCaseInsensitive): void
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $searchTerm = generate_search_term_expression($term, $isCaseInsensitive, $connection);
        $pattern = ($constraint->hasLeadingWildcard() ? '%' : '')
            . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchTerm)
            . ($constraint->hasTrailingWildcard() ? '%' : '');

        $expression = $query->getQuery()->getGrammar()->wrap(generate_search_column_expression($column, $isCaseInsensitive, $connection));

        $query->whereNotNull($column)->whereRaw("{$expression} like ? escape '!'", [$pattern]);
    }

    /**
     * Equals on dates matches the whole day (or the exact second when a
     * time is given); on numbers it compares by value. Terms that are not a
     * date or a number never match these columns.
     */
    protected function applyAdvancedSearchEqualityToColumn(Builder $query, Column $column, string $type, string $term, bool &$isFirst): void
    {
        $model = $query->getModel();

        if ($type === 'number') {
            if (! is_numeric(str_replace(',', '.', $term))) {
                return;
            }

            $value = str_replace(',', '.', $term) + 0;
            $condition = fn (Builder $query, string $qualified) => $query->where($qualified, '=', $value);
        } else {
            [$date, $hasTime] = $this->parseAdvancedSearchDate($term);

            if (! $date) {
                return;
            }

            $condition = $hasTime
                ? fn (Builder $query, string $qualified) => $query->where($qualified, '=', $date->format('Y-m-d H:i:s'))
                : fn (Builder $query, string $qualified) => $query
                    ->where($qualified, '>=', $date->startOfDay()->format('Y-m-d H:i:s'))
                    ->where($qualified, '<=', $date->endOfDay()->format('Y-m-d H:i:s'));
        }

        foreach ($column->getSearchColumns($model) as $searchColumn) {
            if ($column->hasRelationship($model)) {
                $relationshipName = $column->getRelationshipName($model);

                $query->has($relationshipName, '>=', 1, $isFirst ? 'and' : 'or', fn (Builder $query) => $condition($query, $query->qualifyColumn($searchColumn)));
            } else {
                $query->where(fn (Builder $query) => $condition($query, $query->qualifyColumn($searchColumn)), boolean: $isFirst ? 'and' : 'or');
            }

            $isFirst = false;
        }
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: bool}
     */
    protected function parseAdvancedSearchDate(string $term): array
    {
        $table = $this->getTable();
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y/m/d H:i:s', 'Y/m/d H:i', 'Y-m-d', 'Y/m/d', 'Y.m.d'];

        foreach (['getDefaultDateTimeDisplayFormat', 'getDefaultDateDisplayFormat'] as $method) {
            $format = method_exists($table, $method) ? $table->{$method}() : null;

            if (is_string($format) && ($format !== '')) {
                $formats[] = $format;
            }
        }

        foreach (array_unique($formats) as $format) {
            try {
                $date = CarbonImmutable::createFromLocaleFormat($format, app()->getLocale(), $term);
            } catch (Throwable) {
                $date = null;
            }

            if ($date && ($date->format($format) === $term || $date->locale(app()->getLocale())->translatedFormat($format) === $term)) {
                return [$date, (bool) preg_match('/[HhGgis]/', $format)];
            }
        }

        return [null, false];
    }

    protected function getAdvancedSearchColumnType(Column $column, Model $model): string
    {
        if ($column instanceof TextColumn) {
            if ($column->isDate() || $column->isDateTime()) {
                return 'date';
            }

            if ($column->isNumeric() || $column->isMoney()) {
                return 'number';
            }
        }

        $name = $column->getName();

        if (str_contains($name, '.')) {
            return 'text';
        }

        $cast = $model->getCasts()[$name] ?? null;

        if (! is_string($cast)) {
            return 'text';
        }

        $cast = strtolower((string) str($cast)->before(':'));

        return match (true) {
            in_array($cast, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) => 'date',
            in_array($cast, ['int', 'integer', 'float', 'double', 'real', 'decimal'], true) => 'number',
            default => 'text',
        };
    }

    protected function getColumnCustomSearchQuery(Column $column): ?Closure
    {
        try {
            return (fn (): ?Closure => $this->searchQuery ?? null)->call($column);
        } catch (Throwable) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Indicator
    |--------------------------------------------------------------------------
    */

    public function getAdvancedSearchSummary(): string
    {
        $columns = [...$this->getAdvancedSearchableColumns(), ...$this->getAdvancedSearchExtraColumns()];

        return collect($this->getAdvancedSearchGroups())
            ->map(function (array $group, int $index) use ($columns): string {
                $constraint = SearchConstraint::tryFrom($group['constraint']) ?? SearchConstraint::Contains;
                $label = filled($group['columns'])
                    ? implode(', ', array_map(fn (string $column): string => $columns[$column] ?? $column, $group['columns'])) . ' '
                    : '';

                $text = $label . mb_strtolower($constraint->getLabel()) . ' "' . $group['value'] . '"';

                if ($index === 0) {
                    return $text;
                }

                return __('filament-dynamic-tables::dynamic-tables.advanced_search.operators.' . ($group['operator'] ?? 'and')) . ' ' . $text;
            })
            ->implode(' ');
    }

    protected function getAdvancedSearchIndicator(): Indicator
    {
        return Indicator::make(__('filament-dynamic-tables::dynamic-tables.advanced_search.indicator', [
            'search' => $this->getAdvancedSearchSummary(),
        ]))->removeLivewireClickHandler('resetAdvancedSearch');
    }
}
