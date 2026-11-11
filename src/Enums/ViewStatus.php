<?php

namespace KaraOdin\FilamentDynamicTables\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ViewStatus: string implements HasColor, HasLabel
{
    case Approved = 'approved';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __("filament-dynamic-tables::dynamic-tables.status.{$this->value}");
    }

    /**
     * Rejected < pending < approved.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Rejected => 0,
            self::Pending => 1,
            self::Approved => 2,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
        };
    }
}
