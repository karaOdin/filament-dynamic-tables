<?php

namespace KaraOdin\FilamentDynamicTables\Support;

use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Injects the package UI into every Filament table that uses the
 * DynamicTables trait, through Filament's table render hooks, so no table
 * views need to be overridden.
 */
class RenderHooks
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_BEFORE,
            fn (): ?string => static::hasStandaloneFavoritesBar() ? null : static::render('favorites-bar'),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_BEFORE,
            fn (): ?string => static::render('advanced-search.controls'),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): ?string => static::render('toolbar.actions', ['position' => 'container']),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_END,
            fn (): ?string => static::render('toolbar.actions', ['position' => 'end']),
        );

        // Hooks the view manager can be moved to with viewManagerInTable(position: ...).
        $registered = [];

        foreach ([
            'TOOLBAR_START', 'TOOLBAR_SEARCH_BEFORE', 'TOOLBAR_GROUPING_SELECTOR_BEFORE', 'TOOLBAR_GROUPING_SELECTOR_AFTER',
            'TOOLBAR_REORDER_TRIGGER_BEFORE', 'TOOLBAR_REORDER_TRIGGER_AFTER', 'TOOLBAR_TOGGLE_COLUMN_TRIGGER_BEFORE',
            'TOOLBAR_TOGGLE_COLUMN_TRIGGER_AFTER', 'TOOLBAR_COLUMN_MANAGER_TRIGGER_BEFORE', 'TOOLBAR_COLUMN_MANAGER_TRIGGER_AFTER',
            'TOOLBAR_SEARCH_AFTER', 'TOOLBAR_END', 'HEADER_BEFORE', 'HEADER_AFTER',
        ] as $constant) {
            if (! defined(TablesRenderHook::class . '::' . $constant)) {
                continue;
            }

            $hook = constant(TablesRenderHook::class . '::' . $constant);

            if (in_array($hook, $registered, true)) {
                continue;
            }

            $registered[] = $hook;

            FilamentView::registerRenderHook(
                $hook,
                fn (): ?string => static::render('toolbar.actions', ['position' => "hook:{$hook}"]),
            );
        }

        FilamentView::registerRenderHook(
            TablesRenderHook::FILTER_INDICATORS,
            fn (array $data = []): ?string => static::render('quick-filters.indicators', ['filterIndicators' => $data['filterIndicators'] ?? []]),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_AFTER,
            fn (): ?string => static::render('quick-filters.pinned'),
        );

        // Filament 5 has a hook right before the table content; Filament 4 falls back to the toolbar.
        FilamentView::registerRenderHook(
            defined(TablesRenderHook::class . '::CONTENT_BEFORE') ? TablesRenderHook::CONTENT_BEFORE : TablesRenderHook::TOOLBAR_AFTER,
            fn (): ?string => static::render('loading-overlay'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data = []): ?string
    {
        $livewire = static::currentTable();

        if ((! $livewire) || (! method_exists($livewire, 'getDynamicTablesConfig'))) {
            return null;
        }

        // Exceptions are not caught here on purpose: Laravel flushes the
        // shared Blade component stack when a nested view throws, so the
        // surrounding table could not finish rendering anyway.
        $html = view("filament-dynamic-tables::{$view}", [
            'livewire' => $livewire,
            'config' => $livewire->getDynamicTablesConfig(),
            ...$data,
        ])->render();

        return filled(trim($html)) ? $html : null;
    }

    /** @var array<string, bool> */
    protected static array $standaloneFavoritesBars = [];

    /**
     * Output of <x-filament-dynamic-tables::favorites-bar />.
     */
    public static function renderStandaloneFavoritesBar(): string
    {
        $livewire = static::currentTable();

        if (! $livewire) {
            return '';
        }

        static::$standaloneFavoritesBars[$livewire->getId()] = true;

        $html = static::render('favorites-bar', ['isStandalone' => true]);

        return filled($html) ? $html : '';
    }

    protected static function hasStandaloneFavoritesBar(): bool
    {
        $livewire = static::currentTable();

        return $livewire && (static::$standaloneFavoritesBars[$livewire->getId()] ?? false);
    }

    public static function currentTable(): ?Component
    {
        $livewire = Livewire::current();

        if (! $livewire instanceof Component) {
            return null;
        }

        if (! method_exists($livewire, 'getDynamicTablesConfig')) {
            return null;
        }

        return $livewire;
    }
}
