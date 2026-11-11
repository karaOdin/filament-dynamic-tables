<?php

namespace KaraOdin\FilamentDynamicTables\Filters\Operators;

final class TextOperator
{
    public const IS = 'is';

    public const IS_NOT = 'is_not';

    public const CONTAINS = 'contains';

    public const DOES_NOT_CONTAIN = 'does_not_contain';

    public const STARTS_WITH = 'starts_with';

    public const DOES_NOT_START_WITH = 'does_not_start_with';

    public const ENDS_WITH = 'ends_with';

    public const DOES_NOT_END_WITH = 'does_not_end_with';

    public const IS_EMPTY = 'is_empty';

    public const IS_NOT_EMPTY = 'is_not_empty';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::IS, self::IS_NOT, self::CONTAINS, self::DOES_NOT_CONTAIN, self::STARTS_WITH,
            self::DOES_NOT_START_WITH, self::ENDS_WITH, self::DOES_NOT_END_WITH, self::IS_EMPTY, self::IS_NOT_EMPTY,
        ];
    }

    /**
     * @return array<string>
     */
    public static function negated(): array
    {
        return [self::IS_NOT, self::DOES_NOT_CONTAIN, self::DOES_NOT_START_WITH, self::DOES_NOT_END_WITH];
    }
}
