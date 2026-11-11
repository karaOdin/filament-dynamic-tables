<?php

namespace KaraOdin\FilamentDynamicTables\Livewire;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use KaraOdin\FilamentDynamicTables\DynamicTables;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource;

/**
 * The User Views resource table for apps using the standalone Table
 * Builder. Register a route to it:
 *
 *     Route::get('/user-views', ListUserViews::class)->middleware(['auth']);
 */
class ListUserViews extends Page
{
    use DynamicTables;

    public function table(Table $table): Table
    {
        $table = UserViewResource::table($table)
            ->query(UserViewResource::getEloquentQuery());

        $actions = collect($table->getRecordActions())
            ->reject(fn ($action): bool => ($action instanceof EditAction) || ($action instanceof DeleteAction))
            ->values()
            ->all();

        return $table->recordActions([
            ...$actions,
            EditAction::make()->schema(fn (Schema $schema): Schema => UserViewResource::form($schema)),
            DeleteAction::make(),
        ]);
    }

    public function render(): View
    {
        return view('filament-dynamic-tables::livewire.list-user-views');
    }
}
