@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;
    use KaraOdin\FilamentDynamicTables\View\DynamicTablesIconAlias as Icon;

    if (! $livewire->isAdvancedSearchActive()) {
        return;
    }

    $table = $livewire->getTable();
    $groups = $livewire->getAdvancedSearchGroups();
    $constraints = $livewire->getAdvancedSearchConstraintOptions();
    $defaultConstraint = $config->getAdvancedSearchDefaultConstraint();
    $hasColumns = $config->isAdvancedSearchColumnSelectionEnabled();
    $columns = $hasColumns ? $livewire->getAdvancedSearchableColumns() : [];
    $extraColumns = $hasColumns ? $livewire->getAdvancedSearchExtraColumns() : [];
    $hasGrouping = $config->isAdvancedSearchGroupingEnabled();
    $hasSyntax = $config->isAdvancedSearchKeyboardSyntaxEnabled();
    $hasBooleanSyntax = $config->isAdvancedSearchBooleanSyntaxEnabled();
    $columnsFirst = $config->isAdvancedSearchDropdownColumnsFirst();
    $placeholder = $table->getSearchPlaceholder() ?? __('filament-tables::table.fields.search.placeholder');

    $constraintLabels = collect($constraints)->mapWithKeys(fn ($constraint, $value) => [$value => $constraint->getLabel()])->all();
    $columnLabels = [...$columns, ...$extraColumns];

    $sections = $columnsFirst ? ['columns', 'database', 'constraints', 'boolean'] : ['constraints', 'columns', 'database', 'boolean'];

    $modifier = $config->getModifyAdvancedSearchActionUsing();
@endphp

<div
    class="fdt-advanced-search"
    wire:key="{{ $livewire->getId() }}.dynamic-tables.advanced-search.{{ md5(json_encode($groups)) }}"
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('advanced-search', 'karaodin/filament-dynamic-tables') }}"
    x-data="advancedSearch({
        groups: {{ Js::from($groups) }},
        defaultConstraint: {{ Js::from($defaultConstraint->value) }},
        maxGroups: {{ Js::from($config->getAdvancedSearchMaxGroups()) }},
        hasGrouping: {{ Js::from($hasGrouping) }},
        openOnFocus: {{ Js::from($config->doesAdvancedSearchDropdownOpenOnFocus()) }},
        keybindings: {{ Js::from($config->getAdvancedSearchKeybindings()) }},
    })"
    x-on:keydown.window="handleKeybinding($event)"
    x-on:click.outside="open = false; isInfoOpen = false"
    x-on:keydown.escape.prevent.stop="open = false; isInfoOpen = false"
