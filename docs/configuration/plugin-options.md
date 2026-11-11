# Plugin Options

Every option is set fluently on the plugin and has a matching key in `config/filament-dynamic-tables.php`.

- A value set on the plugin overrides the configuration file.
- A `null` value in the configuration file falls back to the package default.
- Standalone Table Builder components, which have no plugin, use the configuration file.
- [Per-table configuration](per-table-configuration.md) overrides both for a single table.

Method names follow the Advanced Tables plugin, so existing configurations can be moved over with few changes.

## Users

These options are read by the migration. Set them before migrating.

| Method | Config key | Default |
| --- | --- | --- |
| `user()` | `users.model` | the auth guard's user model |
| `userTable()` | `users.table` | `users` |
| `userTableNameColumn()` | `users.name_attribute` | `name` |
| `userTableKeyColumn()` | `users.key_column` | `id` |
| `authGuard()` | `users.guard` | the panel guard, then the default guard |

## Models and tables

| Method | Config key | Default |
| --- | --- | --- |
| `userView()` | `models.user_view` | `UserView` |
| `managedUserView()`, `managedPresetView()` | `models.managed_view` | `ManagedView` |
| | `models.preference` | `Preference` |
| | `tables.user_views` | `dynamic_table_user_views` |
| | `tables.managed_views` | `dynamic_table_managed_views` |
| | `tables.preferences` | `dynamic_table_preferences` |
| | `policy` | `UserViewPolicy` |

## Tenancy

See [Multi-tenancy](multi-tenancy.md).

| Method | Config key | Default |
| --- | --- | --- |
| `tenancy()` | `tenancy.enabled` | `false` |
| `tenant()` | `tenancy.model` | `null` |
| `tenantColumn()` | `tenancy.column` | `tenant_id` |
| `scopeToTenancy()` | `tenancy.scope` | `true` |
| `resolveTenantUsing()` | | |

## User views

See [User views](../features/user-views.md) and [Public views, global favorites and approval](../features/sharing-and-approval.md).

| Method | Config key | Default |
| --- | --- | --- |
| `userViewsEnabled()` | `user_views.enabled` | `true` |
| `quickSaveMakePublic()` | `user_views.public_views` | `true` |
| `quickSaveMakeGlobalFavorite()` | `user_views.global_favorites` | `false` |
| `globalUserViewsManageable()` | `user_views.global_manageable` | `true` |
| `newGlobalUserViewSortPosition()` | `user_views.new_global_sort_position` | `before` |
| `publicViewsRequireApproval()` | `user_views.public_views_require_approval` | `false` |
| `globalFavoritesRequireApproval()` | `user_views.global_favorites_require_approval` | `false` |
| `approval()` | both of the above | `false` |
| `initialStatus()` | `user_views.initial_status` | `pending` |
| `minimumStatusForDisplay()` | `user_views.minimum_status_for_display` | `pending` |
| `quickSaveIconSelect()` | `user_views.icon_select` | `true` |
| `icons()` | `user_views.icons` | every Heroicon |
| `quickSaveIncludeOutlineIcons()` | `user_views.include_outline_icons` | `true` |
| `quickSaveIncludeSolidIcons()` | `user_views.include_solid_icons` | `true` |
| `quickSaveColorPicker()` | `user_views.color_select` | `true` |
| `quickSaveColors()` | `user_views.colors` | `primary`, `success`, `warning`, `danger`, `info`, `gray` |
| `persistActiveViewInSession()` | `user_views.persist_active_view_in_session` | `false` |
| `modifiedIndicator()` | `user_views.modified_indicator` | `true` |
| `viewNameMaxLength()` | `user_views.name_max_length` | `60` |

## Preset views and the Default view

See [Preset views](../features/preset-views.md).

| Method | Config key | Default |
| --- | --- | --- |
| `presetViews()` | `preset_views.enabled` | `true` |
| `presetViewsManageable()` | `preset_views.manageable` | `true` |
| `newPresetViewSortPosition()` | `preset_views.new_sort_position` | `before` |
| `createUsingPresetView()` | `preset_views.create_using` | `true` |
| `presetViewLockIcon()` | `preset_views.lock_icon` | `false` |
| `favoritesBarDefaultView()` | `default_view.enabled` | `true` |
| `defaultViewLabel()` | `default_view.label` | `Default` |
| `favoritesBarDefaultIcon()` | `default_view.icon` | alias `FAVORITES_BAR_DEFAULT` |
| `managedDefaultViewsEnabled()` | `managed_default_views.enabled` | `false` |

