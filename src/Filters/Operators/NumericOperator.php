<?php

namespace KaraOdin\FilamentDynamicTables\Filters\Operators;

final class NumericOperator
{
    public const EQUAL_TO = 'equal_to';

    public const NOT_EQUAL_TO = 'not_equal_to';

    public const GREATER_THAN = 'greater_than';

    public const GREATER_THAN_OR_EQUAL_TO = 'greater_than_or_equal_to';

    public const LESS_THAN = 'less_than';

    public const LESS_THAN_OR_EQUAL_TO = 'less_than_or_equal_to';

    public const BETWEEN = 'between';

    public const NOT_BETWEEN = 'not_between';

    public const POSITIVE = 'positive';

    public const NEGATIVE = 'negative';

    public const IS_EMPTY = 'is_empty';

    public const IS_NOT_EMPTY = 'is_not_empty';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::EQUAL_TO, self::NOT_EQUAL_TO, self::GREATER_THAN, self::GREATER_THAN_OR_EQUAL_TO, self::LESS_THAN,
            self::LESS_THAN_OR_EQUAL_TO, self::BETWEEN, self::NOT_BETWEEN, self::POSITIVE, self::NEGATIVE,
            self::IS_EMPTY, self::IS_NOT_EMPTY,
        ];
    }
}
