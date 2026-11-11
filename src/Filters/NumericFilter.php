<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\Operators\NumericOperator;

/**
 * Number column filter, also used for relationship counts and aggregates.
 */
class NumericFilter extends ColumnFilter
{
    protected const COMPARISONS = [
        NumericOperator::EQUAL_TO => '=',
        NumericOperator::NOT_EQUAL_TO => '!=',
        NumericOperator::GREATER_THAN => '>',
        NumericOperator::GREATER_THAN_OR_EQUAL_TO => '>=',
        NumericOperator::LESS_THAN => '<',
        NumericOperator::LESS_THAN_OR_EQUAL_TO => '<=',
    ];

    protected bool $isInteger = false;

    public function integer(bool $condition = true): static
    {
        $this->isInteger = $condition;

        return $this;
    }

    public function getAllOperators(): array
    {
        return NumericOperator::all();
    }

    public function getDefaultOperatorFallback(): string
    {
        return NumericOperator::EQUAL_TO;
    }

    protected function getOperatorsWithoutValue(): array
    {
        return [NumericOperator::POSITIVE, NumericOperator::NEGATIVE, NumericOperator::IS_EMPTY, NumericOperator::IS_NOT_EMPTY];
    }

    protected function getValueSchema(): array
    {
        $input = fn (string $name, ?string $placeholder = null): TextInput => TextInput::make($name)
            ->hiddenLabel()
            ->numeric()
            ->step($this->isInteger ? 1 : 'any')
            ->placeholder($placeholder ?? __('filament-dynamic-tables::dynamic-tables.filter_builder.form.number_placeholder'));

        return [
            $input('value')->visible($this->visibleFor(array_keys(static::COMPARISONS))),
            Grid::make(2)
                ->schema([
                    $input('value_start', __('filament-dynamic-tables::dynamic-tables.filter_builder.form.from')),
                    $input('value_end', __('filament-dynamic-tables::dynamic-tables.filter_builder.form.to')),
                ])
                ->visible($this->visibleFor([NumericOperator::BETWEEN, NumericOperator::NOT_BETWEEN])),
        ];
    }

    protected function hasValue(string $operator, array $data): bool
    {
        if (in_array($operator, [NumericOperator::BETWEEN, NumericOperator::NOT_BETWEEN], true)) {
            return is_numeric($data['value_start'] ?? null) || is_numeric($data['value_end'] ?? null);
        }

        return is_numeric($data['value'] ?? null);
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        if ($this->getAggregate() !== null) {
            $this->applyToAggregate($query, $operator, $data);

            return;
        }

        if (in_array($operator, [NumericOperator::IS_EMPTY, NumericOperator::IS_NOT_EMPTY], true)) {
            $this->whereEmpty($query, $operator === NumericOperator::IS_NOT_EMPTY);

            return;
        }

        $negate = in_array($operator, [NumericOperator::NOT_EQUAL_TO, NumericOperator::NOT_BETWEEN], true);

        $this->where($query, function (Builder $query, string $column) use ($operator, $data): void {
            match ($operator) {
                NumericOperator::POSITIVE => $query->where($column, '>', 0),
                NumericOperator::NEGATIVE => $query->where($column, '<', 0),
                NumericOperator::NOT_EQUAL_TO => $query->where($column, '=', $data['value'] + 0),
                NumericOperator::BETWEEN, NumericOperator::NOT_BETWEEN => $this->applyBetween($query, $column, $data),
                default => $query->where($column, static::COMPARISONS[$operator], $data['value'] + 0),
            };
        }, $negate);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyBetween(Builder $query, string $column, array $data): void
    {
        if (is_numeric($data['value_start'] ?? null)) {
            $query->where($column, '>=', $data['value_start'] + 0);
        }

        if (is_numeric($data['value_end'] ?? null)) {
            $query->where($column, '<=', $data['value_end'] + 0);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyToAggregate(Builder $query, string $operator, array $data): void
    {
        [$sql, $bindings] = $this->getAggregateSql($query);

        match ($operator) {
            NumericOperator::POSITIVE => $query->whereRaw("{$sql} > 0", $bindings),
            NumericOperator::NEGATIVE => $query->whereRaw("{$sql} < 0", $bindings),
            NumericOperator::IS_EMPTY => $query->whereRaw("{$sql} is null", $bindings),
            NumericOperator::IS_NOT_EMPTY => $query->whereRaw("{$sql} is not null", $bindings),
            NumericOperator::BETWEEN => $query->where(function (Builder $query) use ($sql, $bindings, $data): void {
                if (is_numeric($data['value_start'] ?? null)) {
                    $query->whereRaw("{$sql} >= ?", [...$bindings, $data['value_start'] + 0]);
                }

                if (is_numeric($data['value_end'] ?? null)) {
                    $query->whereRaw("{$sql} <= ?", [...$bindings, $data['value_end'] + 0]);
                }
            }),
            NumericOperator::NOT_BETWEEN => $query->where(function (Builder $query) use ($sql, $bindings, $data): void {
                if (is_numeric($data['value_start'] ?? null)) {
                    $query->orWhereRaw("{$sql} < ?", [...$bindings, $data['value_start'] + 0]);
                }

                if (is_numeric($data['value_end'] ?? null)) {
                    $query->orWhereRaw("{$sql} > ?", [...$bindings, $data['value_end'] + 0]);
                }
            }),
            default => $query->whereRaw("{$sql} " . static::COMPARISONS[$operator] . ' ?', [...$bindings, $data['value'] + 0]),
        };
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        if (in_array($operator, $this->getOperatorsWithoutValue(), true)) {
            return null;
        }

        if (in_array($operator, [NumericOperator::BETWEEN, NumericOperator::NOT_BETWEEN], true)) {
            return ($data['value_start'] ?? '…') . ' ' . __('filament-dynamic-tables::dynamic-tables.filter_builder.indicator.and') . ' ' . ($data['value_end'] ?? '…');
        }

        return (string) $data['value'];
    }
}
