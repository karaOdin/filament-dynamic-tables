@php
    $styles = \KaraOdin\FilamentDynamicTables\DynamicTablesPlugin::current()->getFilterBuilderExpandViewStyles();
@endphp

<div class="fdt-filter-builder-expand" @if (filled($styles)) style="{{ implode('; ', $styles) }}" @endif>
    <x-filament::link
        tag="button"
        type="button"
        size="sm"
        color="gray"
        :icon="\KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias::resolve(\KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias::FILTER_BUILDER_EXPAND)"
        wire:click="expandDynamicTableFilterBuilder"
        wire:loading.attr="disabled"
        wire:target="expandDynamicTableFilterBuilder"
    >
        {{ __('filament-dynamic-tables::dynamic-tables.filter_builder.expand_view') }}
    </x-filament::link>
</div>
