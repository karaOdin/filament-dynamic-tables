@php
    // Filter indicators render through the FILTER_INDICATORS hook. This view
    // covers the case where no filter is active but some are pinned.
    if (! $livewire->isQuickFiltersActive()) {
        return;
    }

    if (filled($livewire->getTable()->getFilterIndicators())) {
        return;
    }

    if (blank($livewire->getPinnedQuickFilters())) {
        return;
    }
@endphp

@include('filament-dynamic-tables::quick-filters.bar')
