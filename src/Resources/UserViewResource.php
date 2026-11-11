<?php

namespace KaraOdin\FilamentDynamicTables\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\ViewStatus;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Resources\UserViewResource\Pages;
use KaraOdin\FilamentDynamicTables\Support\Models;
use KaraOdin\FilamentDynamicTables\Support\Users;
use UnitEnum;

/**
 * Lets admins browse every saved view, approve or reject public views and
 * global favorites, and edit or delete any view.
 */
class UserViewResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        return Models::userView();
    }

    protected static function plugin(): DynamicTablesPlugin
    {
        return DynamicTablesPlugin::current();
    }

    public static function canAccess(): bool
    {
        if ($callback = static::plugin()->getCanAccessUserViewsResourceUsing()) {
            return (bool) app()->call($callback, ['user' => Users::current()]);
        }

        return parent::canAccess();
    }

    public static function getModelLabel(): string
    {
        return __('filament-dynamic-tables::dynamic-tables.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-dynamic-tables::dynamic-tables.resource.plural_model_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return static::plugin()->getUserViewsNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return static::plugin()->getUserViewsNavigationLabel();
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return static::plugin()->getUserViewsNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getUserViewsNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::plugin()->hasUserViewsNavigationBadge()) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('status', ViewStatus::Pending->value)
            ->where(fn (Builder $query) => $query->where('is_public', true)->orWhere('is_global_favorite', true))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'warning';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::plugin()->getUserViewsSlug();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');
        $plugin = static::plugin();

        if ($plugin->isTenancyEnabled() && $plugin->isScopedToTenancy()) {
            $tenant = $plugin->getTenantKey();

            filled($tenant)
                ? $query->where(Models::tenantColumn(), (string) $tenant)
                : $query->whereNull(Models::tenantColumn());
        }

        // Views of this panel's tables (plus the panels listed in
        // resourcePanels()), and views created without a panel.
        try {
            $panels = array_values(array_filter([Filament::getCurrentPanel()?->getId(), ...($plugin->getResourcePanels() ?? [])]));
        } catch (\Throwable) {
            $panels = [];
        }

        if (filled($panels)) {
            $query->where(fn (Builder $query) => $query->whereNull('panel_id')->orWhereIn('panel_id', $panels));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $plugin = static::plugin();

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-dynamic-tables::dynamic-tables.form.name'))
                            ->required()
                            ->maxLength($plugin->getViewNameMaxLength())
                            ->columnSpanFull(),
                        Select::make('icon')
                            ->label(__('filament-dynamic-tables::dynamic-tables.form.icon'))
                            ->options($plugin->getIcons())
                            ->searchable(),
                        Select::make('color')
                            ->label(__('filament-dynamic-tables::dynamic-tables.form.color'))
                            ->options(collect($plugin->getColors())->mapWithKeys(fn (string $color): array => [$color => Str::headline($color)])->all()),
                        Select::make('status')
                            ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.status'))
                            ->options(ViewStatus::class)
                            ->required(),
                        Grid::make(1)
                            ->schema([
                                Toggle::make('is_public')
                                    ->label(__('filament-dynamic-tables::dynamic-tables.form.is_public')),
                                Toggle::make('is_global_favorite')
                                    ->label(__('filament-dynamic-tables::dynamic-tables.form.is_global_favorite')),
                            ]),
                    ]),
                Section::make(__('filament-dynamic-tables::dynamic-tables.resource.state'))
                    ->collapsed()
                    ->schema([
                        Textarea::make('state')
                            ->hiddenLabel()
                            ->rows(14)
                            ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $nameAttribute = config('filament-dynamic-tables.users.name_attribute', 'name');

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-dynamic-tables::dynamic-tables.form.name'))
                    ->icon(fn (UserView $record): ?string => $record->icon)
                    ->iconColor(fn (UserView $record): ?string => $record->color)
                    ->searchable()
                    ->sortable(),
                TextColumn::make("user.{$nameAttribute}")
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('table_key')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.table'))
                    ->formatStateUsing(fn (string $state): string => Str::headline(class_basename(Str::before($state, ':'))))
                    ->tooltip(fn (UserView $record): string => $record->table_key)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.status'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.public'))
                    ->boolean()
                    ->sortable()
                    ->tooltip(__('filament-dynamic-tables::dynamic-tables.resource.toggle'))
                    ->action(static::makeToggleAction('is_public')),
                IconColumn::make('is_global_favorite')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.global_favorite'))
                    ->boolean()
                    ->sortable()
                    ->tooltip(__('filament-dynamic-tables::dynamic-tables.resource.toggle'))
                    ->action(static::makeToggleAction('is_global_favorite')),
                TextColumn::make(Models::tenantColumn())
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.tenant'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => static::plugin()->isTenancyEnabled()),
                TextColumn::make('created_at')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.status'))
                    ->options(ViewStatus::class),
                TernaryFilter::make('is_public')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.public')),
                TernaryFilter::make('is_global_favorite')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.global_favorite')),
                SelectFilter::make('user')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.columns.user'))
                    ->relationship('user', $nameAttribute)
                    ->searchable()
                    ->preload(static::plugin()->shouldResourceLoadAllUsers()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('filament-dynamic-tables::dynamic-tables.resource.actions.open'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (UserView $record): ?string => static::getOpenUrl($record))
                    ->visible(fn (UserView $record): bool => filled(static::getOpenUrl($record))),
                static::makeStatusAction('approve', ViewStatus::Approved, 'heroicon-m-check-circle', 'success'),
                static::makeStatusAction('reject', ViewStatus::Rejected, 'heroicon-m-x-circle', 'danger'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::makeStatusBulkAction('approve', ViewStatus::Approved, 'heroicon-m-check-circle', 'success'),
                    static::makeStatusBulkAction('reject', ViewStatus::Rejected, 'heroicon-m-x-circle', 'danger'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function makeStatusAction(string $name, ViewStatus $status, string $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__("filament-dynamic-tables::dynamic-tables.resource.actions.{$name}"))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (UserView $record): bool => ($record->status !== $status) && ($record->is_public || $record->is_global_favorite || ($record->status !== ViewStatus::Approved)))
            ->authorize(fn (UserView $record): bool => static::canApprove($record))
            ->action(fn (UserView $record) => $record->update(['status' => $status]));
    }

    /**
     * A link to the view's table with the view applied, for views of the
     * current panel.
     */
    public static function getOpenUrl(UserView $record): ?string
    {
        if (blank($record->url)) {
            return null;
        }

        try {
            $panel = Filament::getCurrentPanel()?->getId();
        } catch (\Throwable) {
            $panel = null;
        }

        if (filled($record->panel_id) && filled($panel) && ($record->panel_id !== $panel)) {
            return null;
        }

        $url = (string) str($record->url)->before('?');

        return $url . '?' . http_build_query(['table_view' => $record->getKeyString()]);
    }

    protected static function makeToggleAction(string $attribute): Action
    {
        return Action::make("toggle_{$attribute}")
            ->authorize(fn (UserView $record): bool => static::canToggle($record, $attribute))
            ->action(function (UserView $record) use ($attribute): void {
                if (! static::canToggle($record, $attribute)) {
                    return;
                }

                $record->update([$attribute => ! $record->{$attribute}]);
            });
    }

    protected static function canToggle(UserView $record, string $attribute): bool
    {
        $user = Users::current() ?? Filament::auth()->user();

        if (! $user) {
            return false;
        }

        $gate = Gate::forUser($user);

        return $gate->allows('update', $record)
            && $gate->allows($attribute === 'is_public' ? 'makePublic' : 'makeGlobalFavorite', $record);
    }

    protected static function makeStatusBulkAction(string $name, ViewStatus $status, string $icon, string $color): BulkAction
    {
        return BulkAction::make($name)
            ->label(__("filament-dynamic-tables::dynamic-tables.resource.actions.{$name}"))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->authorize(fn (): bool => static::canApprove())
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => $records
                ->filter(fn (UserView $record): bool => static::canApprove($record))
                ->each(fn (UserView $record) => $record->update(['status' => $status])));
    }

    protected static function canApprove(?UserView $record = null): bool
    {
        $user = Users::current() ?? Filament::auth()->user();

        return $user && Gate::forUser($user)->allows('approve', $record ?? static::getModel());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserViews::route('/'),
            'edit' => Pages\EditUserView::route('/{record}/edit'),
        ];
    }
}
