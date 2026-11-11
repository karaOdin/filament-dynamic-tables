<?php

namespace KaraOdin\FilamentDynamicTables\Components;

use BackedEnum;
use Closure;
use Filament\Support\Components\Component;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A developer-defined view returned from getPresetViews():
 *
 *     'active' => PresetView::make()
 *         ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
 *         ->favorite()
 *         ->icon('heroicon-o-check-circle'),
 */
class PresetView extends Component
{
    protected string $evaluationIdentifier = 'presetView';

    protected ?string $key = null;

    protected string | Htmlable | Closure | null $label = null;

    protected string | BackedEnum | Closure | null $icon = null;

    protected string | BackedEnum | Closure | null $activeIcon = null;

    protected string | array | Closure | null $color = null;

    protected string | Closure | null $tooltip = null;

    protected mixed $badge = null;

    protected string | array | Closure | null $badgeColor = null;

    protected bool | Closure $isFavorite = false;

    protected bool | Closure $isDefault = false;

    protected bool | Closure $isVisible = true;

    protected ?Closure $modifyQueryUsing = null;

    /** @var string | array<string> | Closure | null */
    protected string | array | Closure | null $indicator = null;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $defaultFavoriteFilters = null;

    /** @var array<string, mixed> | Closure | null */
    protected array | Closure | null $defaultFilters = null;

    /** @var array<string, string> | Closure | null column => direction */
    protected array | Closure | null $defaultSort = null;

    protected string | Closure | null $defaultGrouping = null;

    protected string | Closure | null $defaultGroupingDirection = null;

    /** @var string | array<int, array<string, mixed>> | Closure | null */
    protected string | array | Closure | null $defaultSearch = null;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $visibleColumns = null;

    /** @var array<string> | Closure | null */
    protected array | Closure | null $columnOrder = null;

    protected bool | Closure $preserveFilters = false;

    protected bool | Closure $preserveSearch = false;

    protected bool | Closure $preserveSort = false;

    protected bool | Closure $preserveGrouping = false;

    protected bool | Closure $preserveColumns = false;

    protected ?HasTable $livewire = null;

    final public function __construct(string | Htmlable | Closure | null $label = null)
    {
        $this->label($label);
    }

