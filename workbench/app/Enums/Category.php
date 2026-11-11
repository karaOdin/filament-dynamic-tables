<?php

namespace Workbench\App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Category: string implements HasColor, HasLabel
{
    case Electronics = 'electronics';
    case Books = 'books';
    case Clothing = 'clothing';
    case Garden = 'garden';
    case Toys = 'toys';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Electronics => 'info',
            self::Books => 'warning',
            self::Clothing => 'success',
            self::Garden => 'primary',
            self::Toys => 'danger',
        };
    }
}
