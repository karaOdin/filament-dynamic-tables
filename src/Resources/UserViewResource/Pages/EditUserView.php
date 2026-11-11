<?php

namespace KaraOdin\FilamentDynamicTables\Resources\UserViewResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;

class EditUserView extends EditRecord
{
    public static function getResource(): string
    {
        return DynamicTablesPlugin::current()->getUserViewsResource();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
