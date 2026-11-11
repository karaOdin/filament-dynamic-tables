<?php

namespace KaraOdin\FilamentDynamicTables\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;

/**
 * Base class for standalone Table Builder components, so the DynamicTables
 * trait can be used without trait conflicts:
 *
 *     class ListProducts extends Page
 *     {
 *         use DynamicTables;
 *     }
 */
abstract class Page extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
}
