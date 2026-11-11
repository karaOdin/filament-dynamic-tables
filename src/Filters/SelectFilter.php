<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\Concerns\HasFilterOptions;
use KaraOdin\FilamentDynamicTables\Filters\Operators\TextOperator;

/**
 * Select column filter: is, is not, is empty, is not empty.
 */
class SelectFilter extends ColumnFilter
{
    use HasFilterOptions;

    public function getAllOperators(): array
    {
        return [TextOperator::IS, TextOperator::IS_NOT, TextOperator::IS_EMPTY, TextOperator::IS_NOT_EMPTY];
    }

    public function getDefaultOperatorFallback(): string
    {
        return TextOperator::IS;
    }

    public function hasOptions(): bool
    {
        return true;
    }

    protected function getValueSchema(): array
    {
        return [
            $this->makeOptionsSelect('value')
                ->visible($this->visibleFor([TextOperator::IS, TextOperator::IS_NOT])),
        ];
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        if (in_array($operator, [TextOperator::IS_EMPTY, TextOperator::IS_NOT_EMPTY], true)) {
            $this->whereEmpty($query, $operator === TextOperator::IS_NOT_EMPTY);

            return;
        }

        $this->applyOptionsCondition($query, $data, $operator === TextOperator::IS_NOT);
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        if (in_array($operator, $this->getOperatorsWithoutValue(), true)) {
            return null;
        }

        return $this->getOptionsSummary($data);
    }
}
