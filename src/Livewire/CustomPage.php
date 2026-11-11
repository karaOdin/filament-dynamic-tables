<?php

namespace KaraOdin\FilamentDynamicTables\Livewire;

use Filament\Pages\Page as FilamentPage;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

/**
 * Base class for custom panel pages with a table:
 *
 *     class Reports extends CustomPage
 *     {
 *         use DynamicTables;
 *     }
 */
abstract class CustomPage extends FilamentPage implements HasTable
{
    use InteractsWithTable;
}
