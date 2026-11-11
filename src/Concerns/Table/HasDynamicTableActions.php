<?php

namespace KaraOdin\FilamentDynamicTables\Concerns\Table;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Illuminate\Support\Str;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias;
use Throwable;

trait HasDynamicTableActions
{
    public function dynamicTablesSaveViewAction(): Action
    {
        $config = $this->getDynamicTablesConfig();

        $action = Action::make('dynamicTablesSaveView')
            ->label(__('filament-dynamic-tables::dynamic-tables.quick_save.label'))
            ->tooltip(__('filament-dynamic-tables::dynamic-tables.quick_save.tooltip'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::QUICK_SAVE_TRIGGER, $config->getQuickSaveIcon()))
            ->iconButton()
            ->color('gray')
            ->extraAttributes(['class' => 'fdt-quick-save-trigger'])
            ->slideOver($config->isQuickSaveSlideOver())
            ->modalWidth('md')
            ->modalHeading(__('filament-dynamic-tables::dynamic-tables.quick_save.heading'))
            ->modalDescription(__('filament-dynamic-tables::dynamic-tables.quick_save.description'))
            ->modalSubmitActionLabel(__('filament-dynamic-tables::dynamic-tables.quick_save.submit'))
            ->fillForm(fn (): array => [
                'name' => null,
                'icon' => null,
                'color' => null,
                'is_favorite' => $config->isQuickSaveFavoriteByDefault(),
                'is_public' => $config->isPublicViewsEnabled() && $config->isQuickSavePublicByDefault(),
                'is_global_favorite' => false,
                'is_default' => false,
            ])
            ->schema(fn (): array => $this->isDynamicTableSaveBlockedByPresetView() ? [] : $this->getDynamicTableViewFormSchema())
            ->modalHidden(fn (): bool => $this->isDynamicTableSaveBlockedByPresetView())
            ->action(function (array $data): void {
                $this->createDynamicTableUserView($data);
            })
            ->visible(fn (): bool => $config->isQuickSaveEnabled() && (bool) $this->getDynamicTablesUser() && $this->dynamicTablesCan('create'));

        if ($modifier = $config->getModifyQuickSaveActionUsing()) {
            $action = $action->evaluate($modifier, ['action' => $action]) ?? $action;
        }

        return $action;
    }

    public function dynamicTablesEditViewAction(): Action
    {
        $config = $this->getDynamicTablesConfig();

        $action = Action::make('dynamicTablesEditView')
            ->label(__('filament-dynamic-tables::dynamic-tables.view_manager.actions.edit'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::EDIT_VIEW))
            ->slideOver($config->isQuickSaveSlideOver())
            ->modalWidth('md')
            ->modalHeading(__('filament-dynamic-tables::dynamic-tables.edit_view.heading'))
            ->modalSubmitActionLabel(__('filament-dynamic-tables::dynamic-tables.edit_view.submit'))
            ->fillForm(function (array $arguments): array {
                $view = $this->resolveDynamicTableUserViewFromArguments($arguments);

                if (! $view) {
                    return [];
                }

                $managed = $this->getDynamicTableManagedViews()->get($view->getKeyString());

                return [
                    'name' => $view->name,
                    'icon' => $view->icon,
                    'color' => $view->color,
                    'is_favorite' => $managed->is_favorite ?? ($view->is_global_favorite && $view->isApproved()),
                    'is_public' => $view->is_public,
                    'is_global_favorite' => $view->is_global_favorite,
                    'is_default' => (bool) $managed?->is_default,
                ];
            })
            ->schema(fn (array $arguments): array => $this->getDynamicTableViewFormSchema($this->resolveDynamicTableUserViewFromArguments($arguments)))
            ->action(function (array $data, array $arguments): void {
                $view = $this->resolveDynamicTableUserViewFromArguments($arguments);

                if ($view) {
                    $this->updateDynamicTableUserView($view, $data);
                }
            })
            ->authorize(fn (array $arguments): bool => ($view = $this->resolveDynamicTableUserViewFromArguments($arguments)) && $this->dynamicTablesCan('update', $view));

        if ($modifier = $config->getModifyEditViewActionUsing()) {
            $action = $action->evaluate($modifier, ['action' => $action]) ?? $action;
        }

        return $action;
    }

