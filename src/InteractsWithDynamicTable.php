<?php

namespace KaraOdin\FilamentDynamicTables;

use Filament\Tables\Concerns\InteractsWithTable;

/**
 * Use in standalone Livewire components (Filament Table Builder) instead of
 * Filament's InteractsWithTable:
 *
 *     class ListProducts extends Component implements HasActions, HasSchemas, HasTable
 *     {
 *         use InteractsWithActions;
 *         use InteractsWithSchemas;
 *         use InteractsWithDynamicTable;
 *     }
 */
trait InteractsWithDynamicTable
{
    use DynamicTables, InteractsWithTable {
        DynamicTables::applyFiltersToTableQuery insteadof InteractsWithTable;
        DynamicTables::applySortingToTableQuery insteadof InteractsWithTable;
        DynamicTables::applyGlobalSearchToTableQuery insteadof InteractsWithTable;
        DynamicTables::getTableSearchIndicator insteadof InteractsWithTable;
        DynamicTables::removeTableFilter insteadof InteractsWithTable;
        DynamicTables::updatedTableSearch insteadof InteractsWithTable;
        InteractsWithTable::applyFiltersToTableQuery as protected filamentApplyFiltersToTableQuery;
        InteractsWithTable::applySortingToTableQuery as protected filamentApplySortingToTableQuery;
        InteractsWithTable::applyGlobalSearchToTableQuery as protected filamentApplyGlobalSearchToTableQuery;
        InteractsWithTable::getTableSearchIndicator as public filamentGetTableSearchIndicator;
        InteractsWithTable::removeTableFilter as protected filamentRemoveTableFilter;
        InteractsWithTable::updatedTableSearch as protected filamentUpdatedTableSearch;
    }
}
