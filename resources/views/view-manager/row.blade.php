@php
    use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias as Icon;

    $isSortable = $isSortable ?? false;
    $color = is_string($item->color) && filled($item->color) ? $item->color : null;
    $user = $livewire->getDynamicTablesUser();
    $canManage = (bool) $user && (! $item->isDefaultView());
    $hasManagedDefaults = $config->isManagedDefaultViewsEnabled() && (bool) $user;
    $clickToApply = $config->isViewManagerClickToApply();
    $jsKey = \Illuminate\Support\Js::from($item->key);
    $closeAfterApply = ($isDropdown ?? false) && $config->doesViewManagerCloseOnApply() ? '; close()' : '';

    $showGlobal = $item->isGlobalFavorite;
    $showPublic = $item->isPublic && ((! $item->isGlobalFavorite) || $config->showsViewManagerPublicIndicatorWhenGlobal());

    $types = array_values(array_filter([
        $item->isDefaultView() ? ['icon' => Icon::DEFAULT_VIEW_INDICATOR, 'label' => __('filament-dynamic-tables::dynamic-tables.view_manager.tags.default_view')] : null,
        $item->isPreset() ? ['icon' => Icon::PRESET_LOCKED_INDICATOR, 'label' => __('filament-dynamic-tables::dynamic-tables.view_manager.tags.preset')] : null,
        ($item->isUserView() && $item->isOwn) ? ['icon' => Icon::USER_OWNER_INDICATOR, 'label' => __('filament-dynamic-tables::dynamic-tables.view_manager.tags.mine')] : null,
        $showGlobal ? ['icon' => Icon::GLOBAL_VIEW_INDICATOR, 'label' => __('filament-dynamic-tables::dynamic-tables.view_manager.tags.global')] : null,
        $showPublic ? ['icon' => Icon::PUBLIC_VIEW_INDICATOR, 'label' => __('filament-dynamic-tables::dynamic-tables.view_manager.tags.public')] : null,
    ]));

    $canFavorite = $canManage && $item->isManageable && $config->isFavoritesBarEnabled();
    $canSetDefault = $canManage && $hasManagedDefaults;
    $canEdit = $item->isUserView() && $item->canEdit;
    $canDelete = $item->isUserView() && $item->canDelete;
    $hasActions = $config->hasViewManagerApplyButton() || $canFavorite || $canSetDefault || $canEdit || $canDelete;
@endphp

<div
    @if ($isSortable) x-sortable-item="{{ $item->key }}" @endif
    x-show="matches({{ \Illuminate\Support\Js::from($item->getSearchText()) }})"
    wire:key="{{ $livewire->getId() }}.dynamic-tables.view-manager.row.{{ $section ?? 'list' }}.{{ $item->key }}"
    @class([
        'fdt-vm-row',
        'fdt-vm-row-active' => $item->isActive,
        'fdt-vm-row-clickable' => $clickToApply,
    ])
    @if ($color)
        style="--fdt-view-color-500: var(--{{ $color }}-500); --fdt-view-color-400: var(--{{ $color }}-400);"
    @endif