## Favorites bar

See [Favorites bar](../features/favorites-bar.md).

| Method | Config key | Default |
| --- | --- | --- |
| `favoritesBarEnabled()` | `favorites_bar.enabled` | `true` |
| `favoritesBarTheme()` | `favorites_bar.theme` | `Github` |
| `favoritesBarSize()` | `favorites_bar.size` | `md` |
| `favoritesBarIconPosition()` | `favorites_bar.icon_position` | `before` |
| `favoritesBarIcons()` | `favorites_bar.icons` | `true` |
| `favoritesBarDivider()` | `favorites_bar.divider` | `false` |
| `favoritesBarBadges()` | `favorites_bar.badges` | `true` |
| `favoritesBarLoadingIndicator()` | `favorites_bar.loading_indicator` | `false` |
| `favoritesBarHiddenWhenEmpty()` | `favorites_bar.hidden_when_empty` | `false` |

## Quick save

See [Quick save](../features/quick-save.md).

| Method | Config key | Default |
| --- | --- | --- |
| `quickSaveEnabled()` | `quick_save.enabled` | `true` |
| `quickSaveInFavoritesBar()`, `quickSaveInTable()` | `quick_save.position` | `favorites_bar` |
| `quickSaveInFavoritesBar(position:)` | `quick_save.favorites_bar_position` | `end` |
| `quickSaveSlideOver()` | `quick_save.slide_over` | `true` |
| `quickSaveInFavoritesBar(icon:)` | `quick_save.icon` | `heroicon-o-plus` |
| `quickSaveMakeFavorite()` | `quick_save.make_favorite` | `true` |
| `quickSaveFavoriteByDefault()` | `quick_save.favorite_by_default` | `true` |
| `quickSavePublicByDefault()` | `quick_save.public_by_default` | `false` |
| `quickSaveNameHelperText()` | `quick_save.helper_texts.name` | `false` |
| `quickSaveFiltersHelperText()` | `quick_save.helper_texts.filters` | `false` |
| `quickSavePublicHelperText()` | `quick_save.helper_texts.public` | `true` |
| `quickSaveFavoriteHelperText()` | `quick_save.helper_texts.favorite` | `true` |
| `quickSaveGlobalFavoriteHelperText()` | `quick_save.helper_texts.global_favorite` | `true` |
| `quickSaveActivePresetViewHelperText()` | `quick_save.helper_texts.active_preset_view` | `false` |
| `quickSaveTriggerAction()` | | |
| `modifyEditViewActionUsing()` | | |

## View manager

See [View manager](../features/view-manager.md).

| Method | Config key | Default |
| --- | --- | --- |
| `viewManagerEnabled()` | `view_manager.enabled` | `true` |
| `viewManagerInFavoritesBar()`, `viewManagerInTable()` | `view_manager.position` | `favorites_bar` |
| `viewManagerInFavoritesBar(position:)` | `view_manager.favorites_bar_position` | `end` |
| `viewManagerInTable(position:)` | `view_manager.table_position` | `null` |
| `viewManagerSlideOver()` | `view_manager.slide_over` | `false` |
| `defaultViewIcon()` | `view_manager.fallback_icon` | `heroicon-o-queue-list` |
| `viewManagerSearch()` | `view_manager.searchable` | `true` |
| `viewManagerReorderable()` | `view_manager.reorderable` | `true` |
| `viewManagerPresetViews()` | `view_manager.preset_views` | `true` |
| `viewManagerPublicViews()` | `view_manager.public_views` | `true` |
| `viewManagerGlobalViews()` | `view_manager.global_views` | `true` |
| `viewManagerClosesOnApply()` | `view_manager.close_on_apply` | `true` |
| `viewManagerSaveView()` | `view_manager.save_view` | `false` |
| `viewManagerResetView()` | `view_manager.reset_view` | `false` |
| `viewManagerClickToApply()` | `view_manager.click_to_apply` | `true` |
| `viewManagerApplyButton()` | `view_manager.apply_button` | `true` |
| `viewManagerActiveViewIndicator()` | `view_manager.active_view_indicator` | `true` |
| `viewManagerActiveViewBadge()` | `view_manager.active_view_badge` | `false` |
| `viewManagerViewTypeIcons()` | `view_manager.view_type_icons` | `true` |
| `viewManagerViewTypeBadges()` | `view_manager.view_type_badges` | `false` |
| `viewManagerPublicIndicatorWhenGlobal()` | `view_manager.public_indicator_when_global` | `false` |
| `viewIcon()` | `view_manager.view_icon` | `true` |
| `viewManagerTriggerAction()` | | |