>
    <div class="fdt-as-field" x-on:click="focus()">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass" class="fdt-as-search-icon" />

        <div class="fdt-as-tokens">
            <template x-for="(group, index) in groups" :key="index">
                <span class="fdt-as-group" x-bind:class="{ 'fdt-as-group-active': index === active }">
                    <template x-if="index > 0">
                        <button
                            type="button"
                            class="fdt-as-badge fdt-as-badge-operator"
                            x-on:click.stop="toggleOperator(index)"
                            x-text="group.operator === 'or' ? {{ Js::from(__('filament-dynamic-tables::dynamic-tables.advanced_search.operators.or')) }} : {{ Js::from(__('filament-dynamic-tables::dynamic-tables.advanced_search.operators.and')) }}"
                            title="{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.toggle_operator') }}"
                        ></button>
                    </template>

                    <template x-if="! isDefaultConstraint(group.constraint)">
                        <span class="fdt-as-badge fdt-as-badge-constraint" x-text="{{ Js::from($constraintLabels) }}[group.constraint] ?? group.constraint"></span>
                    </template>

                    <template x-for="column in group.columns" :key="column">
                        <span class="fdt-as-badge fdt-as-badge-column">
                            <span x-text="{{ Js::from($columnLabels) }}[column] ?? column"></span>
                            <button type="button" class="fdt-as-badge-remove" x-on:click.stop="removeColumn(index, column)" aria-label="{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.remove') }}">×</button>
                        </span>
                    </template>

                    <template x-if="index !== active">
                        <button type="button" class="fdt-as-value" x-on:click.stop="active = index; focus()" x-text="group.value"></button>
                    </template>
                </span>
            </template>

            <input
                x-ref="input"
                type="search"
                autocomplete="off"
                class="fdt-as-input"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $placeholder }}"
                x-model="current.value"
                x-on:focus="onFocus()"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="enter()"
                x-on:keydown.backspace="backspace($event)"
                x-on:input="if (open) highlighted = -1"
            />
        </div>

        <div class="fdt-as-field-actions">
            <x-filament::loading-indicator class="fdt-as-loading" wire:loading.delay.default="" wire:target="setAdvancedSearch,resetAdvancedSearch" />

            <button
                type="button"
                class="fdt-as-icon-button"
                x-show="hasSearch"
                x-cloak
                x-on:click.stop="clear()"
                title="{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.clear') }}"
            >
                <x-filament::icon :icon="Icon::resolve(Icon::ADVANCED_SEARCH_CLEAR)" class="fdt-as-icon" />
            </button>

            <button
                type="button"
                class="fdt-as-icon-button"
                x-on:click.stop="isInfoOpen = ! isInfoOpen; open = false"
                title="{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.reference') }}"
            >
                <x-filament::icon icon="heroicon-m-information-circle" class="fdt-as-icon" />
            </button>

            <button
                type="button"
                class="fdt-as-icon-button fdt-as-trigger"
                x-on:click.stop="open = ! open; isInfoOpen = false; focus()"
                title="{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.label') }}"
            >
                <x-filament::icon :icon="Icon::resolve(Icon::ADVANCED_SEARCH_CONSTRAINT)" class="fdt-as-icon" />
            </button>
        </div>
    </div>

    <div class="fdt-as-menu fdt-panel" x-ref="menu" x-show="open" x-cloak x-transition.opacity.duration.100ms>
        @foreach ($sections as $section)
            @if ($section === 'constraints')
                <div class="fdt-as-section">
                    <p class="fdt-as-section-heading">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.sections.constraints') }}</p>

                    @foreach ($constraints as $value => $constraint)
                        <button
                            type="button"
                            data-fdt-as-item
                            class="fdt-as-item"
                            x-bind:class="{ 'fdt-as-item-highlighted': isHighlighted($el), 'fdt-as-item-selected': (current.constraint ?? {{ Js::from($defaultConstraint->value) }}) === {{ Js::from($value) }} }"
                            x-on:click.stop="selectConstraint({{ Js::from($value) }})"
                        >
                            <x-filament::icon :icon="Icon::resolve(Icon::ADVANCED_SEARCH_CONSTRAINT)" class="fdt-as-item-icon" />
                            <span class="fdt-as-item-label">{{ $constraint->getLabel() }}</span>
                            @if ($hasSyntax && filled($constraint->syntax()))
                                <kbd class="fdt-as-kbd">{{ $constraint->syntax() }}</kbd>
                            @endif
                        </button>
                    @endforeach
                </div>
            @elseif (($section === 'columns') && filled($columns))
                <div class="fdt-as-section">
                    <p class="fdt-as-section-heading">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.sections.columns') }}</p>

                    @foreach ($columns as $value => $label)
                        <button
                            type="button"
                            data-fdt-as-item
                            class="fdt-as-item"
                            x-bind:class="{ 'fdt-as-item-highlighted': isHighlighted($el), 'fdt-as-item-selected': current.columns.includes({{ Js::from($value) }}) }"
                            x-on:click.stop="toggleColumn({{ Js::from($value) }})"
                        >
                            <x-filament::icon :icon="Icon::resolve(Icon::ADVANCED_SEARCH_COLUMN)" class="fdt-as-item-icon" />
                            <span class="fdt-as-item-label">{{ $label }}</span>
                            <x-filament::icon icon="heroicon-m-check" class="fdt-as-item-check" x-show="current.columns.includes({{ Js::from($value) }})" />
                        </button>
                    @endforeach
                </div>
            @elseif (($section === 'database') && filled($extraColumns))
                <div class="fdt-as-section">
                    <p class="fdt-as-section-heading">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.sections.database') }}</p>

                    @foreach ($extraColumns as $value => $label)
                        <button
                            type="button"
                            data-fdt-as-item
                            class="fdt-as-item"
                            x-bind:class="{ 'fdt-as-item-highlighted': isHighlighted($el), 'fdt-as-item-selected': current.columns.includes({{ Js::from($value) }}) }"
                            x-on:click.stop="toggleColumn({{ Js::from($value) }})"
                        >
                            <x-filament::icon :icon="Icon::resolve(Icon::ADVANCED_SEARCH_EXTRA_COLUMN)" class="fdt-as-item-icon" />
                            <span class="fdt-as-item-label">{{ $label }}</span>
                            <x-filament::icon icon="heroicon-m-check" class="fdt-as-item-check" x-show="current.columns.includes({{ Js::from($value) }})" />
                        </button>
                    @endforeach
                </div>
            @elseif (($section === 'boolean') && $hasGrouping)
                <div class="fdt-as-section" x-show="canAddGroup">
                    <p class="fdt-as-section-heading">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.sections.boolean') }}</p>

                    @foreach (['and' => Icon::ADVANCED_SEARCH_BOOLEAN_AND, 'or' => Icon::ADVANCED_SEARCH_BOOLEAN_OR] as $operator => $operatorIcon)
                        <button
                            type="button"
                            data-fdt-as-item
                            class="fdt-as-item"
                            x-bind:class="{ 'fdt-as-item-highlighted': isHighlighted($el) }"
                            x-on:click.stop="addGroup({{ Js::from($operator) }})"
                        >
                            <x-filament::icon :icon="Icon::resolve($operatorIcon)" class="fdt-as-item-icon" />
                            <span class="fdt-as-item-label">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.operators.' . $operator) }}</span>
                            @if ($hasBooleanSyntax)
                                <kbd class="fdt-as-kbd">{{ $operator === 'and' ? '&' : '|' }}</kbd>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>

    <div class="fdt-as-menu fdt-as-reference fdt-panel" x-show="isInfoOpen" x-cloak x-transition.opacity.duration.100ms>
        <p class="fdt-as-section-heading">{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.reference') }}</p>

        <table class="fdt-as-reference-table">
            <tbody>
                @if ($hasSyntax)
                    @foreach ($constraints as $constraint)
                        <tr>
                            <td>{{ $constraint->getLabel() }}</td>
                            <td><kbd class="fdt-as-kbd">{{ filled($constraint->syntax()) ? $constraint->syntax() . 'term' : 'term' }}</kbd></td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td>{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.reference_phrase') }}</td>
                    <td><kbd class="fdt-as-kbd">"john doe"</kbd></td>
                </tr>

                @if ($hasBooleanSyntax)
                    <tr>
                        <td>{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.operators.and') }}</td>
                        <td><kbd class="fdt-as-kbd">john &amp; gmail</kbd></td>
                    </tr>
                    <tr>
                        <td>{{ __('filament-dynamic-tables::dynamic-tables.advanced_search.operators.or') }}</td>
                        <td><kbd class="fdt-as-kbd">john | jane</kbd></td>
                    </tr>
                @endif
            </tbody>
        </table>

        <p class="fdt-as-reference-keys">
            <kbd class="fdt-as-kbd">↓</kbd> {{ __('filament-dynamic-tables::dynamic-tables.advanced_search.keys.open') }}
            · <kbd class="fdt-as-kbd">↑</kbd><kbd class="fdt-as-kbd">↓</kbd> {{ __('filament-dynamic-tables::dynamic-tables.advanced_search.keys.navigate') }}
            · <kbd class="fdt-as-kbd">↵</kbd> {{ __('filament-dynamic-tables::dynamic-tables.advanced_search.keys.select') }}
            · <kbd class="fdt-as-kbd">⌫</kbd> {{ __('filament-dynamic-tables::dynamic-tables.advanced_search.keys.remove') }}
        </p>
    </div>
</div>
