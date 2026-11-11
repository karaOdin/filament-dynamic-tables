<?php

namespace KaraOdin\FilamentDynamicTables\Filters\Operators;

final class BooleanOperator
{
    public const IS_TRUE = 'is_true';

    public const IS_FALSE = 'is_false';

    public const IS_EMPTY = 'is_empty';

    public const IS_NOT_EMPTY = 'is_not_empty';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [self::IS_TRUE, self::IS_FALSE, self::IS_EMPTY, self::IS_NOT_EMPTY];
    }
}
