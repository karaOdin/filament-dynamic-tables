<?php

namespace KaraOdin\FilamentDynamicTables\Resources\UserViewResource\Pages;

use Filament\Resources\Pages\ListRecords;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;

class ListUserViews extends ListRecords
{
    public static function getResource(): string
    {
        return DynamicTablesPlugin::current()->getUserViewsResource();
    }
}
