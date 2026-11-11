<?php

namespace KaraOdin\FilamentDynamicTables\Livewire;

use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

/**
 * Base class for custom resource pages with a table:
 *
 *     class ArchivedOrders extends PanelPage
 *     {
 *         use DynamicTables;
 *
 *         protected static string $resource = OrderResource::class;
 *     }
 */
abstract class PanelPage extends ResourcePage implements HasTable
{
    use InteractsWithTable;
}
