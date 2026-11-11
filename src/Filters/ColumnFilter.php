<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Components\Component;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * A single filter of the Advanced Filter Builder: a column, an operator and
 * a value. Instances live inside a filter group; the same filter can be
 * added several times, in one group or in several.
 */
abstract class ColumnFilter extends Component
{
    protected string $evaluationIdentifier = 'filter';

    protected string $name;

    protected string | Htmlable | Closure | null $label = null;

    protected string | BackedEnum | Closure | null $icon = null;

    protected string | Closure | null $attribute = null;

    protected string | Closure | null $relationship = null;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $includedOperators = null;

    /** @var array<string> | Closure */
    protected array | Closure $excludedOperators = [];

    protected string | Closure | null $defaultOperator = null;

    protected ?Column $column = null;

    protected ?AdvancedFilter $advancedFilter = null;

    /**
     * Relationship aggregate: ['type' => 'count'|'avg'|..., 'relationship' => ..., 'column' => ...].
     *
     * @var array{type: string, relationship: string, column: string | null} | null
     */
    protected ?array $aggregate = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    /**
     * @return array<string>
     */
    abstract public function getAllOperators(): array;

    abstract public function getDefaultOperatorFallback(): string;

    /**
     * Value fields shown after the operator select.
     *
     * @return array<SchemaComponent>
     */
    abstract protected function getValueSchema(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function applyOperator(Builder $query, string $operator, array $data): void;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function getValueSummary(string $operator, array $data): ?string;

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string | Htmlable | Closure | null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        $label = $this->evaluate($this->label);

        if ($label instanceof Htmlable) {
            $label = strip_tags($label->toHtml());
        }

        if (filled($label)) {
            return (string) $label;
        }

        if ($this->column) {
            return strip_tags((string) $this->column->getLabel());
        }

        return (string) str($this->name)->afterLast('.')->replace('_', ' ')->ucfirst();
    }

    public function icon(string | BackedEnum | Closure | null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): string | BackedEnum | null
    {
        return $this->evaluate($this->icon);
    }