    public function dynamicTablesReplaceViewAction(): Action
    {
        return Action::make('dynamicTablesReplaceView')
            ->label(__('filament-dynamic-tables::dynamic-tables.view_manager.actions.replace'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::REPLACE_VIEW))
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('filament-dynamic-tables::dynamic-tables.replace_view.heading', [
                'name' => $this->resolveDynamicTableUserViewFromArguments($arguments)?->name,
            ]))
            ->modalDescription(__('filament-dynamic-tables::dynamic-tables.replace_view.description'))
            ->modalSubmitActionLabel(__('filament-dynamic-tables::dynamic-tables.replace_view.submit'))
            ->action(function (array $arguments): void {
                $this->replaceDynamicTableUserView((string) ($arguments['view'] ?? ''));
            })
            ->authorize(fn (array $arguments): bool => ($view = $this->resolveDynamicTableUserViewFromArguments($arguments)) && $this->dynamicTablesCan('update', $view));
    }

    public function dynamicTablesDeleteViewAction(): Action
    {
        return Action::make('dynamicTablesDeleteView')
            ->label(__('filament-dynamic-tables::dynamic-tables.view_manager.actions.delete'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::DELETE_VIEW))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('filament-dynamic-tables::dynamic-tables.delete_view.heading', [
                'name' => $this->resolveDynamicTableUserViewFromArguments($arguments)?->name,
            ]))
            ->modalDescription(__('filament-dynamic-tables::dynamic-tables.delete_view.description'))
            ->modalSubmitActionLabel(__('filament-dynamic-tables::dynamic-tables.delete_view.submit'))
            ->action(function (array $arguments): void {
                $this->deleteDynamicTableUserView((string) ($arguments['view'] ?? ''));
            })
            ->authorize(fn (array $arguments): bool => ($view = $this->resolveDynamicTableUserViewFromArguments($arguments)) && $this->dynamicTablesCan('delete', $view));
    }

    /**
     * The multi-sort trigger. It only opens the dropdown, so it is rendered
     * without a Livewire click handler.
     */
    public function dynamicTablesMultiSortAction(): Action
    {
        $config = $this->getDynamicTablesConfig();

        $action = Action::make('dynamicTablesMultiSort')
            ->label(__('filament-dynamic-tables::dynamic-tables.multi_sort.label'))
            ->tooltip(__('filament-dynamic-tables::dynamic-tables.multi_sort.label'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::MULTI_SORT_TRIGGER, $config->getMultiSortIcon()))
            ->iconButton()
            ->color('gray')
            ->badge(fn (): ?int => count($this->getTableMultiSort()) ?: null)
            ->livewireClickHandlerEnabled(false)
            ->extraAttributes(['class' => 'fdt-multi-sort-trigger'])
            ->authorize(true);

        if ($modifier = $config->getModifyMultiSortActionUsing()) {
            $action = $action->evaluate($modifier, ['action' => $action]) ?? $action;
        }

        return $action;
    }

    public function dynamicTablesViewManagerAction(): Action
    {
        $config = $this->getDynamicTablesConfig();

        $action = Action::make('dynamicTablesViewManager')
            ->label(__('filament-dynamic-tables::dynamic-tables.view_manager.label'))
            ->tooltip(__('filament-dynamic-tables::dynamic-tables.view_manager.tooltip'))
            ->icon(DynamicTablesIconAlias::resolve(DynamicTablesIconAlias::VIEW_MANAGER_TRIGGER, $config->getViewManagerIcon()))
            ->iconButton()
            ->color('gray')
            ->extraAttributes(['class' => 'fdt-view-manager-trigger'])
            ->slideOver($config->isViewManagerSlideOver())
            ->modalWidth('md')
            ->modalHeading(__('filament-dynamic-tables::dynamic-tables.view_manager.heading'))
            ->modalContent(fn () => view('filament-dynamic-tables::view-manager.index', [
                'livewire' => $this,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-dynamic-tables::dynamic-tables.view_manager.close'))
            // Edit, replace and delete open on top of the view manager, and
            // Filament resolves actions mounted from a modal through its parent.
            ->registerModalActions([
                $this->dynamicTablesEditViewAction(),
                $this->dynamicTablesReplaceViewAction(),
                $this->dynamicTablesDeleteViewAction(),
            ])
            ->visible(fn (): bool => $config->isViewManagerEnabled());

        if ($modifier = $config->getModifyViewManagerActionUsing()) {
            $action = $action->evaluate($modifier, ['action' => $action]) ?? $action;
        }

        return $action;
    }

    /**
     * @return array<Component | Field>
     */
    protected function getDynamicTableViewFormSchema(?UserView $view = null): array
    {
        $config = $this->getDynamicTablesConfig();
        $isCreating = $view === null;

        $colorOptions = collect($config->getColors())
            ->mapWithKeys(fn (string $color): array => [$color => $this->renderDynamicTableColorOption($color)])
            ->all();

        $helper = fn (string $field, string $key): ?string => $config->hasQuickSaveHelperText($field)
            ? __("filament-dynamic-tables::dynamic-tables.form.{$key}")
            : null;

        return [
            TextInput::make('name')
                ->label(__('filament-dynamic-tables::dynamic-tables.form.name'))
                ->helperText($helper('name', 'name_help'))
                ->required()
                ->maxLength($config->getViewNameMaxLength())
                ->autofocus(),
            Grid::make(2)
                ->schema([
                    Select::make('icon')
                        ->label(__('filament-dynamic-tables::dynamic-tables.form.icon'))
                        ->allowHtml()
                        ->searchable()
                        ->native(false)
                        ->options(fn (): array => $this->getDynamicTableIconOptions())
                        ->getSearchResultsUsing(fn (?string $search): array => $this->getDynamicTableIconOptions($search))
                        ->getOptionLabelUsing(fn (?string $value): ?string => filled($value) && array_key_exists($value, $config->getIcons()) ? $this->renderDynamicTableIconOption($value, $config->getIcons()[$value]) : null)
                        ->visible(fn (): bool => $config->isIconSelectEnabled() && $this->dynamicTablesCan('selectIcon')),
                    Select::make('color')
                        ->label(__('filament-dynamic-tables::dynamic-tables.form.color'))
                        ->options($colorOptions)
                        ->allowHtml()
                        ->native(false)
                        ->visible(fn (): bool => $config->isColorSelectEnabled() && $this->dynamicTablesCan('selectColor')),
                ]),
            View::make('filament-dynamic-tables::quick-save.summary')
                ->viewData(fn (): array => [
                    'summary' => $this->getDynamicTableStateSummary(),
                    'helperText' => $helper('filters', 'filters_help'),
                    'presetHelperText' => ($config->hasQuickSaveHelperText('active_preset_view') && $this->getActiveDynamicTablePresetView()?->hasQueryModification())
                        ? __('filament-dynamic-tables::dynamic-tables.form.active_preset_view_help', ['name' => strip_tags((string) $this->getActiveDynamicTablePresetView()->getLabel())])
                        : null,
                ])
                ->visible($isCreating),
            Toggle::make('is_favorite')
                ->label(__('filament-dynamic-tables::dynamic-tables.form.is_favorite'))
                ->helperText($helper('favorite', 'is_favorite_help'))
                ->visible(fn (): bool => $config->isFavoritesBarEnabled() && $config->canQuickSaveMakeFavorite() && $this->dynamicTablesCan('makeFavorite')),
            Toggle::make('is_public')
                ->label(__('filament-dynamic-tables::dynamic-tables.form.is_public'))
                ->helperText(fn (): ?string => $this->doesDynamicTableViewNeedApproval('public', $view)
                    ? __('filament-dynamic-tables::dynamic-tables.form.requires_approval')
                    : $helper('public', 'is_public_help'))
                ->visible(fn (): bool => $config->isPublicViewsEnabled() && $this->dynamicTablesCan('makePublic')),
            Toggle::make('is_global_favorite')
                ->label(__('filament-dynamic-tables::dynamic-tables.form.is_global_favorite'))
                ->helperText(fn (): ?string => $this->doesDynamicTableViewNeedApproval('global', $view)
                    ? __('filament-dynamic-tables::dynamic-tables.form.requires_approval')
                    : $helper('global_favorite', 'is_global_favorite_help'))
                ->visible(fn (): bool => $config->isGlobalFavoritesEnabled() && $this->dynamicTablesCan('makeGlobalFavorite')),
            Toggle::make('is_default')
                ->label(__('filament-dynamic-tables::dynamic-tables.form.is_default'))
                ->helperText(__('filament-dynamic-tables::dynamic-tables.form.is_default_help'))
                ->visible(fn (): bool => $config->isManagedDefaultViewsEnabled()),
        ];
    }

    protected function doesDynamicTableViewNeedApproval(string $type, ?UserView $view): bool
    {
        $config = $this->getDynamicTablesConfig();

        if ($this->dynamicTablesCan('approve', $view)) {
            return false;
        }

        if ($config->hasExplicitInitialStatus()) {
            return ! $config->isStatusDisplayable($config->getInitialStatus());
        }

        return $type === 'public' ? $config->doPublicViewsRequireApproval() : $config->doGlobalFavoritesRequireApproval();
    }

    /**
     * Icon options rendered with their SVG. Without a search only the first
     * ones are listed; the select searches the rest.
     *
     * @return array<string, string>
     */
    protected function getDynamicTableIconOptions(?string $search = null): array
    {
        $icons = $this->getDynamicTablesConfig()->getIcons();

        if (filled($search)) {
            $needle = mb_strtolower(trim($search));

            $icons = array_filter(
                $icons,
                fn (string $label, string $icon): bool => str_contains(mb_strtolower($label), $needle) || str_contains($icon, $needle),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return collect($icons)
            ->take(60)
            ->mapWithKeys(fn (string $label, string $icon): array => [$icon => $this->renderDynamicTableIconOption($icon, $label)])
            ->all();
    }

    /**
     * What a saved view will contain, as short labels, shown in the save form.
     *
     * @return array<string, array<string>>
     */
    public function getDynamicTableStateSummary(): array
    {
        $table = $this->getTable();
        $summary = [];

        try {
            $filters = collect($table->getFilters())
                ->flatMap(fn ($filter): array => $filter->isHidden() ? [] : $filter->getIndicators())
                ->map(fn ($indicator): string => strip_tags((string) $indicator->getLabel()))
                ->values()
                ->all();
        } catch (Throwable) {
            $filters = [];
        }

        if ($preset = $this->getActiveDynamicTablePresetView()) {
            $filters = [...$preset->getIndicators(), ...$filters];
        }

        if (filled($filters)) {
            $summary['filters'] = $filters;
        }

        if (filled($search = $this->getTableSearch())) {
            $summary['search'] = [$search];
        }

        if (filled($sorts = $this->getTableMultiSortItems())) {
            $summary['sort'] = collect($sorts)
                ->map(fn (array $sort): string => $sort['label'] . ' ' . ($sort['direction'] === 'desc' ? '↓' : '↑'))
                ->all();
        }

        if (filled($this->tableGrouping) && ($group = $table->getGrouping())) {
            $summary['grouping'] = [strip_tags((string) $group->getLabel())];
        }

        $hidden = collect($table->getColumns())
            ->filter(fn ($column): bool => $column->isToggleable() && $column->isToggledHidden())
            ->count();

        if ($hidden > 0) {
            $summary['columns'] = [trans_choice('filament-dynamic-tables::dynamic-tables.form.summary.hidden_columns', $hidden, ['count' => $hidden])];
        }

        return $summary;
    }

    protected function renderDynamicTableIconOption(string $icon, string $label): string
    {
        try {
            $svg = svg($icon, 'fdt-option-icon')->toHtml();
        } catch (Throwable) {
            $svg = '';
        }

        return '<span class="fdt-option">' . $svg . '<span>' . e($label) . '</span></span>';
    }

    protected function renderDynamicTableColorOption(string $color): string
    {
        $label = __("filament-dynamic-tables::dynamic-tables.colors.{$color}");

        if (str_starts_with($label, 'filament-dynamic-tables::')) {
            $label = Str::headline($color);
        }

        return '<span class="fdt-option"><span class="fdt-color-dot" style="--fdt-dot: var(--' . e($color) . '-500, currentColor)"></span><span>' . e($label) . '</span></span>';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function resolveDynamicTableUserViewFromArguments(array $arguments): ?UserView
    {
        $key = (string) ($arguments['view'] ?? '');

        if (! str_starts_with($key, 'user:')) {
            return null;
        }

        return $this->findDynamicTableUserView(Str::after($key, 'user:'));
    }
}
