<?php

namespace KaraOdin\FilamentDynamicTables\Filters\Operators;

final class DateOperator
{
    public const YESTERDAY = 'yesterday';

    public const TODAY = 'today';

    public const TOMORROW = 'tomorrow';

    public const IN_THE_LAST = 'in_the_last';

    public const NOT_IN_THE_LAST = 'not_in_the_last';

    public const IN_THE_NEXT = 'in_the_next';

    public const NOT_IN_THE_NEXT = 'not_in_the_next';

    public const EXACTLY_AGO = 'exactly_ago';

    public const EXACTLY_FROM_NOW = 'exactly_from_now';

    public const THIS = 'this';

    public const LAST = 'last';

    public const NEXT = 'next';

    public const IS_DATE = 'is_date';

    public const BEFORE = 'before';

    public const AFTER = 'after';

    public const BETWEEN = 'between';

    public const NOT_BETWEEN = 'not_between';

    public const IS_EMPTY = 'is_empty';

    public const IS_NOT_EMPTY = 'is_not_empty';

    public const UNIT_DAYS = 'days';

    public const UNIT_WEEKS = 'weeks';

    public const UNIT_MONTHS = 'months';

    public const UNIT_QUARTERS = 'quarters';

    public const UNIT_YEARS = 'years';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::TODAY, self::YESTERDAY, self::TOMORROW, self::IN_THE_LAST, self::NOT_IN_THE_LAST, self::IN_THE_NEXT,
            self::NOT_IN_THE_NEXT, self::EXACTLY_AGO, self::EXACTLY_FROM_NOW, self::THIS, self::LAST, self::NEXT,
            self::IS_DATE, self::BEFORE, self::AFTER, self::BETWEEN, self::NOT_BETWEEN, self::IS_EMPTY, self::IS_NOT_EMPTY,
        ];
    }

    /**
     * @return array<string>
     */
    public static function units(): array
    {
        return [self::UNIT_DAYS, self::UNIT_WEEKS, self::UNIT_MONTHS, self::UNIT_QUARTERS, self::UNIT_YEARS];
    }
}