## Multi-sort

See [Multi-sort](../features/multi-sort.md).

| Method | Config key | Default |
| --- | --- | --- |
| `multiSortEnabled()` | `multi_sort.enabled` | `true` |
| `multiSortMaxColumns()` | `multi_sort.max_columns` | `5` |
| `multiSortTriggerAction()` | `multi_sort.icon` | `heroicon-o-arrows-up-down` |

## Quick filters

See [Quick filters](../features/quick-filters.md).

| Method | Config key | Default |
| --- | --- | --- |
| `quickFiltersEnabled()` | `quick_filters.enabled` | `false` |
| `quickFilterPinning()` | `quick_filters.pinning` | `true` |
| `pinnedFilters()` | `quick_filters.pinned` | `[]` |

## Advanced search

See [Advanced search](../features/advanced-search.md).

| Method | Config key | Default |
| --- | --- | --- |
| `advancedSearchEnabled()` | `advanced_search.enabled` | `false` |
| `advancedSearchDefaultConstraint()` | `advanced_search.default_constraint` | `Contains` |
| `advancedSearchConstraints()` | `advanced_search.constraints` | all eight |
| `advancedSearchColumnsEnabled()` | `advanced_search.column_selection` | `false` |
| `advancedSearchConstraintSyntaxEnabled()` | `advanced_search.keyboard_syntax` | `true` |
| `advancedSearchGroupingEnabled()` | `advanced_search.grouping` | `false` |
| `advancedSearchMaxGroups()` | `advanced_search.max_groups` | `5` |
| `advancedSearchBooleanSyntaxEnabled()` | `advanced_search.boolean_syntax` | `false` |
| `advancedSearchMaxWords()` | `advanced_search.max_words` | `null` |
| `advancedSearchDropdownColumnsFirst()` | `advanced_search.dropdown_columns_first` | `false` |
| `advancedSearchDropdownOpenOnFocus()` | `advanced_search.dropdown_open_on_focus` | `false` |
| `advancedSearchKeybindings()` | `advanced_search.keybindings` | `[]` |

## Advanced filter builder

See [Advanced filter builder](../features/advanced-filter-builder.md). Most builder options are set on `AdvancedFilter` itself.

| Method | Config key | Default |
| --- | --- | --- |
| `filterBuilderExpandViewStyles()` | `filter_builder.expand_view_styles` | `[]` |

## Loading overlay

| Method | Config key | Default |
| --- | --- | --- |
| `tableLoadingOverlay()` | `loading_overlay.enabled` | `false` |
| `loadingOverlaySkeletonRows()` | `loading_overlay.skeleton_rows` | `5` |

## User views resource

See [User views resource](../features/user-views-resource.md).

| Method | Config key | Default |
| --- | --- | --- |
| `resourceEnabled()` | `resource.enabled` | `false` |
| `userViewsResourceClass()` | `resource.class` | `UserViewResource` |
| `resourceNavigationGroup()` | `resource.navigation_group` | `null` |
| `userViewsNavigationLabel()` | `resource.navigation_label` | `null` |
| `resourceNavigationIcon()` | `resource.navigation_icon` | `heroicon-o-bookmark-square` |
| `resourceNavigationSort()` | `resource.navigation_sort` | `null` |
| `resourceNavigationBadge()` | `resource.navigation_badge` | `true` |
| `userViewsSlug()` | `resource.slug` | `dynamic-table-views` |
| `resourceLoadAllUsers()` | `resource.load_all_users` | `true` |
| `resourcePanels()` | `resource.panels` | `null` |
| `canAccessUserViewsResourceUsing()` | | |

## Notifications

| Method | Config key | Default |
| --- | --- | --- |
| `notifications()` | `notifications.enabled` | `true` |

When enabled, saving, replacing and deleting views show a Filament notification.
