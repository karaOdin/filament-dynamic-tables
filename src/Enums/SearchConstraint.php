<?php

namespace KaraOdin\FilamentDynamicTables\Enums;

use Filament\Support\Contracts\HasLabel;

enum SearchConstraint: string implements HasLabel
{
    case Contains = 'contains';
    case DoesNotContain = 'does_not_contain';
    case StartsWith = 'starts_with';
    case DoesNotStartWith = 'does_not_start_with';
    case EndsWith = 'ends_with';
    case DoesNotEndWith = 'does_not_end_with';
    case Equals = 'equals';
    case DoesNotEqual = 'does_not_equal';

    public function getLabel(): string
    {
        return __("filament-dynamic-tables::dynamic-tables.advanced_search.constraints.{$this->value}");
    }

    public function isNegated(): bool
    {
        return in_array($this, [self::DoesNotContain, self::DoesNotStartWith, self::DoesNotEndWith, self::DoesNotEqual], true);
    }

    public function hasLeadingWildcard(): bool
    {
        return in_array($this, [self::Contains, self::DoesNotContain, self::EndsWith, self::DoesNotEndWith], true);
    }

    public function hasTrailingWildcard(): bool
    {
        return in_array($this, [self::Contains, self::DoesNotContain, self::StartsWith, self::DoesNotStartWith], true);
    }

    public function negate(): self
    {
        return match ($this) {
            self::Contains => self::DoesNotContain,
            self::DoesNotContain => self::Contains,
            self::StartsWith => self::DoesNotStartWith,
            self::DoesNotStartWith => self::StartsWith,
            self::EndsWith => self::DoesNotEndWith,
            self::DoesNotEndWith => self::EndsWith,
            self::Equals => self::DoesNotEqual,
            self::DoesNotEqual => self::Equals,
        };
    }

    /**
     * The operator and value for a custom ->searchable(query: ...) closure:
     *
     *     [$operator, $value] = $constraint?->getOperatorAndValue($search) ?? ['like', "%{$search}%"];
     *
     * @return array{0: string, 1: string}
     */
    public function getOperatorAndValue(string $search): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return match ($this) {
            self::Contains => ['like', "%{$escaped}%"],
            self::DoesNotContain => ['not like', "%{$escaped}%"],
            self::StartsWith => ['like', "{$escaped}%"],
            self::DoesNotStartWith => ['not like', "{$escaped}%"],
            self::EndsWith => ['like', "%{$escaped}"],
            self::DoesNotEndWith => ['not like', "%{$escaped}"],
            self::Equals => ['=', $search],
            self::DoesNotEqual => ['!=', $search],
        };
    }

    /**
     * Keyboard shortcut typed in front of a term.
     */
    public function syntax(): string
    {
        return match ($this) {
            self::Contains => '',
            self::DoesNotContain => '-',
            self::StartsWith => '^',
            self::DoesNotStartWith => '-^',
            self::EndsWith => '$',
            self::DoesNotEndWith => '-$',
            self::Equals => '=',
            self::DoesNotEqual => '-=',
        };
    }
}
