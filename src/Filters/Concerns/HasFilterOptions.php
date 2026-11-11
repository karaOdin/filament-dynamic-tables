<?php

namespace KaraOdin\FilamentDynamicTables\Filters\Concerns;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Options for "is" / "is not" selects: a static list, an enum, or the
 * records of a relationship.
 */
trait HasFilterOptions
{
    /** @var array<string | int, string> | class-string<BackedEnum> | Closure | null */
    protected array | string | Closure | null $options = null;

    protected string | Closure | null $optionsRelationship = null;

    protected string | Closure | null $optionsTitleAttribute = null;

    protected ?Closure $modifyOptionsQueryUsing = null;

    protected bool | Closure $isMultiple = true;

    protected bool | Closure $isPreloaded = false;

    protected bool | Closure $isSearchable = false;

    /**
     * @param  array<string | int, string> | class-string<BackedEnum> | Closure | null  $options
     */
    public function options(array | string | Closure | null $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function relationship(string | Closure | null $name, string | Closure | null $titleAttribute = null, ?Closure $modifyQueryUsing = null): static
    {
        $this->optionsRelationship = $name;
        $this->optionsTitleAttribute = $titleAttribute;
        $this->modifyOptionsQueryUsing = $modifyQueryUsing;

        return $this;
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function preload(bool | Closure $condition = true): static
    {
        $this->isPreloaded = $condition;

        return $this;
    }

    public function searchable(bool | Closure $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    public function getOptionsRelationshipName(): ?string
    {
        return $this->evaluate($this->optionsRelationship);
    }

    public function hasOptions(): bool
    {
        return filled($this->getOptionsRelationshipName()) || ($this->evaluate($this->options) !== null);
    }

    /**
     * @return array<string | int, string>
     */
    public function getOptions(?string $search = null): array
    {
        if (filled($this->getOptionsRelationshipName())) {
            return $this->getRelationshipOptions($search);
        }

        $options = $this->evaluate($this->options);

        if (is_string($options) && enum_exists($options)) {
            $options = collect($options::cases())
                ->mapWithKeys(fn (BackedEnum $case): array => [$case->value => $case instanceof HasLabel ? (string) $case->getLabel() : (string) str($case->name)->headline()])
                ->all();
        }

        if ($options instanceof Collection) {
            $options = $options->all();
        }

        $options = array_map(fn (mixed $label): string => $label instanceof HasLabel ? (string) $label->getLabel() : (is_array($label) ? '' : strip_tags((string) $label)), (array) $options);

        if (filled($search)) {
            $options = array_filter($options, fn (string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($search)));
        }

        return $options;
    }

    /**
     * @return array<string | int, string>
     */
    protected function getRelationshipOptions(?string $search = null, ?array $keys = null): array
    {
        $relation = $this->getOptionsRelation();

        if (! $relation) {
            return [];
        }

        $related = $relation->getRelated();
        $titleAttribute = $this->evaluate($this->optionsTitleAttribute) ?? $this->getAttribute();
        $query = $related->newQuery();

        if ($this->modifyOptionsQueryUsing) {
            $query = $this->evaluate($this->modifyOptionsQueryUsing, ['query' => $query]) ?? $query;
        }

        if ($keys !== null) {
            $query->whereKey($keys);
        } elseif (filled($search)) {
            $query->where($related->qualifyColumn($titleAttribute), 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%');
        }

        if (($keys === null) && (! $this->evaluate($this->isPreloaded)) && blank($search) && $this->evaluate($this->isSearchable)) {
            return [];
        }

        return $query
            ->orderBy($related->qualifyColumn($titleAttribute))
            ->limit($keys === null ? 100 : count($keys))
            ->pluck($related->qualifyColumn($titleAttribute), $related->getQualifiedKeyName())
            ->map(fn (mixed $label): string => (string) $label)
            ->all();
    }

    protected function getOptionsRelation(): ?Relation
    {
        $name = $this->getOptionsRelationshipName();
        $model = $this->getModelInstance();

        if (blank($name) || (! $model)) {
            return null;
        }

        $relation = null;

        foreach (explode('.', $name) as $segment) {
            if (! method_exists($model, $segment)) {
                return null;
            }

            $relation = $model->{$segment}();

            if (! $relation instanceof Relation) {
                return null;
            }

            $model = $relation->getRelated();
        }

        return $relation;
    }

    protected function makeOptionsSelect(string $name = 'value'): Select
    {
        $select = Select::make($name)
            ->hiddenLabel()
            ->multiple($this->isMultiple())
            ->native(false)
            ->placeholder(__('filament-dynamic-tables::dynamic-tables.filter_builder.form.select_placeholder'));

        if (filled($this->getOptionsRelationshipName()) && $this->evaluate($this->isSearchable) && (! $this->evaluate($this->isPreloaded))) {
            return $select
                ->searchable()
                ->getSearchResultsUsing(fn (?string $search): array => $this->getRelationshipOptions($search ?? ''))
                ->getOptionLabelUsing(fn (mixed $value): ?string => $this->getRelationshipOptions(keys: [$value])[$value] ?? null)
                ->getOptionLabelsUsing(fn (array $values): array => $this->getRelationshipOptions(keys: $values));
        }

        return $select
            ->options(fn (): array => $this->getOptions())
            ->searchable((bool) $this->evaluate($this->isSearchable) || (count($this->getOptions()) > 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyOptionsCondition(Builder $query, array $data, bool $negate): void
    {
        $values = array_values(array_filter(Arr::wrap($data['value'] ?? null), fn (mixed $value): bool => filled($value)));

        if (blank($values)) {
            return;
        }

        if (filled($relationship = $this->getOptionsRelationshipName())) {
            $method = $negate ? 'whereDoesntHave' : 'whereHas';

            $query->{$method}($relationship, fn (Builder $query) => $query->whereKey($values));

            return;
        }

        $this->where(
            $query,
            fn (Builder $query, string $column) => $query->whereIn($column, $values),
            $negate,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function getOptionsSummary(array $data): ?string
    {
        $values = array_values(array_filter(Arr::wrap($data['value'] ?? null), fn (mixed $value): bool => filled($value)));

        if (blank($values)) {
            return null;
        }

        $labels = filled($this->getOptionsRelationshipName())
            ? $this->getRelationshipOptions(keys: $values)
            : $this->getOptions();

        $labels = collect($values)->map(fn (mixed $value): string => (string) ($labels[$value] ?? $value));

        if ($labels->count() > 3) {
            return $labels->take(3)->implode(', ') . ' ' . __('filament-dynamic-tables::dynamic-tables.filter_builder.indicator.more', ['count' => $labels->count() - 3]);
        }

        return $labels->implode(__('filament-dynamic-tables::dynamic-tables.filter_builder.indicator.or_separator'));
    }

    abstract protected function getModelInstance(): ?Model;
}
