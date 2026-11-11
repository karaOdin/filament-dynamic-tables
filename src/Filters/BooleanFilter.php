<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\Operators\BooleanOperator;

/**
 * Yes / no column filter, used for toggle, checkbox and boolean icon columns.
 */
class BooleanFilter extends ColumnFilter
{
    public function getAllOperators(): array
    {
        return BooleanOperator::all();
    }

    public function getDefaultOperatorFallback(): string
    {
        return BooleanOperator::IS_TRUE;
    }

    protected function getValueSchema(): array
    {
        return [];
    }

    public function getFormSchema(): array
    {
        $schema = parent::getFormSchema();

        // The operator is the value here, so always show it.
        $schema[0]->hidden(false)->columnSpanFull();

        return $schema;
    }

    protected function getOperatorsWithoutValue(): array
    {
        return BooleanOperator::all();
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        match ($operator) {
            BooleanOperator::IS_TRUE => $this->where($query, fn (Builder $query, string $column) => $query->where($column, true)),
            BooleanOperator::IS_FALSE => $this->where($query, fn (Builder $query, string $column) => $query->where(fn (Builder $query) => $query->where($column, false)->orWhereNull($column))),
            BooleanOperator::IS_EMPTY => $this->whereEmpty($query, false),
            BooleanOperator::IS_NOT_EMPTY => $this->whereEmpty($query, true),
            default => null,
        };
    }

    public function getIndicatorLabel(array $data): ?string
    {
        return $this->getLabel() . ': ' . str($this->getOperatorLabel($this->resolveOperator($data)))->lower();
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        return null;
    }
}
