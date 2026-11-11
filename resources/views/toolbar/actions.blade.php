@php
    $hasFavoritesBar = $config->isFavoritesBarEnabled();
    $viewManagerHook = $config->getViewManagerTablePosition();
    $isHookPosition = str_starts_with($position, 'hook:');

    $wantsQuickSave = (! $hasFavoritesBar) || $config->isQuickSaveInTable();
    $wantsViewManager = (! $hasFavoritesBar) || $config->isViewManagerInTable();

    if ($isHookPosition) {
        // A render hook chosen with viewManagerInTable(position: ...).
        if ((! $wantsViewManager) || blank($viewManagerHook) || ($position !== "hook:{$viewManagerHook}")) {
            return;
        }

        $wantsQuickSave = false;
        $hasMultiSort = false;
    } else {
        $hasContainer = $livewire->hasDynamicTablesToolbarContainer();

        if (($position === 'container') !== $hasContainer) {
            return;
        }

        if (filled($viewManagerHook)) {
            $wantsViewManager = false;
        }

        $hasMultiSort = $config->isMultiSortEnabled()
            && (! $livewire->isTableReordering())
            && (filled($livewire->getTableMultiSortItems()) || filled($livewire->getTableMultiSortAvailableColumns()));
    }

    $quickSaveAction = $wantsQuickSave ? $livewire->getAction('dynamicTablesSaveView', isMounting: false) : null;
    $viewManagerAction = $wantsViewManager ? $livewire->getAction('dynamicTablesViewManager', isMounting: false) : null;

    $quickSaveAction = $quickSaveAction?->isVisible() ? $quickSaveAction : null;
    $viewManagerAction = $viewManagerAction?->isVisible() ? $viewManagerAction : null;
@endphp

@if ($hasMultiSort || $quickSaveAction || $viewManagerAction)
    <div class="fdt-toolbar-actions">
        @if ($hasMultiSort)
            @include('filament-dynamic-tables::multi-sort.dropdown')
        @endif

        @if ($viewManagerAction)
            @include('filament-dynamic-tables::view-manager.trigger', ['action' => $viewManagerAction, 'location' => 'toolbar'])
        @endif

        {{ $quickSaveAction }}
    </div>
@endif
