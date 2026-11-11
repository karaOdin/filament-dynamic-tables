@php
    if (! $config->isLoadingOverlayEnabled()) {
        return;
    }

    $targets = implode(',', [
        'applyDynamicTableView', 'resetDynamicTableView', 'tableFilters', 'tableDeferredFilters', 'applyTableFilters',
        'removeTableFilter', 'removeTableFilters', 'resetTableFiltersForm', 'tableSearch', 'tableColumnSearches',
        'sortTable', 'tableSort', 'tableGrouping', 'tableRecordsPerPage', 'gotoPage', 'nextPage', 'previousPage',
        'addTableMultiSort', 'removeTableMultiSort', 'toggleTableMultiSortDirection', 'reorderTableMultiSort',
        'resetTableMultiSort', 'setTableSearchConstraint', 'toggleTableSearchColumn', 'resetTableSearchColumns',
    ]);
@endphp

<div
    class="fdt-loading-anchor"
    x-data="{
        top: 0,
        measure() {
            const container = this.$el.closest('.fi-ta-ctn')

            if (! container) {
                return
            }

            container.classList.add('fdt-has-loading-overlay')
            this.top = this.$el.getBoundingClientRect().top - container.getBoundingClientRect().top
        },
    }"
    x-init="$nextTick(() => measure())"
    x-on:resize.window.debounce.200ms="measure()"
    x-on:mousedown.window="measure()"
    x-on:keydown.window="measure()"
>
<div
    class="fdt-loading-overlay"
    wire:loading.delay.default.flex
    wire:target="{{ $targets }}"
    x-bind:style="{ top: top + 'px' }"
    aria-hidden="true"
>
    <div class="fdt-loading-skeleton">
        @foreach (range(1, $config->getLoadingOverlaySkeletonRows()) as $row)
            <div class="fdt-loading-row">
                <span class="fdt-loading-cell" style="width: {{ [38, 22, 30, 18, 26][$row % 5] }}%"></span>
                <span class="fdt-loading-cell" style="width: {{ [20, 32, 16, 28, 24][$row % 5] }}%"></span>
                <span class="fdt-loading-cell" style="width: {{ [14, 18, 22, 12, 20][$row % 5] }}%"></span>
            </div>
        @endforeach
    </div>
</div>
</div>