    public static function make(string | Htmlable | Closure | null $label = null): static
    {
        $static = app(static::class, ['label' => $label]);
        $static->configure();

        return $static;
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function livewire(?HasTable $livewire): static
    {
        $this->livewire = $livewire;

        return $this;
    }

    public function getLivewire(): ?HasTable
    {
        return $this->livewire;
    }

    public function getTable(): ?Table
    {
        return $this->livewire?->getTable();
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function label(string | Htmlable | Closure | null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function hasCustomLabel(): bool
    {
        return filled($this->evaluate($this->label));
    }

    public function getLabel(): string | Htmlable
    {
        return $this->evaluate($this->label) ?? (string) str($this->key ?? '')->headline();
    }

    public function icon(string | BackedEnum | Closure | null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): string | BackedEnum | null
    {
        return $this->evaluate($this->icon);
    }

    public function activeIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->activeIcon = $icon;

        return $this;
    }

    public function getActiveIcon(): string | BackedEnum | null
    {
        return $this->evaluate($this->activeIcon) ?? $this->getIcon();
    }

    public function color(string | array | Closure | null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string | array | null
    {
        return $this->evaluate($this->color);
    }

    public function tooltip(string | Closure | null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function getTooltip(): ?string
    {
        return $this->evaluate($this->tooltip);
    }

    /**
     * A static value, or a closure. The closure can receive `$query`: the
     * table query with this view's query modification applied, so
     * ->badge(fn (Builder $query) => $query->count()) shows a live count.
     */
    public function badge(mixed $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function hasBadge(): bool
    {
        return $this->badge !== null;
    }

    public function getBadge(?Builder $query = null): mixed
    {
        return $this->evaluate($this->badge, [
            'query' => $query ? $this->modifyQuery($query) : null,
        ]);
    }

    public function badgeColor(string | array | Closure | null $color): static
    {
        $this->badgeColor = $color;

        return $this;
    }

    public function getBadgeColor(): string | array | null
    {
        return $this->evaluate($this->badgeColor);
    }

    /*
    |--------------------------------------------------------------------------
    | Behaviour
    |--------------------------------------------------------------------------
    */

    /**
     * Show the view in the favorites bar by default. Users can still remove it.
     */
    public function favorite(bool | Closure $condition = true): static
    {
        $this->isFavorite = $condition;

        return $this;
    }

    public function isFavorite(): bool
    {
        return (bool) $this->evaluate($this->isFavorite);
    }

    /**
     * Apply this view when the table loads without any other state.
     */
    public function default(bool | Closure $condition = true): static
    {
        $this->isDefault = $condition;

        return $this;
    }

    public function isDefault(): bool
    {
        return (bool) $this->evaluate($this->isDefault);
    }

    public function visible(bool | Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function hidden(bool | Closure $condition = true): static
    {
        $this->isVisible = $condition instanceof Closure
            ? fn (): bool => ! $this->evaluate($condition)
            : ! $condition;

        return $this;
    }

    public function isVisible(): bool
    {
        return (bool) $this->evaluate($this->isVisible);
    }

    public function isHidden(): bool
    {
        return ! $this->isVisible();
    }

    /**
     * A closure receiving the table query, or a query whose conditions are
     * added to it: ->modifyQueryUsing(Product::query()->where('stock', '<', 5)).
     */
    public function modifyQueryUsing(Closure | Builder | null $callback): static
    {
        if ($callback instanceof Builder) {
            $conditions = $callback;
            $callback = fn (Builder $query): Builder => $query->addNestedWhereQuery($conditions->getQuery());
        }

        $this->modifyQueryUsing = $callback;

        return $this;
    }

    /**
     * Alias of modifyQueryUsing().
     */
    public function query(Closure | Builder | null $callback): static
    {
        return $this->modifyQueryUsing($callback);
    }

    public function hasQueryModification(): bool
    {
        return $this->modifyQueryUsing !== null;
    }

    public function modifyQuery(Builder $query): Builder
    {
        if (! $this->modifyQueryUsing) {
            return $query;
        }

        return $this->evaluate($this->modifyQueryUsing, [
            'query' => $query,
        ]) ?? $query;
    }

    /**
     * @param  array<string, mixed> | Closure  $filters  filter name => filter form state
     */
    public function defaultFilters(array | Closure $filters): static
    {
        $this->defaultFilters = $filters;

        return $this;
    }

    /**
     * @return array<string, mixed> | null
     */
    public function getDefaultFilters(): ?array
    {
        return $this->evaluate($this->defaultFilters);
    }

    /**
     * One column, or several in priority order: ->defaultSort(['is_visible' => 'desc', 'price' => 'asc']).
     *
     * @param  string | array<string, string> | Closure  $column
     */
    public function defaultSort(string | array | Closure $column, string $direction = 'asc'): static
    {
        $this->defaultSort = ($column instanceof Closure || is_array($column)) ? $column : [$column => $direction];

        return $this;
    }

    /**
     * @param  array<string, string> | Closure  $sorts  column => direction, in priority order
     */
    public function defaultMultiSort(array | Closure $sorts): static
    {
        $this->defaultSort = $sorts;

        return $this;
    }

    /**
     * @return array<string, string> | null
     */
    public function getDefaultSort(): ?array
    {
        $sort = $this->evaluate($this->defaultSort);

        if (is_string($sort)) {
            return [$sort => 'asc'];
        }

        return $sort;
    }

    public function defaultGrouping(string | Closure | null $group, string | Closure | null $direction = 'asc'): static
    {
        $this->defaultGrouping = $group;
        $this->defaultGroupingDirection = $direction;

        return $this;
    }

    public function getDefaultGrouping(): ?string
    {
        $group = $this->evaluate($this->defaultGrouping);

        if (blank($group)) {
            return null;
        }

        $direction = $this->evaluate($this->defaultGroupingDirection) ?? 'asc';

        return str_contains($group, ':') ? $group : "{$group}:{$direction}";
    }

    /**
     * A search term ("contains" across all columns), or advanced search
     * groups: [['columns' => ['status'], 'constraint' => 'equals', 'value' => 'active', 'operator' => null], ...].
     *
     * @param  string | array<int, array<string, mixed>> | Closure | null  $search
     */
    public function defaultSearch(string | array | Closure | null $search): static
    {
        $this->defaultSearch = $search;

        return $this;
    }

    /**
     * @return string | array<int, array<string, mixed>> | null
     */
    public function getDefaultSearch(): string | array | null
    {
        return $this->evaluate($this->defaultSearch);
    }

    /**
     * Only these toggleable columns are visible while the view is active.
     *
     * @param  array<string> | Closure  $columns
     */
    public function visibleColumns(array | Closure $columns): static
    {
        $this->visibleColumns = $columns;

        return $this;
    }

    /**
     * Alias of visibleColumns().
     *
     * @param  array<string> | Closure  $columns
     */
    public function toggledColumns(array | Closure $columns): static
    {
        return $this->visibleColumns($columns);
    }

    /**
     * @return array<string> | null
     */
    public function getVisibleColumns(): ?array
    {
        return $this->evaluate($this->visibleColumns);
    }

    /**
     * Column order while the view is active. Requires ->reorderableColumns()
     * on the table. Unlisted columns keep their relative order at the end.
     *
     * @param  array<string> | Closure  $columns
     */
    public function columnOrder(array | Closure $columns): static
    {
        $this->columnOrder = $columns;

        return $this;
    }

    /**
     * @return array<string> | null
     */
    public function getColumnOrder(): ?array
    {
        return $this->evaluate($this->columnOrder);
    }

    /**
     * Shows these columns in this order. Unlisted toggleable columns are
     * hidden; unlisted columns that can't be toggled go to the end.
     *
     * @param  array<string> | Closure  $columns
     */
    public function defaultColumns(array | Closure $columns): static
    {
        $this->visibleColumns = $columns;
        $this->columnOrder = $columns;

        return $this;
    }

    /**
     * A note shown in the save view form's summary when a user saves a
     * view based on this preset, describing what the preset's query does.
     *
     * @param  string | array<string> | Closure | null  $indicator
     */
    public function indicator(string | array | Closure | null $indicator): static
    {
        $this->indicator = $indicator;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getIndicators(): array
    {
        return array_values(array_filter((array) $this->evaluate($this->indicator), 'filled'));
    }

    /**
     * Quick filters shown in the indicator bar while this view is active,
     * even when they are not used.
     *
     * @param  array<string> | Closure  $filters
     */
    public function defaultFavoriteFilters(array | Closure $filters): static
    {
        $this->defaultFavoriteFilters = $filters;

        return $this;
    }

    /**
     * @return array<string> | null
     */
    public function getDefaultFavoriteFilters(): ?array
    {
        $filters = $this->evaluate($this->defaultFavoriteFilters);

        return $filters === null ? null : array_values((array) $filters);
    }

    /**
     * Alias of preserveColumns().
     */
    public function preserveToggledColumns(bool | Closure $condition = true): static
    {
        return $this->preserveColumns($condition);
    }

    public function preserveFilters(bool | Closure $condition = true): static
    {
        $this->preserveFilters = $condition;

        return $this;
    }

    public function preserveSearch(bool | Closure $condition = true): static
    {
        $this->preserveSearch = $condition;

        return $this;
    }

    public function preserveSort(bool | Closure $condition = true): static
    {
        $this->preserveSort = $condition;

        return $this;
    }

    public function preserveGrouping(bool | Closure $condition = true): static
    {
        $this->preserveGrouping = $condition;

        return $this;
    }

    public function preserveColumns(bool | Closure $condition = true): static
    {
        $this->preserveColumns = $condition;

        return $this;
    }

    /**
     * Keep the user's current filters, search, sort, grouping and columns
     * when the view is applied; only the query modification changes.
     */
    public function preserveAll(bool | Closure $condition = true): static
    {
        return $this
            ->preserveFilters($condition)
            ->preserveSearch($condition)
            ->preserveSort($condition)
            ->preserveGrouping($condition)
            ->preserveColumns($condition);
    }

    public function shouldPreserveFilters(): bool
    {
        return (bool) $this->evaluate($this->preserveFilters);
    }

    public function shouldPreserveSearch(): bool
    {
        return (bool) $this->evaluate($this->preserveSearch);
    }

    public function shouldPreserveSort(): bool
    {
        return (bool) $this->evaluate($this->preserveSort);
    }

    public function shouldPreserveGrouping(): bool
    {
        return (bool) $this->evaluate($this->preserveGrouping);
    }

    public function shouldPreserveColumns(): bool
    {
        return (bool) $this->evaluate($this->preserveColumns);
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'livewire' => [$this->getLivewire()],
            'table' => [$this->getTable()],
            'key' => [$this->getKey()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
