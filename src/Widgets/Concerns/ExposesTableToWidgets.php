<?php

namespace KaraOdin\FilamentDynamicTables\Widgets\Concerns;

use Filament\Pages\Concerns\ExposesTableToWidgets as BaseExposesTableToWidgets;

/**
 * Use on a List page instead of Filament's ExposesTableToWidgets so page
 * widgets also see the advanced search, multi-sort and active preset view.
 */
trait ExposesTableToWidgets
{
    use BaseExposesTableToWidgets {
        getWidgetData as protected getFilamentWidgetData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            ...$this->getFilamentWidgetData(),
            'advancedSearch' => $this->advancedSearch ?? [],
            'tableMultiSort' => $this->tableMultiSort ?? [],
            'activeDynamicTableView' => $this->activeDynamicTableView ?? null,
        ];
    }
}
