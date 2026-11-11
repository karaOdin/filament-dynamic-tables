@php
    /** @var \Filament\Actions\Action $action */
    $isSlideOver = $config->isViewManagerSlideOver();
@endphp

@if ($isSlideOver)
    {{ $action }}
@else
    <x-filament::dropdown
        placement="bottom-end"
        :width="'sm'"
        :max-height="'32rem'"
        shift

        class="fdt-view-manager-dropdown"
        wire:key="{{ $livewire->getId() }}.dynamic-tables.view-manager-dropdown.{{ $location ?? 'bar' }}"
    >
        <x-slot name="trigger">
            {{ $action->livewireClickHandlerEnabled(false) }}
        </x-slot>

        <div class="fdt-panel fdt-view-manager-panel">
            <div class="fdt-panel-header">
                <h3 class="fdt-panel-heading">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.heading') }}</h3>
            </div>

            @include('filament-dynamic-tables::view-manager.index', ['isDropdown' => true])
        </div>
    </x-filament::dropdown>
@endif
