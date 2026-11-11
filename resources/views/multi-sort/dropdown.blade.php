@php
    $sorts = $livewire->getTableMultiSortItems();
    $availableColumns = $livewire->getTableMultiSortAvailableColumns();
    $canAdd = $livewire->canAddTableMultiSort();
@endphp

<x-filament::dropdown
    :menu="false"
    placement="bottom-end"
    shift
    :flip="false"
    width="xs"
    :wire:key="$livewire->getId() . '.dynamic-tables.multi-sort'"
    class="fdt-multi-sort-dropdown"
>
    <x-slot name="trigger">
        {{ $livewire->getAction('dynamicTablesMultiSort', isMounting: false) }}
    </x-slot>

    <div class="fdt-panel">
        <div class="fdt-panel-header">
            <h3 class="fdt-panel-heading">
                {{ __('filament-dynamic-tables::dynamic-tables.multi_sort.heading') }}
            </h3>

            @if (filled($sorts))
                <x-filament::link
                    tag="button"
                    color="danger"
                    size="sm"
                    wire:click="resetTableMultiSort"
                >
                    {{ __('filament-dynamic-tables::dynamic-tables.multi_sort.reset') }}
                </x-filament::link>
            @endif
        </div>

        @if (blank($sorts))
            <p class="fdt-panel-empty">
                {{ __('filament-dynamic-tables::dynamic-tables.multi_sort.empty') }}
            </p>
        @else
            <ul
                class="fdt-sort-list"
                x-sortable
                x-on:end.stop="$wire.reorderTableMultiSort($event.target.sortable.toArray())"
                data-sortable-animation-duration="200"
            >
                @foreach ($sorts as $index => $sort)
                    <li
                        class="fdt-sort-row"
                        x-sortable-item="{{ $sort['column'] }}"
                        wire:key="{{ $livewire->getId() }}.dynamic-tables.multi-sort.{{ $sort['column'] }}"
                    >
                        <button
                            type="button"
                            class="fdt-handle"
                            x-sortable-handle
                            title="{{ __('filament-dynamic-tables::dynamic-tables.multi_sort.reorder') }}"
                        >
                            <x-filament::icon icon="heroicon-m-bars-2" class="fdt-handle-icon" />
                        </button>

                        <span class="fdt-sort-priority">{{ $index + 1 }}</span>

                        <span class="fdt-sort-label">{{ $sort['label'] }}</span>

                        <button
                            type="button"
                            class="fdt-sort-direction"
                            wire:click="toggleTableMultiSortDirection({{ $index }})"
                            title="{{ __('filament-dynamic-tables::dynamic-tables.multi_sort.toggle_direction') }}"
                        >
                            <x-filament::icon
                                :icon="$sort['direction'] === 'asc' ? 'heroicon-m-bars-arrow-up' : 'heroicon-m-bars-arrow-down'"
                                class="fdt-sort-direction-icon"
                            />
                            {{ __('filament-dynamic-tables::dynamic-tables.multi_sort.' . $sort['direction']) }}
                        </button>

                        <x-filament::icon-button
                            icon="heroicon-m-x-mark"
                            color="gray"
                            size="sm"
                            :label="__('filament-dynamic-tables::dynamic-tables.multi_sort.remove')"
                            wire:click="removeTableMultiSort({{ $index }})"
                        />
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canAdd)
            <div class="fdt-sort-add">
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        x-on:change="if ($event.target.value) { $wire.addTableMultiSort($event.target.value); $event.target.value = '' }"
                    >
                        <option value="">{{ __('filament-dynamic-tables::dynamic-tables.multi_sort.add') }}</option>

                        @foreach ($availableColumns as $column => $label)
                            <option value="{{ $column }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @elseif (filled($availableColumns))
            <p class="fdt-panel-hint">
                {{ __('filament-dynamic-tables::dynamic-tables.multi_sort.max_reached', ['max' => $config->getMultiSortMaxColumns()]) }}
            </p>
        @endif
    </div>
</x-filament::dropdown>
