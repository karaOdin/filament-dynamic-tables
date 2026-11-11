@php
    use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias as Icon;

    $config = $livewire->getDynamicTablesConfig();
    $isDropdown = $isDropdown ?? false;
    $items = $livewire->getDynamicTableViewItems();
    $favorites = $livewire->getDynamicTableFavoriteViewItems();
    $defaultItem = $livewire->getDefaultDynamicTableViewItem();
    $user = $livewire->getDynamicTablesUser();

    $sortItems = fn ($collection) => $collection
        ->sortBy([
            fn ($a, $b) => ($a->sortOrder ?? PHP_INT_MAX) <=> ($b->sortOrder ?? PHP_INT_MAX),
            fn ($a, $b) => $a->naturalOrder <=> $b->naturalOrder,
        ])
        ->values();

    $userViews = $sortItems($items->filter(fn ($item) => $item->isUserView() && $item->isOwn && (! $item->isFavorite)));
    $presetViews = $config->showsPresetViewsInViewManager()
        ? $sortItems($items->filter(fn ($item) => $item->isPreset() && (! $item->isFavorite)))
        : collect();
    $publicViews = $config->showsPublicViewsInViewManager()
        ? $sortItems($items->filter(fn ($item) => $item->isUserView() && (! $item->isOwn) && $item->isPublic && (! $item->isGlobalFavorite) && (! $item->isFavorite)))
        : collect();
    $globalViews = $config->showsGlobalViewsInViewManager()
        ? $sortItems($items->filter(fn ($item) => $item->isUserView() && (! $item->isOwn) && $item->isGlobalFavorite && (! $item->isFavorite)))
        : collect();

    $sections = [
        'favorites' => ['items' => $favorites, 'reorderable' => true],
        'user_views' => ['items' => $userViews, 'reorderable' => true],
        'preset_views' => ['items' => $presetViews, 'reorderable' => $config->arePresetViewsManageable()],
        'public_views' => ['items' => $publicViews, 'reorderable' => false],
        'global_views' => ['items' => $globalViews, 'reorderable' => false],
    ];

    $canReorder = $config->isViewManagerReorderable() && (bool) $user;
    $isSearchable = $config->isViewManagerSearchable();
    $closesOnApply = $config->doesViewManagerCloseOnApply();
    $saveAction = ($isDropdown && $config->hasViewManagerSaveView()) ? $livewire->getAction('dynamicTablesSaveView', isMounting: false) : null;
    $saveAction = $saveAction?->isVisible() ? $saveAction : null;
@endphp

<div
    class="fdt-view-manager"
    x-data="{
        search: '',
        reordering: null,
        matches(text) {
            return this.search.trim() === '' || text.includes(this.search.trim().toLowerCase())
        },
        apply(key) {
            this.$wire.applyDynamicTableView(key, {{ $isDropdown ? 'false' : 'true' }})
        },
    }"
