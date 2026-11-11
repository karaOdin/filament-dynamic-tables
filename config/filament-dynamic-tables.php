<?php

use KaraOdin\FilamentDynamicTables\Enums\FavoritesBarTheme;
use KaraOdin\FilamentDynamicTables\Enums\SearchConstraint;
use KaraOdin\FilamentDynamicTables\Models\ManagedView;
use KaraOdin\FilamentDynamicTables\Models\Preference;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Policies\UserViewPolicy;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource;

/*
|--------------------------------------------------------------------------
| Filament Dynamic Tables
|--------------------------------------------------------------------------
|
| Every option here can also be set fluently on the panel plugin
| (DynamicTablesPlugin::make()->...) and per table by overriding the
| configureDynamicTables() method on the Livewire component. Plugin and
| per-table values always win over this file. This file is the fallback
| used by standalone Table Builder components that live outside a panel.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    'users' => [
        'model' => null, // null = the model of the auth guard's user provider
        'table' => 'users',
        'guard' => null, // null = Filament's panel guard, then the default guard
        'name_attribute' => 'name',
        'key_column' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'user_views' => 'dynamic_table_user_views',
        'managed_views' => 'dynamic_table_managed_views',
        'preferences' => 'dynamic_table_preferences',
    ],

    'models' => [
        'user_view' => UserView::class,
        'managed_view' => ManagedView::class,
        'preference' => Preference::class,
    ],

    'policy' => UserViewPolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | Enable when every tenant shares one database (Filament tenancy, simple
    | one-to-many tenancy, spatie/laravel-multitenancy single database).
    | Leave disabled for database-per-tenant setups such as stancl/tenancy,
    | where the migrations simply run inside each tenant database.
    |
    */
    'tenancy' => [
        'enabled' => false,
        'model' => null, // e.g. App\Models\Team::class, enables tenancy
        'column' => 'tenant_id',
        'scope' => true, // false shares views across tenants
    ],

    /*
    |--------------------------------------------------------------------------
    | User views
    |--------------------------------------------------------------------------
    */
    'user_views' => [
        'enabled' => true,
        'public_views' => true,
        'global_favorites' => false,
        'global_manageable' => true,
        'new_global_sort_position' => 'before', // before, after
        'public_views_require_approval' => false,
        'global_favorites_require_approval' => false,
        'initial_status' => null, // pending, approved, rejected
        'minimum_status_for_display' => null,
        'icon_select' => true,
        'color_select' => true,
        'icons' => null, // null = every Heroicon
        'include_outline_icons' => true,
        'include_solid_icons' => true,
        'colors' => ['primary', 'success', 'warning', 'danger', 'info', 'gray'],
        'persist_active_view_in_session' => false,
        'modified_indicator' => true,
        'name_max_length' => 60,
    ],

    'preset_views' => [
        'enabled' => true,
        'manageable' => true,
        'new_sort_position' => 'before', // before, after
        'create_using' => true,
        'lock_icon' => false, // true or an icon name
    ],

    'default_view' => [
        'enabled' => true,
        'label' => null, // null = translated "Default"
        'icon' => null, // null = the FAVORITES_BAR_DEFAULT icon alias
    ],

    'managed_default_views' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Favorites bar
    |--------------------------------------------------------------------------
    */
    'favorites_bar' => [
        'enabled' => true,
        'theme' => FavoritesBarTheme::Github,
        'size' => 'md', // sm, md, lg
        'icon_position' => 'before', // before, after
        'icons' => true,
        'divider' => false,
        'badges' => true,
        'loading_indicator' => false,
        'hidden_when_empty' => false,
    ],

    'quick_save' => [
        'enabled' => true,
        'position' => 'favorites_bar', // favorites_bar, toolbar
        'favorites_bar_position' => 'end', // start, end
        'slide_over' => true,
        'icon' => 'heroicon-o-plus',
        'make_favorite' => true,
        'favorite_by_default' => true,
        'public_by_default' => false,
        'helper_texts' => [
            'name' => false,
            'filters' => false,
            'public' => true,
            'favorite' => true,
            'global_favorite' => true,
            'active_preset_view' => false,
        ],
    ],

    'view_manager' => [
        'enabled' => true,
        'position' => 'favorites_bar', // favorites_bar, toolbar
        'favorites_bar_position' => 'end', // start, end
        'table_position' => null, // a Filament table render hook, e.g. tables::toolbar.search.after
        'slide_over' => false, // dropdown by default
        'icon' => 'heroicon-o-queue-list',
        'fallback_icon' => 'heroicon-o-queue-list',
        'searchable' => true,
        'reorderable' => true,
        'preset_views' => true,
        'public_views' => true,
        'global_views' => true,
        'close_on_apply' => true,
        'save_view' => false,
        'reset_view' => false,
        'click_to_apply' => true,
        'apply_button' => true,
        'active_view_indicator' => true,
        'active_view_badge' => false,
        'view_type_icons' => true,
        'view_type_badges' => false,
        'public_indicator_when_global' => false,
        'view_icon' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-sort
    |--------------------------------------------------------------------------
    */
    'multi_sort' => [
        'enabled' => true,
        'max_columns' => 5,
        'icon' => 'heroicon-o-arrows-up-down',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick filters
    |--------------------------------------------------------------------------
    */
    'quick_filters' => [
        'enabled' => false,
        'pinning' => true,
        'pinned' => [], // filter names pinned for everyone by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced search
    |--------------------------------------------------------------------------
    */
    'advanced_search' => [
        'enabled' => false,
        'default_constraint' => SearchConstraint::Contains,
        'constraints' => null, // null = all eight constraints
        'column_selection' => false,
        'keyboard_syntax' => true,
        'grouping' => false,
        'max_groups' => 5,
        'boolean_syntax' => false,
        'max_words' => null,
        'dropdown_columns_first' => false,
        'dropdown_open_on_focus' => false,
        'keybindings' => [], // e.g. ['command+k', 'ctrl+k']
    ],

    'filter_builder' => [
        'expand_view_styles' => [], // e.g. ['right: 100px', 'top: 24px']
    ],

    /*
    |--------------------------------------------------------------------------
    | Loading overlay
    |--------------------------------------------------------------------------
    */
    'loading_overlay' => [
        'enabled' => false,
        'skeleton_rows' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | User views resource
    |--------------------------------------------------------------------------
    */
    'resource' => [
        'enabled' => false,
        'class' => UserViewResource::class,
        'navigation_group' => null,
        'navigation_label' => null,
        'navigation_icon' => 'heroicon-o-bookmark-square',
        'navigation_sort' => null,
        'navigation_badge' => true, // number of views pending approval
        'slug' => 'dynamic-table-views',
        'load_all_users' => true,
        'panels' => null, // other panel ids whose views are listed too
    ],

    'notifications' => [
        'enabled' => true,
    ],
];
