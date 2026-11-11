<?php

namespace KaraOdin\FilamentDynamicTables\Widgets\Concerns;

use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\Concerns\InteractsWithPageTable as BaseInteractsWithPageTable;
use Livewire\Attributes\Reactive;

/**
 * Use on page widgets instead of Filament's InteractsWithPageTable, together
 * with ExposesTableToWidgets on the page.
 */
trait InteractsWithPageTable
{
    use BaseInteractsWithPageTable {
        getTablePageInstance as protected getFilamentTablePageInstance;
    }

    /**
     * @var array<int, array<string, mixed>>
     */
    #[Reactive]
    public array $advancedSearch = [];

    /**
     * @var array<int, string>
     */
    #[Reactive]
    public array $tableMultiSort = [];

    #[Reactive]
    public ?string $activeDynamicTableView = null;

    protected function getTablePageInstance(): HasTable
    {
        if (isset($this->cachedTablePageInstance)) {
            return $this->cachedTablePageInstance;
        }

        $page = $this->getFilamentTablePageInstance();

        foreach (['advancedSearch', 'tableMultiSort', 'activeDynamicTableView'] as $property) {
            if (property_exists($page, $property)) {
                $page->{$property} = $this->{$property};
            }
        }

        return $page;
    }
}
