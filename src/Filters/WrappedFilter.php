<?php

namespace KaraOdin\FilamentDynamicTables\Filters;

use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter as FilamentSelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * Lets a regular Filament filter (Filter, SelectFilter, TernaryFilter ...)
 * be used inside the Advanced Filter Builder, so it can be repeated and
 * combined in "or" groups.
 */
class WrappedFilter extends ColumnFilter
{
    protected BaseFilter $filter;

    public static function wrap(BaseFilter $filter): static
    {
        $static = static::make($filter->getName());
        $static->filter = $filter;

        return $static;
    }

    public function getFilamentFilter(): BaseFilter
    {
        return $this->filter;
    }

    public function getLabel(): string
    {
        if (filled($this->evaluate($this->label))) {
            return parent::getLabel();
        }

        return strip_tags((string) $this->filter->getLabel());
    }

    public function getAllOperators(): array
    {
        return ['custom'];
    }

    public function getDefaultOperatorFallback(): string
    {
        return 'custom';
    }

    public function getFormSchema(): array
    {
        return $this->filter->getFormSchema();
    }

    protected function getValueSchema(): array
    {
        return [];
    }

    public function isActive(array $data): bool
    {
        if ($this->filter instanceof TernaryFilter) {
            return filled($data['value'] ?? null) || (($data['value'] ?? null) === false) || (($data['value'] ?? null) === '0');
        }

        if ($this->filter instanceof FilamentSelectFilter) {
            return filled($data['values'] ?? null) || filled($data['value'] ?? null);
        }

        if (($this->filter instanceof Filter) && (! $this->filter->hasForm())) {
            return (bool) ($data['isActive'] ?? false);
        }

        return collect(Arr::flatten($data))->contains(fn (mixed $value): bool => filled($value) && ($value !== false));
    }

    public function apply(Builder $query, array $data): void
    {
        if (! $this->isActive($data)) {
            return;
        }

        $this->filter->applyToBaseQuery($query, $data);
        $this->filter->apply($query, $data);
    }

    protected function applyOperator(Builder $query, string $operator, array $data): void
    {
        $this->apply($query, $data);
    }

    public function getIndicatorLabel(array $data): ?string
    {
        if (! $this->isActive($data)) {
            return null;
        }

        $indicateUsing = (fn () => $this->indicateUsing)->call($this->filter);

        if ($indicateUsing) {
            $indicators = $this->filter->evaluate($indicateUsing, ['data' => $data, 'state' => $data]);

            if (filled($indicators)) {
                return collect(Arr::wrap($indicators))
                    ->map(fn (mixed $indicator): string => strip_tags((string) ($indicator instanceof Indicator ? $indicator->getLabel() : $indicator)))
                    ->implode(', ');
            }
        }

        if ($this->filter instanceof TernaryFilter) {
            $value = $data['value'] ?? null;
            $isTrue = in_array($value, [true, 1, '1', 'true'], true);

            return $this->getLabel() . ': ' . ($isTrue ? $this->filter->getTrueLabel() : $this->filter->getFalseLabel());
        }

        if ($this->filter instanceof FilamentSelectFilter) {
            $values = Arr::wrap($data['values'] ?? $data['value'] ?? []);
            $options = $this->filter->getOptions();
            $labels = collect($values)->map(fn (mixed $value): string => strip_tags((string) (Arr::get($options, (string) $value) ?? $value)));

            return $this->getLabel() . ': ' . $labels->implode(', ');
        }

        return $this->getLabel();
    }

    protected function getValueSummary(string $operator, array $data): ?string
    {
        return null;
    }
}
