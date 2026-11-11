<?php

namespace KaraOdin\FilamentDynamicTables\Support;

use Closure;
use Filament\Tables\Filters\BaseFilter;
use WeakMap;

/**
 * Quick filter options added to every Filament filter as macros:
 *
 *     SelectFilter::make('status')->favorite()->limitIndicatorLabels(3)
 *     Filter::make('created_at')->form([...])->multipleIndicators()
 */
class FilterOptions
{
    /** @var WeakMap<BaseFilter, array<string, mixed>> | null */
    protected static ?WeakMap $options = null;

    public static function register(): void
    {
        $set = static fn (BaseFilter $filter, string $key, mixed $value): BaseFilter => static::set($filter, $key, $value);

        if (! BaseFilter::hasMacro('favorite')) {
            BaseFilter::macro('favorite', function (bool | Closure $condition = true) use ($set): BaseFilter {
                /** @var BaseFilter $this */
                return $set($this, 'favorite', $condition);
            });
        }

        if (! BaseFilter::hasMacro('limitIndicatorLabels')) {
            BaseFilter::macro('limitIndicatorLabels', function (?int $limit) use ($set): BaseFilter {
                /** @var BaseFilter $this */
                return $set($this, 'limit_indicator_labels', $limit);
            });
        }

        if (! BaseFilter::hasMacro('multipleIndicators')) {
            BaseFilter::macro('multipleIndicators', function (bool | Closure $condition = true) use ($set): BaseFilter {
                /** @var BaseFilter $this */
                return $set($this, 'multiple_indicators', $condition);
            });
        }
    }

    public static function set(BaseFilter $filter, string $key, mixed $value): BaseFilter
    {
        static::$options ??= new WeakMap;

        static::$options[$filter] = [...(static::$options[$filter] ?? []), $key => $value];

        return $filter;
    }

    public static function get(BaseFilter $filter, string $key, mixed $default = null): mixed
    {
        $value = (static::$options ?? new WeakMap)[$filter][$key] ?? $default;

        return $value instanceof Closure ? $filter->evaluate($value) : $value;
    }

    public static function isFavorite(BaseFilter $filter): bool
    {
        return (bool) static::get($filter, 'favorite', false);
    }

    public static function getIndicatorLabelLimit(BaseFilter $filter): ?int
    {
        $limit = static::get($filter, 'limit_indicator_labels');

        return $limit === null ? null : max(1, (int) $limit);
    }

    /**
     * null: let the indicators decide (one chip per indicator with a
     * remove field, otherwise one combined chip).
     */
    public static function hasMultipleIndicators(BaseFilter $filter): ?bool
    {
        $value = static::get($filter, 'multiple_indicators');

        return $value === null ? null : (bool) $value;
    }
}