>
    @if ($isSearchable)
        <x-filament::input.wrapper :prefix-icon="Icon::resolve(Icon::FILTER_BUILDER_SEARCH)" class="fdt-vm-search">
            <x-filament::input
                type="search"
                x-model="search"
                :placeholder="__('filament-dynamic-tables::dynamic-tables.view_manager.search')"
            />
        </x-filament::input.wrapper>
    @endif

    @if ($defaultItem && (! $config->hasViewManagerResetView()))
        <div class="fdt-vm-section" x-show="search.trim() === ''">
            @include('filament-dynamic-tables::view-manager.row', ['item' => $defaultItem, 'section' => 'default'])
        </div>
    @endif

    @php
        $hasAny = collect($sections)->contains(fn ($definition) => $definition['items']->isNotEmpty());
    @endphp

    @foreach ($sections as $section => $definition)
        @continue($definition['items']->isEmpty())

        @php
            $sectionItems = $definition['items'];
            $isSectionReorderable = $canReorder && $definition['reorderable'] && ($sectionItems->filter(fn ($item) => $item->isManageable)->count() > 1);
        @endphp

        <section
            class="fdt-vm-section"
            wire:key="{{ $livewire->getId() }}.dynamic-tables.view-manager.{{ $section }}"
            x-show="search.trim() === '' || $el.querySelector('.fdt-vm-row:not([style*=\'display: none\'])')"
        >
            <h3 class="fdt-vm-heading">
                @if ($section === 'favorites')
                    <x-filament::icon :icon="Icon::resolve(Icon::REMOVE_FROM_FAVORITES)" class="fdt-vm-heading-icon" />
                @endif

                <span class="fdt-vm-heading-label">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.sections.' . $section) }}</span>
                <span class="fdt-vm-count">{{ $sectionItems->count() }}</span>

                @if ($isSectionReorderable)
                    <button
                        type="button"
                        class="fdt-vm-reorder"
                        x-on:click="reordering = (reordering === {{ \Illuminate\Support\Js::from($section) }}) ? null : {{ \Illuminate\Support\Js::from($section) }}"
                        x-bind:title="reordering === {{ \Illuminate\Support\Js::from($section) }} ? {{ \Illuminate\Support\Js::from(__('filament-dynamic-tables::dynamic-tables.view_manager.reorder_done')) }} : {{ \Illuminate\Support\Js::from(__('filament-dynamic-tables::dynamic-tables.view_manager.reorder')) }}"
                        x-show="search.trim() === ''"
                    >
                        <x-filament::icon :icon="Icon::resolve(Icon::REORDER_VIEWS)" class="fdt-vm-reorder-icon" x-show="reordering !== {{ \Illuminate\Support\Js::from($section) }}" />
                        <x-filament::icon :icon="Icon::resolve(Icon::REORDER_VIEWS_CONFIRM)" class="fdt-vm-reorder-icon" x-show="reordering === {{ \Illuminate\Support\Js::from($section) }}" x-cloak />
                    </button>
                @endif
            </h3>

            <div
                class="fdt-vm-list"
                @if ($isSectionReorderable)
                    x-sortable
                    x-on:end.stop="$wire.reorderDynamicTableFavoriteViews($event.target.sortable.toArray())"
                    data-sortable-animation-duration="200"
                    x-bind:class="{ 'fdt-vm-list-reordering': reordering === {{ \Illuminate\Support\Js::from($section) }} }"
                @endif
            >
                @foreach ($sectionItems as $item)
                    @include('filament-dynamic-tables::view-manager.row', [
                        'item' => $item,
                        'section' => $section,
                        'isSortable' => $isSectionReorderable && $item->isManageable,
                    ])
                @endforeach
            </div>
        </section>
    @endforeach

    @if ((! $hasAny) && $user && $config->isQuickSaveEnabled())
        <p class="fdt-panel-hint">{{ __('filament-dynamic-tables::dynamic-tables.view_manager.no_user_views') }}</p>
    @endif

    @if ($saveAction || ($defaultItem && $config->hasViewManagerResetView()))
        <div class="fdt-vm-footer">
            @if ($defaultItem && $config->hasViewManagerResetView())
                <x-filament::link
                    tag="button"
                    type="button"
                    color="gray"
                    size="sm"
                    x-on:click="apply('default'){{ ($isDropdown && $closesOnApply) ? '; close()' : '' }}"
                >
                    {{ __('filament-dynamic-tables::dynamic-tables.view_manager.reset_view') }}
                </x-filament::link>
            @endif

            @if ($saveAction)
                <x-filament::link
                    tag="button"
                    type="button"
                    size="sm"
                    :icon="Icon::resolve(Icon::QUICK_SAVE_TRIGGER)"
                    wire:click="mountAction('dynamicTablesSaveView')"
                    x-on:click="if (typeof close === 'function') close()"
                >
                    {{ __('filament-dynamic-tables::dynamic-tables.view_manager.save_view') }}
                </x-filament::link>
            @endif
        </div>
    @endif
</div>
