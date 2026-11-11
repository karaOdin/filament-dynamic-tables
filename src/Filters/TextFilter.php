<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Filters\Concerns\HasFilterOptions;
use KaraOdin\FilamentDynamicTables\Filters\Operators\TextOperator;

/**
 * Text column filter: is, is not, contains, starts with, ends with (and
 * their opposites), is empty, is not empty. With ->options() or
 * ->relationship(), "is" and "is not" become a multi-select.
 */
class TextFilter extends ColumnFilter
{
    use HasFilterOptions;

    public function getAllOperators(): array
    {
        return TextOperator::all();
    }

    public function getDefaultOperatorFallback(): string
    {
        return $this->hasOptions() ? TextOperator::IS : TextOperator::CONTAINS;
    }

    protected function getValueSchema(): array
    {
        $textOperators = array_values(array_diff(TextOperator::all(), $this->getOperatorsWithoutValue()));

        if (! $this->hasOptions()) {
            return [
                TextInput::make('value')
                    ->hiddenLabel()
                    ->placeholder(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.value_placeholder'))
                    ->visible($this->visibleFor($textOperators)),
            ];
        }

        $optionOperators = [TextOperator::IS, TextOperator::IS_NOT];

        return [
            $this->makeOptionsSelect('value')
                ->visible($this->visibleFor($optionOperators)),
            TextInput::make('text')
                ->hiddenLabel()
                ->placeholder(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.value_placeholder'))
                ->visible($this->visibleFor(array_values(array_diff($textOperators, $optionOperators)))),
        ];
    }

    protected function hasValue(string $operator, array $data): bool
    {
        if ($this->hasOptions() && (! in_array($operator, [TextOperator::IS, TextOperator::IS_NOT], true))) {
            return filled($data['text'] ?? ($data['value'] ?? null)) && is_scalar($data['text'] ?? ($data['value'] ?? null));
        }

        return filled($data['value'] ?? null);
    }

    protected function getTextValue(array $data): string
    {
        $value = $this->hasOptions() ? ($data['text'] ?? $data['value'] ?? '') : ($data['value'] ?? '');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        $negate = in_array($operator, TextOperator::negated(), true);

        if (in_array($operator, [TextOperator::IS_EMPTY, TextOperator::IS_NOT_EMPTY], true)) {
            $this->whereEmpty($query, $operator === TextOperator::IS_NOT_EMPTY);

            return;
        }

        if ($this->hasOptions() && in_array($operator, [TextOperator::IS, TextOperator::IS_NOT], true)) {
            $this->applyOptionsCondition($query, $data, $negate);

            return;
        }

        $value = $this->getTextValue($data);

        [$leading, $trailing] = match ($operator) {
            TextOperator::CONTAINS, TextOperator::DOES_NOT_CONTAIN => [true, true],
            TextOperator::STARTS_WITH, TextOperator::DOES_NOT_START_WITH => [false, true],
            TextOperator::ENDS_WITH, TextOperator::DOES_NOT_END_WITH => [true, false],
            default => [false, false],
        };

        $this->where(
            $query,
            fn (Builder $query, string $column) => $this->whereLike($query, $column, $value, $leading, $trailing),
            $negate,
        );
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        if (in_array($operator, $this->getOperatorsWithoutValue(), true)) {
            return null;
        }

        if ($this->hasOptions() && in_array($operator, [TextOperator::IS, TextOperator::IS_NOT], true)) {
            return $this->getOptionsSummary($data);
        }

        return '"' . $this->getTextValue($data) . '"';
    }
}
