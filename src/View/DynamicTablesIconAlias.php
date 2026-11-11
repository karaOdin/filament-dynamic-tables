<?php

namespace KaraOdin\FilamentDynamicTables\View;

use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Icon aliases: swap any of the package's icons app-wide with
 *
 *     FilamentIcon::register([
 *         DynamicTablesIconAlias::QUICK_SAVE_TRIGGER => 'phosphor-floppy-disk',
 *     ]);
 */
final class DynamicTablesIconAlias
{
    // Trigger actions
    public const MULTI_SORT_TRIGGER = 'filament-dynamic-tables::multi-sort.trigger';

    public const VIEW_MANAGER_TRIGGER = 'filament-dynamic-tables::view-manager.trigger';

    public const QUICK_SAVE_TRIGGER = 'filament-dynamic-tables::quick-save.trigger';

    // View manager actions
    public const ADD_TO_FAVORITES = 'filament-dynamic-tables::view-manager.add-to-favorites';

    public const APPLY_VIEW = 'filament-dynamic-tables::view-manager.apply-view';

    public const DELETE_VIEW = 'filament-dynamic-tables::view-manager.delete-view';

    public const EDIT_VIEW = 'filament-dynamic-tables::view-manager.edit-view';

    public const REPLACE_VIEW = 'filament-dynamic-tables::view-manager.replace-view';

    public const REMOVE_FROM_FAVORITES = 'filament-dynamic-tables::view-manager.remove-from-favorites';

    public const SET_DEFAULT_VIEW = 'filament-dynamic-tables::view-manager.set-default-view';

    public const REMOVE_DEFAULT_VIEW = 'filament-dynamic-tables::view-manager.remove-default-view';

    public const SHOW_VIEW_MANAGER = 'filament-dynamic-tables::view-manager.show';

    // Multi-sort
    public const SORT_ASCENDING = 'filament-dynamic-tables::multi-sort.ascending';

    public const SORT_DESCENDING = 'filament-dynamic-tables::multi-sort.descending';

    // Filter builder
    public const FILTER_BUILDER_DELETE = 'filament-dynamic-tables::filter-builder.delete';

    public const FILTER_BUILDER_SEARCH = 'filament-dynamic-tables::filter-builder.search';

    public const FILTER_BUILDER_EXPAND = 'filament-dynamic-tables::filter-builder.expand';

    // Shared
    public const DRAG_HANDLE = 'filament-dynamic-tables::drag-handle';

    public const VIEW_MANAGER_ACTION_GROUP = 'filament-dynamic-tables::view-manager.action-group';

    // View type indicators
    public const DEFAULT_VIEW_INDICATOR = 'filament-dynamic-tables::indicators.default-view';

    public const USER_OWNER_INDICATOR = 'filament-dynamic-tables::indicators.user-owner';

    public const GLOBAL_VIEW_INDICATOR = 'filament-dynamic-tables::indicators.global-view';

    public const PUBLIC_VIEW_INDICATOR = 'filament-dynamic-tables::indicators.public-view';

    public const PRESET_LOCKED_INDICATOR = 'filament-dynamic-tables::indicators.preset-locked';

    // View manager reordering
    public const REORDER_VIEWS = 'filament-dynamic-tables::view-manager.reorder';

    public const REORDER_VIEWS_CONFIRM = 'filament-dynamic-tables::view-manager.reorder-confirm';

    // Favorites bar
    public const FAVORITES_BAR_DEFAULT = 'filament-dynamic-tables::favorites-bar.default';

    // Advanced search
    public const ADVANCED_SEARCH_CONSTRAINT = 'filament-dynamic-tables::advanced-search.constraint';

    public const ADVANCED_SEARCH_COLUMN = 'filament-dynamic-tables::advanced-search.column';

    public const ADVANCED_SEARCH_EXTRA_COLUMN = 'filament-dynamic-tables::advanced-search.extra-column';

    public const ADVANCED_SEARCH_BOOLEAN_AND = 'filament-dynamic-tables::advanced-search.boolean-and';

    public const ADVANCED_SEARCH_BOOLEAN_OR = 'filament-dynamic-tables::advanced-search.boolean-or';

    public const ADVANCED_SEARCH_CLEAR = 'filament-dynamic-tables::advanced-search.clear';

    /**
     * Default icon for each alias.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            self::MULTI_SORT_TRIGGER => 'heroicon-o-arrows-up-down',
            self::VIEW_MANAGER_TRIGGER => 'heroicon-o-queue-list',
            self::QUICK_SAVE_TRIGGER => 'heroicon-o-plus',
            self::ADD_TO_FAVORITES => 'heroicon-o-star',
            self::APPLY_VIEW => 'heroicon-o-check',
            self::DELETE_VIEW => 'heroicon-o-trash',
            self::EDIT_VIEW => 'heroicon-o-pencil-square',
            self::REPLACE_VIEW => 'heroicon-o-arrow-path',
            self::REMOVE_FROM_FAVORITES => 'heroicon-s-star',
            self::SET_DEFAULT_VIEW => 'heroicon-o-home',
            self::REMOVE_DEFAULT_VIEW => 'heroicon-s-home',
            self::SHOW_VIEW_MANAGER => 'heroicon-o-queue-list',
            self::SORT_ASCENDING => 'heroicon-m-bars-arrow-up',
            self::SORT_DESCENDING => 'heroicon-m-bars-arrow-down',
            self::FILTER_BUILDER_DELETE => 'heroicon-m-x-mark',
            self::FILTER_BUILDER_SEARCH => 'heroicon-m-magnifying-glass',
            self::FILTER_BUILDER_EXPAND => 'heroicon-m-arrows-pointing-out',
            self::DRAG_HANDLE => 'heroicon-m-bars-2',
            self::VIEW_MANAGER_ACTION_GROUP => 'heroicon-m-ellipsis-vertical',
            self::DEFAULT_VIEW_INDICATOR => 'heroicon-s-home',
            self::USER_OWNER_INDICATOR => 'heroicon-m-user',
            self::GLOBAL_VIEW_INDICATOR => 'heroicon-m-globe-alt',
            self::PUBLIC_VIEW_INDICATOR => 'heroicon-m-eye',
            self::PRESET_LOCKED_INDICATOR => 'heroicon-m-lock-closed',
            self::REORDER_VIEWS => 'heroicon-m-arrows-up-down',
            self::REORDER_VIEWS_CONFIRM => 'heroicon-m-check',
            self::FAVORITES_BAR_DEFAULT => 'heroicon-o-table-cells',
            self::ADVANCED_SEARCH_CONSTRAINT => 'heroicon-m-adjustments-horizontal',
            self::ADVANCED_SEARCH_COLUMN => 'heroicon-m-view-columns',
            self::ADVANCED_SEARCH_EXTRA_COLUMN => 'heroicon-m-circle-stack',
            self::ADVANCED_SEARCH_BOOLEAN_AND => 'heroicon-m-plus',
            self::ADVANCED_SEARCH_BOOLEAN_OR => 'heroicon-m-arrows-right-left',
            self::ADVANCED_SEARCH_CLEAR => 'heroicon-m-x-mark',
        ];
    }

    /**
     * The registered icon for an alias, or the package default.
     */
    public static function resolve(string $alias, string | \BackedEnum | null $fallback = null): string | \BackedEnum | Htmlable
    {
        $icon = FilamentIcon::resolve($alias);

        if (filled($icon)) {
            return $icon;
        }

        return $fallback ?? self::defaults()[$alias] ?? 'heroicon-o-question-mark-circle';
    }
}
