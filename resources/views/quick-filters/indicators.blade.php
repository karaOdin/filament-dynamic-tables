@php
    if (! $livewire->isQuickFiltersActive()) {
        return;
    }
@endphp

@include('filament-dynamic-tables::quick-filters.bar')
