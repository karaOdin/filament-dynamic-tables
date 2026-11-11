@php
    $icon = $config->hasFavoritesBarIcons() ? ($item->isActive ? ($item->activeIcon ?? $item->icon) : $item->icon) : null;
    $color = is_string($item->color) && filled($item->color) ? $item->color : null;
    $target = 'applyDynamicTableView(' . \Illuminate\Support\Js::from($item->key) . ')';
@endphp

<button
    type="button"
    wire:click="{{ $target }}"
    wire:key="{{ $livewire->getId() }}.dynamic-tables.favorite.{{ $item->key }}"
    wire:loading.attr="disabled"
    wire:target="applyDynamicTableView"
    aria-pressed="{{ $item->isActive ? 'true' : 'false' }}"
    @if ($item->tooltip)
        x-tooltip="{ content: {{ \Illuminate\Support\Js::from($item->tooltip) }}, theme: $store.theme }"
    @endif
    @class([
        'fdt-view',
        'fdt-view-active' => $item->isActive,
        'fdt-view-colored' => $color,
        'fdt-view-pending' => $item->isPending(),
    ])
    @if ($color)
        style="--fdt-view-color-50: var(--{{ $color }}-50); --fdt-view-color-400: var(--{{ $color }}-400); --fdt-view-color-500: var(--{{ $color }}-500); --fdt-view-color-600: var(--{{ $color }}-600);"
    @endif
>
    @if ($config->hasFavoritesBarLoadingIndicator())
        <x-filament::loading-indicator
            class="fdt-view-loading"
            wire:loading.delay.default=""
            :wire:target="$target"
        />
    @endif

    @if ($icon)
        <x-filament::icon
            :icon="$icon"
            class="fdt-view-icon"
            :wire:loading.remove.delay.default="$config->hasFavoritesBarLoadingIndicator() ? '' : null"
            :wire:target="$config->hasFavoritesBarLoadingIndicator() ? $target : null"
        />
    @endif

    <span class="fdt-view-label">{{ $item->label }}</span>

    @if (($lockIcon ?? null) && $item->isPreset())
        <x-filament::icon :icon="$lockIcon" class="fdt-view-lock" />
    @endif

    @if ($item->isModified)
        <span class="fdt-modified-dot" title="{{ __('filament-dynamic-tables::dynamic-tables.favorites_bar.modified') }}"></span>
    @endif

    @if ($item->isPending())
        <span class="fdt-view-status">{{ __('filament-dynamic-tables::dynamic-tables.status.pending') }}</span>
    @endif

    @if ($config->hasFavoritesBarBadges() && filled($item->badge))
        <x-filament::badge :color="$item->badgeColor ?? 'gray'" size="sm" class="fdt-view-badge">
            {{ $item->badge }}
        </x-filament::badge>
    @endif
</button>
