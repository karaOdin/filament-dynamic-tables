@php
    use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;

    if (! $config->isFavoritesBarEnabled()) {
        return;
    }

    $theme = $config->getFavoritesBarTheme();
    $defaultItem = $livewire->getDefaultDynamicTableViewItem();
    $favorites = $livewire->getDynamicTableFavoriteViewItems();
    $items = collect([$defaultItem])->filter()->merge($favorites)->values();

    $quickSaveAction = $config->isQuickSaveInFavoritesBar()
        ? $livewire->getAction('dynamicTablesSaveView', isMounting: false)
        : null;
    $viewManagerAction = $config->isViewManagerInFavoritesBar()
        ? $livewire->getAction('dynamicTablesViewManager', isMounting: false)
        : null;

    $quickSaveAction = $quickSaveAction?->isVisible() ? $quickSaveAction : null;
    $viewManagerAction = $viewManagerAction?->isVisible() ? $viewManagerAction : null;

    $isHidden = ($favorites->isEmpty() && $config->isFavoritesBarHiddenWhenEmpty())
        || ($items->isEmpty() && (! $quickSaveAction) && (! $viewManagerAction));

    $activeItem = $livewire->getActiveDynamicTableViewItem();

    $startActions = array_filter([
        ($quickSaveAction && ($config->getQuickSaveFavoritesBarPosition() === 'start')) ? 'quick-save' : null,
        ($viewManagerAction && ($config->getViewManagerFavoritesBarPosition() === 'start')) ? 'view-manager' : null,
    ]);
    $endActions = array_filter([
        ($quickSaveAction && ($config->getQuickSaveFavoritesBarPosition() === 'end')) ? 'quick-save' : null,
        ($viewManagerAction && ($config->getViewManagerFavoritesBarPosition() === 'end')) ? 'view-manager' : null,
    ]);
    $lockIcon = $config->getPresetViewLockIcon();
@endphp

@if (! $isHidden)
    <div
        wire:key="{{ $livewire->getId() }}.dynamic-tables.favorites-bar"
        @class([
            'fdt-favorites-bar',
            'fdt-theme-' . $theme->value,
            'fdt-size-' . $config->getFavoritesBarSize(),
            'fdt-icon-after' => $config->getFavoritesBarIconPosition() === 'after',
            'fdt-favorites-bar-standalone' => $isStandalone ?? false,
        ])
    >
        @if (filled($startActions))
            <div class="fdt-favorites-bar-actions fdt-favorites-bar-actions-start">
                @foreach ($startActions as $actionName)
                    @if ($actionName === 'quick-save')
                        {{ $quickSaveAction }}
                    @else
                        @include('filament-dynamic-tables::view-manager.trigger', ['action' => $viewManagerAction, 'location' => 'bar'])
                    @endif
                @endforeach
            </div>
        @endif

        @if ($theme === FavoritesBarTheme::Filament)
            <x-filament::tabs class="fdt-favorites-bar-views" :contained="false">
                @foreach ($items as $item)
                    <x-filament::tabs.item
                        :active="$item->isActive"
                        :badge="$config->hasFavoritesBarBadges() ? $item->badge : null"
                        :badge-color="$item->badgeColor"
                        :icon="$config->hasFavoritesBarIcons() ? ($item->isActive ? ($item->activeIcon ?? $item->icon) : $item->icon) : null"
                        :icon-position="$config->getFavoritesBarIconPosition()"
                        :wire:click="'applyDynamicTableView(' . \Illuminate\Support\Js::from($item->key) . ')'"
                        :wire:key="$livewire->getId() . '.dynamic-tables.favorite.' . $item->key"
                        :x-tooltip="$item->tooltip ? '{ content: ' . \Illuminate\Support\Js::from($item->tooltip) . ', theme: $store.theme }' : null"
                    >
                        {{ $item->label }}

                        @if ($item->isModified)
                            <span class="fdt-modified-dot" title="{{ __('filament-dynamic-tables::dynamic-tables.favorites_bar.modified') }}"></span>
                        @endif
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @else
            <nav class="fdt-favorites-bar-views" aria-label="{{ __('filament-dynamic-tables::dynamic-tables.favorites_bar.label') }}">
                @foreach ($items as $item)
                    @if ($config->hasFavoritesBarDivider() && ($loop->index > 0) && $item->isUserView() && (! $items[$loop->index - 1]->isUserView()))
                        <span class="fdt-divider" aria-hidden="true"></span>
                    @endif

                    @include('filament-dynamic-tables::partials.view-item', ['item' => $item, 'lockIcon' => $lockIcon])
                @endforeach

                @if ($items->count() <= ($defaultItem ? 1 : 0))
                    <span class="fdt-favorites-bar-empty">
                        {{ __('filament-dynamic-tables::dynamic-tables.favorites_bar.empty') }}
                    </span>
                @endif
            </nav>
        @endif

        @if (filled($endActions) || ($activeItem?->isModified && $activeItem->isUserView() && $activeItem->canEdit))
            <div class="fdt-favorites-bar-actions">
                @if ($activeItem?->isModified && $activeItem->isUserView() && $activeItem->canEdit)
                    <button
                        type="button"
                        class="fdt-save-changes"
                        wire:click="mountAction('dynamicTablesReplaceView', {{ \Illuminate\Support\Js::from(['view' => $activeItem->key]) }})"
                    >
                        {{ __('filament-dynamic-tables::dynamic-tables.favorites_bar.save_changes') }}
                    </button>
                @endif

                @foreach ($endActions as $actionName)
                    @if ($actionName === 'quick-save')
                        {{ $quickSaveAction }}
                    @else
                        @include('filament-dynamic-tables::view-manager.trigger', ['action' => $viewManagerAction, 'location' => 'bar'])
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif
