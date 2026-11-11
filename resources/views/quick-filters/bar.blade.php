@php
    $table = $livewire->getTable();
    $searchIndicators = $livewire->getQuickFilterSearchIndicators();
    $items = $livewire->getQuickFilterItems();
    $hasRemovable = collect($searchIndicators)->contains(fn ($indicator) => $indicator->isRemovable())
        || collect($items)->contains(fn (array $item): bool => collect($item['indicators'])->contains(fn ($indicator) => $indicator->isRemovable()));
    $isDeferred = $table->hasDeferredFilters();
    $canPin = $config->isQuickFilterPinningEnabled() && (bool) $livewire->getDynamicTablesUser();
@endphp

<div
    class="fi-ta-filter-indicators fdt-quick-filters"
    wire:key="{{ $livewire->getId() }}.dynamic-tables.quick-filters"
>
    <div>
        <span class="fi-ta-filter-indicators-label">
            {{ __('filament-tables::table.filters.indicator') }}
        </span>

        <div class="fi-ta-filter-indicators-badges-ctn fdt-quick-filters-chips" role="list">
            @foreach ($searchIndicators as $indicator)
                <x-filament::badge :color="$indicator->getColor()" role="listitem">
                    {{ $indicator->getLabel() }}

                    @if ($indicator->isRemovable())
                        <x-slot
                            name="deleteButton"
                            :label="__('filament-tables::table.filters.actions.remove.label')"
                            :wire:click="$indicator->getRemoveLivewireClickHandler()"
                            wire:target="removeTableFilter"
                        ></x-slot>
                    @endif
                </x-filament::badge>
            @endforeach

            @foreach ($items as $item)
                @include('filament-dynamic-tables::quick-filters.chip')
            @endforeach
        </div>
    </div>

    @if ($hasRemovable)
        {{ $table->getFiltersRemoveAllAction() }}
    @endif
</div>