>
    @if ($isSortable)
        <button
            type="button"
            class="fdt-handle"
            x-sortable-handle
            x-show="reordering === {{ \Illuminate\Support\Js::from($section ?? null) }}"
            x-cloak
            title="{{ __('filament-dynamic-tables::dynamic-tables.view_manager.reorder') }}"
        >
            <x-filament::icon :icon="Icon::resolve(Icon::DRAG_HANDLE)" class="fdt-handle-icon" />
        </button>
    @endif

    <{{ $clickToApply ? 'button' : 'div' }}
        @if ($clickToApply)
            type="button"
            x-on:click="apply({{ $jsKey }}){{ $closeAfterApply }}"
            title="{{ __('filament-dynamic-tables::dynamic-tables.view_manager.actions.apply') }}"
        @endif
        class="fdt-vm-apply"
    >
        @if ($config->hasViewIcon())
            <span @class(['fdt-vm-icon', 'fdt-vm-icon-colored' => $color])>
                <x-filament::icon :icon="$item->icon ?? $config->getFallbackViewIcon() ?? 'heroicon-o-queue-list'" />
            </span>
        @endif

        <span class="fdt-vm-text">
            <span class="fdt-vm-name">
                <span class="fdt-vm-label">{{ $item->label }}</span>

                @if ($item->isActive && $config->hasViewManagerActiveViewIndicator())
                    @if ($config->hasViewManagerActiveViewBadge())
                        <span class="fdt-vm-tag fdt-vm-tag-success">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.tags.active') }}</span>
                    @else
                        <span class="fdt-vm-active-dot" title="{{ __('filament-dynamic-tables::dynamic-tables.view_manager.tags.active') }}"></span>
                    @endif
                @endif

                @if ($item->isModified)
                    <span class="fdt-vm-tag fdt-vm-tag-warning">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.tags.modified') }}</span>
                @endif
            </span>

            <span class="fdt-vm-meta">
                @if ($config->hasViewManagerViewTypeBadges())
                    @foreach ($types as $type)
                        <span class="fdt-vm-tag">{{ $type['label'] }}</span>
                    @endforeach
                @endif

                @if ($item->isUserView() && (! $item->isOwn) && $item->ownerName)
                    <span>{{ __('filament-dynamic-tables::dynamic-tables.view_manager.by', ['name' => $item->ownerName]) }}</span>
                @endif

                @if ($item->isPending())
                    <span class="fdt-vm-tag fdt-vm-tag-warning">{{ __('filament-dynamic-tables::dynamic-tables.status.pending') }}</span>
                @elseif ($item->isRejected())
                    <span class="fdt-vm-tag fdt-vm-tag-danger">{{ __('filament-dynamic-tables::dynamic-tables.status.rejected') }}</span>
                @endif

                @if ($item->isDefault)
                    <span class="fdt-vm-tag fdt-vm-tag-success">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.tags.default') }}</span>
                @endif
            </span>
        </span>
    </{{ $clickToApply ? 'button' : 'div' }}>

    @if ($config->hasViewManagerViewTypeIcons() && filled($types))
        <span class="fdt-vm-types">
            @foreach ($types as $type)
                <x-filament::icon
                    :icon="Icon::resolve($type['icon'])"
                    class="fdt-vm-type-icon"
                    :x-tooltip="'{ content: ' . \Illuminate\Support\Js::from($type['label']) . ', theme: $store.theme }'"
                />
            @endforeach
        </span>
    @endif

    @if ($hasActions)
        <div class="fdt-vm-actions">
            <x-filament::dropdown placement="bottom-end" width="xs">
                <x-slot name="trigger">
                    <x-filament::icon-button
                        :icon="Icon::resolve(Icon::VIEW_MANAGER_ACTION_GROUP)"
                        color="gray"
                        size="sm"
                        :label="__('filament-dynamic-tables::dynamic-tables.view_manager.actions.more')"
                    />
                </x-slot>

                <x-filament::dropdown.list>
                    @if ($config->hasViewManagerApplyButton())
                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve(Icon::APPLY_VIEW)"
                            :x-on:click="'apply(' . $jsKey . ')' . $closeAfterApply"
                        >
                            {{ __('filament-dynamic-tables::dynamic-tables.view_manager.actions.apply') }}
                        </x-filament::dropdown.list.item>
                    @endif

                    @if ($canSetDefault)
                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve($item->isDefault ? Icon::REMOVE_DEFAULT_VIEW : Icon::SET_DEFAULT_VIEW)"
                            wire:click="toggleDynamicTableDefaultView({{ $jsKey }})"
                        >
                            {{ $item->isDefault ? __('filament-dynamic-tables::dynamic-tables.view_manager.actions.unset_default') : __('filament-dynamic-tables::dynamic-tables.view_manager.actions.set_default') }}
                        </x-filament::dropdown.list.item>
                    @endif

                    @if ($canFavorite)
                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve($item->isFavorite ? Icon::REMOVE_FROM_FAVORITES : Icon::ADD_TO_FAVORITES)"
                            wire:click="toggleDynamicTableViewFavorite({{ $jsKey }})"
                        >
                            {{ $item->isFavorite ? __('filament-dynamic-tables::dynamic-tables.view_manager.actions.unfavorite') : __('filament-dynamic-tables::dynamic-tables.view_manager.actions.favorite') }}
                        </x-filament::dropdown.list.item>
                    @endif

                    @if ($canEdit)
                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve(Icon::EDIT_VIEW)"
                            wire:click="mountAction('dynamicTablesEditView', {{ \Illuminate\Support\Js::from(['view' => $item->key]) }})"
                        >
                            {{ __('filament-dynamic-tables::dynamic-tables.view_manager.actions.edit') }}
                        </x-filament::dropdown.list.item>

                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve(Icon::REPLACE_VIEW)"
                            wire:click="mountAction('dynamicTablesReplaceView', {{ \Illuminate\Support\Js::from(['view' => $item->key]) }})"
                        >
                            {{ __('filament-dynamic-tables::dynamic-tables.view_manager.actions.replace') }}
                        </x-filament::dropdown.list.item>

                        @if ($config->isPublicViewsEnabled() && $item->isOwn)
                            <x-filament::dropdown.list.item
                                :icon="Icon::resolve($item->isPublic ? Icon::USER_OWNER_INDICATOR : Icon::PUBLIC_VIEW_INDICATOR)"
                                wire:click="toggleDynamicTableUserViewPublic({{ $jsKey }})"
                            >
                                {{ $item->isPublic ? __('filament-dynamic-tables::dynamic-tables.view_manager.actions.make_private') : __('filament-dynamic-tables::dynamic-tables.view_manager.actions.make_public') }}
                            </x-filament::dropdown.list.item>
                        @endif
                    @endif

                    @if ($canDelete)
                        <x-filament::dropdown.list.item
                            :icon="Icon::resolve(Icon::DELETE_VIEW)"
                            color="danger"
                            wire:click="mountAction('dynamicTablesDeleteView', {{ \Illuminate\Support\Js::from(['view' => $item->key]) }})"
                        >
                            {{ __('filament-dynamic-tables::dynamic-tables.view_manager.actions.delete') }}
                        </x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>
    @endif
</div>