    /**
     * The database column, when it differs from the filter name.
     */
    public function attribute(string | Closure | null $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getAttribute(): string
    {
        $attribute = $this->evaluate($this->attribute);

        if (filled($attribute)) {
            return $attribute;
        }

        return str_contains($this->name, '.') ? (string) str($this->name)->afterLast('.') : $this->name;
    }

    /**
     * The relationship the column lives on. Detected from dotted names
     * (customer.name) automatically.
     */
    public function queriesRelationship(string | Closure | null $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getQueriedRelationship(): ?string
    {
        $relationship = $this->evaluate($this->relationship);

        if (filled($relationship)) {
            return $relationship;
        }

        if (filled($this->evaluate($this->attribute))) {
            return null;
        }

        return str_contains($this->name, '.') ? (string) str($this->name)->beforeLast('.') : null;
    }

    /**
     * @param  array<string> | Closure | null  $operators
     */
    public function includeOperators(array | Closure | null $operators): static
    {
        $this->includedOperators = $operators;

        return $this;
    }

    /**
     * @param  array<string> | Closure  $operators
     */
    public function excludeOperators(array | Closure $operators): static
    {
        $this->excludedOperators = $operators;

        return $this;
    }

    public function defaultOperator(string | Closure | null $operator): static
    {
        $this->defaultOperator = $operator;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getOperators(): array
    {
        $operators = $this->getAllOperators();
        $included = $this->evaluate($this->includedOperators);
        $excluded = $this->evaluate($this->excludedOperators) ?? [];

        if ($included !== null) {
            $operators = array_values(array_intersect($operators, $included));
        }

        $operators = array_values(array_diff($operators, $excluded));

        return filled($operators) ? $operators : [$this->getDefaultOperatorFallback()];
    }

    public function getDefaultOperator(): string
    {
        $operator = $this->evaluate($this->defaultOperator);
        $operators = $this->getOperators();

        if (filled($operator) && in_array($operator, $operators, true)) {
            return $operator;
        }

        $fallback = $this->getDefaultOperatorFallback();

        return in_array($fallback, $operators, true) ? $fallback : $operators[0];
    }

    /**
     * @return array<string, string>
     */
    public function getOperatorOptions(): array
    {
        return collect($this->getOperators())
            ->mapWithKeys(fn (string $operator): array => [$operator => $this->getOperatorLabel($operator)])
            ->all();
    }

    public function getOperatorLabel(string $operator): string
    {
        return __("filament-dynamic-tables::dynamic-tables.filter_builder.operators.{$operator}");
    }

    public function column(?Column $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): ?Column
    {
        return $this->column;
    }

    public function advancedFilter(?AdvancedFilter $filter): static
    {
        $this->advancedFilter = $filter;

        return $this;
    }

    public function getAdvancedFilter(): ?AdvancedFilter
    {
        return $this->advancedFilter;
    }

    /**
     * @param  array{type: string, relationship: string, column: string | null} | null  $aggregate
     */
    public function aggregate(?array $aggregate): static
    {
        $this->aggregate = $aggregate;

        return $this;
    }

    /**
     * @return array{type: string, relationship: string, column: string | null} | null
     */
    public function getAggregate(): ?array
    {
        return $this->aggregate;
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<SchemaComponent>
     */
    public function getFormSchema(): array
    {
        $operators = $this->getOperatorOptions();

        return [
            Select::make('operator')
                ->hiddenLabel()
                ->options($operators)
                ->default($this->getDefaultOperator())
                ->selectablePlaceholder(false)
                ->native(false)
                ->live()
                ->hidden(count($operators) < 2),
            ...$this->getValueSchema(),
        ];
    }

    /**
     * Makes a value field visible only for the given operators.
     *
     * @param  array<string>  $operators
     */
    protected function visibleFor(array $operators): Closure
    {
        $default = $this->getDefaultOperator();

        return fn (Get $get): bool => in_array($get('operator') ?? $default, $operators, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveOperator(array $data): string
    {
        $operator = $data['operator'] ?? null;

        return (is_string($operator) && in_array($operator, $this->getOperators(), true)) ? $operator : $this->getDefaultOperator();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function isActive(array $data): bool
    {
        $operator = $this->resolveOperator($data);

        if (in_array($operator, $this->getOperatorsWithoutValue(), true)) {
            return true;
        }

        return $this->hasValue($operator, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hasValue(string $operator, array $data): bool
    {
        return filled($data['value'] ?? null);
    }

    /**
     * @return array<string>
     */
    protected function getOperatorsWithoutValue(): array
    {
        return ['is_empty', 'is_not_empty'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(Builder $query, array $data): void
    {
        if (! $this->isActive($data)) {
            return;
        }

        $this->applyOperator($query, $this->resolveOperator($data), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function getIndicatorLabel(array $data): ?string
    {
        if (! $this->isActive($data)) {
            return null;
        }

        $operator = $this->resolveOperator($data);
        $summary = $this->getValueSummary($operator, $data);

        return trim($this->getLabel() . ' ' . str($this->getOperatorLabel($operator))->lower() . (filled($summary) ? " {$summary}" : ''));
    }

    /**
     * Runs $condition against the column, through the relationship when
     * there is one. Negated conditions also match records without a value
     * (or without related records).
     *
     * @param  Closure(Builder, string): mixed  $condition
     */
    protected function where(Builder $query, Closure $condition, bool $negate = false): void
    {
        $relationship = $this->getQueriedRelationship();

        if (filled($relationship)) {
            $method = $negate ? 'whereDoesntHave' : 'whereHas';

            $query->{$method}($relationship, fn (Builder $query) => $condition($query, $query->qualifyColumn($this->getAttribute())));

            return;
        }

        $column = $query->qualifyColumn($this->getAttribute());

        if ($negate) {
            $query->where(fn (Builder $query) => $query
                ->whereNot(fn (Builder $query) => $condition($query, $column))
                ->orWhereNull($column));

            return;
        }

        $condition($query, $column);
    }

    /**
     * A correlated subquery for count / avg / sum / min / max columns, so
     * they can be compared like a regular column.
     */
    /**
     * @return array{0: string, 1: array<mixed>}
     */
    protected function getAggregateSql(Builder $query): array
    {
        /** @var array{type: string, relationship: string, column: string | null} $aggregate */
        $aggregate = $this->aggregate;
        $model = $query->getModel();
        $relation = $model->{$aggregate['relationship']}();
        $related = $relation->getRelated();

        $function = $aggregate['type'] === 'count' ? 'count(*)' : $aggregate['type'] . '(' . $query->getQuery()->getGrammar()->wrap($related->qualifyColumn((string) $aggregate['column'])) . ')';

        $subquery = $relation->getRelationExistenceQuery($related->newQuery(), $query, new Expression($function));

        $sql = $subquery->toBase()->toSql();
        $bindings = $subquery->toBase()->getBindings();

        return [$aggregate['type'] === 'count' ? "coalesce(({$sql}), 0)" : "({$sql})", $bindings];
    }

    /**
     * Case-insensitive LIKE with escaped wildcards.
     */
    protected function whereLike(Builder $query, string $column, string $value, bool $leadingWildcard, bool $trailingWildcard): void
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $term = generate_search_term_expression($value, true, $connection);
        $pattern = ($leadingWildcard ? '%' : '') . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . ($trailingWildcard ? '%' : '');
        $expression = $query->getQuery()->getGrammar()->wrap(generate_search_column_expression($column, true, $connection));

        $query->whereRaw("{$expression} like ? escape '!'", [$pattern]);
    }

    protected function whereEmpty(Builder $query, bool $negate): void
    {
        $isString = $this instanceof TextFilter || $this instanceof SelectFilter;

        $relationship = $this->getQueriedRelationship();
        $attribute = $this->getAttribute();

        $empty = function (Builder $query, string $column) use ($isString): void {
            $query->where(fn (Builder $query) => $query
                ->whereNull($column)
                ->when($isString, fn (Builder $query) => $query->orWhere($column, '')));
        };

        if (filled($relationship)) {
            if ($negate) {
                $query->whereHas($relationship, fn (Builder $query) => $query->whereNot(fn (Builder $query) => $empty($query, $query->qualifyColumn($attribute))));

                return;
            }

            $query->where(fn (Builder $query) => $query
                ->whereDoesntHave($relationship)
                ->orWhereHas($relationship, fn (Builder $query) => $empty($query, $query->qualifyColumn($attribute))));

            return;
        }

        $column = $query->qualifyColumn($attribute);

        if ($negate) {
            $query->whereNot(fn (Builder $query) => $empty($query, $column));

            return;
        }

        $empty($query, $column);
    }

    /**
     * @return array<mixed>
     */
    protected function wrapValues(mixed $value): array
    {
        return array_values(array_filter(Arr::wrap($value), fn (mixed $item): bool => filled($item)));
    }

    protected function getModelInstance(): ?Model
    {
        $model = $this->advancedFilter?->getTable()->getModel();

        return $model ? app($model) : null;
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'filter' => [$this],
            'column' => [$this->column],
            'table' => [$this->advancedFilter?->getTable()],
            'livewire' => [$this->advancedFilter?->getLivewire()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByType(string $parameterType): array
    {
        return match ($parameterType) {
            static::class, self::class => [$this],
            default => parent::resolveDefaultClosureDependencyForEvaluationByType($parameterType),
        };
    }
}
