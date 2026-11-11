@php
    $filterSchema = $item['canEdit'] ? $livewire->getQuickFilterSchema($item['name']) : null;
    $summary = $item['summary'] ?? '';
    $firstColor = $item['color'] ?? 'primary';
    $removeHandler = $item['removeHandler'] ?? null;
    $isRemovable = filled($removeHandler);
    $canPin = $canPin && ($item['canPin'] ?? true);
@endphp

<div
    role="listitem"
    wire:key="{{ $livewire->getId() }}.dynamic-tables.quick-filter.{{ $item['key'] ?? $item['name'] }}"
    @class([
        'fdt-quick-filter',
        'fdt-quick-filter-active' => $item['isActive'],
        'fdt-quick-filter-pinned' => $item['isPinned'],
    ])
    style="--fdt-chip-color-50: var(--{{ is_string($firstColor) ? $firstColor : 'primary' }}-50); --fdt-chip-color-400: var(--{{ is_string($firstColor) ? $firstColor : 'primary' }}-400); --fdt-chip-color-600: var(--{{ is_string($firstColor) ? $firstColor : 'primary' }}-600);"
>
    @if ($filterSchema)
        <x-filament::dropdown
            :menu="false"
            placement="bottom-start"
            shift
            width="xs"
            class="fdt-quick-filter-dropdown"
        >
            <x-slot name="trigger">
                <button type="button" class="fdt-quick-filter-trigger">
                    @if ($item['isPinned'] && (! $item['isActive']))
                        <x-filament::icon icon="heroicon-m-plus-circle" class="fdt-quick-filter-icon" />
                    @endif

                    <span class="fdt-quick-filter-name">{{ $item['isActive'] ? $summary : $item['label'] }}</span>

                    <x-filament::icon icon="heroicon-m-chevron-down" class="fdt-quick-filter-chevron" />
                </button>
            </x-slot>

            <div class="fdt-panel fdt-quick-filter-panel">
                <div class="fdt-panel-header">
                    <h3 class="fdt-panel-heading">{{ $item['label'] }}</h3>

                    @if ($canPin)
                        <button
                            type="button"
                            class="fdt-pin"
                            wire:click="toggleQuickFilterPin({{ \Illuminate\Support\Js::from($item['name']) }})"
                            title="{{ $item['isPinned'] ? __('filament-dynamic-tables::dynamic-tables.quick_filters.unpin') : __('filament-dynamic-tables::dynamic-tables.quick_filters.pin') }}"
                        >
                            <x-filament::icon
                                :icon="$item['isPinned'] ? 'heroicon-s-star' : 'heroicon-o-star'"
                                @class(['fdt-pin-icon', 'fdt-pin-icon-active' => $item['isPinned']])
                            />
                        </button>
                    @endif
                </div>

                <div class="fdt-quick-filter-form">
                    {{ $filterSchema }}
                </div>

                <div class="fdt-panel-footer">
                    @if ($isDeferred)
                        <x-filament::button size="sm" wire:click="applyTableFilters">
                            {{ __('filament-tables::table.filters.actions.apply.label') }}
                        </x-filament::button>
                    @endif

                    @if ($item['isActive'] && $isRemovable)
                        <x-filament::link
                            tag="button"
                            color="danger"
                            size="sm"
                            :wire:click="$removeHandler"
                        >
                            {{ __('filament-dynamic-tables::dynamic-tables.quick_filters.clear') }}
                        </x-filament::link>
                    @endif
                </div>
            </div>
        </x-filament::dropdown>
    @else
        <span class="fdt-quick-filter-trigger fdt-quick-filter-static">
            <span class="fdt-quick-filter-name">{{ $summary ?: $item['label'] }}</span>
        </span>
    @endif

    @if ($item['isActive'] && $isRemovable)
        <button
            type="button"
            class="fdt-quick-filter-remove"
            wire:click="{{ $removeHandler }}"
            wire:target="removeTableFilter"
            aria-label="{{ __('filament-tables::table.filters.actions.remove.label') }}"
        >
            <x-filament::icon icon="heroicon-m-x-mark" class="fdt-quick-filter-remove-icon" />
        </button>
    @endif
</div>
