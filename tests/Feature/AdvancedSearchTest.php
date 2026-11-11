<?php

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use KaraOdin\FilamentDynamicTables\Components\PresetView;
use KaraOdin\FilamentDynamicTables\DynamicTablesPlugin;
use KaraOdin\FilamentDynamicTables\Enums\SearchConstraint;
use KaraOdin\FilamentDynamicTables\Support\Models;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Brand;
use Workbench\App\Models\Product;

beforeEach(function () {
    actingAsUser();

    $this->apple = Product::factory()->create(['name' => 'Apple juice', 'sku' => 'AA-0001', 'price' => 5, 'released_at' => '2025-01-15']);
    $this->pineapple = Product::factory()->create(['name' => 'Pineapple', 'sku' => 'BB-0002', 'price' => 12.5, 'released_at' => '2025-02-01']);
    $this->banana = Product::factory()->create(['name' => 'Banana', 'sku' => 'CC-0003', 'price' => 7, 'released_at' => null]);
});

function group(string $value, ?string $constraint = null, array $columns = [], ?string $operator = null): array
{
    return ['value' => $value, 'constraint' => $constraint, 'columns' => $columns, 'operator' => $operator];
}

function assertVisible($component, array $visible): void
{
    $all = ['apple' => test()->apple, 'pineapple' => test()->pineapple, 'banana' => test()->banana];

    $component
        ->assertCanSeeTableRecords(array_values(array_intersect_key($all, array_flip($visible))))
        ->assertCanNotSeeTableRecords(array_values(array_diff_key($all, array_flip($visible))));
}

it('searches with every constraint', function (string $constraint, string $value, array $visible) {
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group($value, $constraint)]), $visible);
})->with([
    'contains' => [SearchConstraint::Contains->value, 'apple', ['apple', 'pineapple']],
    'does not contain' => [SearchConstraint::DoesNotContain->value, 'apple', ['banana']],
    'starts with' => [SearchConstraint::StartsWith->value, 'apple', ['apple']],
    'does not start with' => [SearchConstraint::DoesNotStartWith->value, 'apple', ['pineapple', 'banana']],
    'ends with' => [SearchConstraint::EndsWith->value, 'apple', ['pineapple']],
    'does not end with' => [SearchConstraint::DoesNotEndWith->value, 'apple', ['apple', 'banana']],
    'equals' => [SearchConstraint::Equals->value, 'banana', ['banana']],
    'does not equal' => [SearchConstraint::DoesNotEqual->value, 'banana', ['apple', 'pineapple']],
]);

it('understands the constraint shortcuts', function (string $search, array $visible) {
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group($search)]), $visible);
})->with([
    'equals' => ['=banana', ['banana']],
    'does not contain' => ['-apple', ['banana']],
    'does not equal' => ['-=banana', ['apple', 'pineapple']],
    'starts with' => ['^apple', ['apple']],
    'does not start with' => ['-^apple', ['pineapple', 'banana']],
    'ends with' => ['$apple', ['pineapple']],
    'does not end with' => ['-$apple', ['apple', 'banana']],
    'quoted phrase' => ['"apple juice"', ['apple']],
]);

it('stores the parsed constraint', function () {
    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('^apple')])
        ->assertSet('advancedSearch', [['columns' => [], 'constraint' => 'starts_with', 'value' => 'apple', 'operator' => null]]);
});

it('keeps numbers that look like shortcuts', function () {
    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('-250')])
        ->assertSet('advancedSearch.0.constraint', 'contains')
        ->assertSet('advancedSearch.0.value', '-250');
});

it('can turn the shortcuts off', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->advancedSearchConstraintSyntaxEnabled(false);
        }
    };

    livewire($component::class)
        ->call('setAdvancedSearch', [group('^apple')])
        ->assertSet('advancedSearch.0.constraint', 'contains')
        ->assertSet('advancedSearch.0.value', '^apple');
});

it('requires every word of a contains search', function () {
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group('juice apple')]), ['apple']);
});

it('excludes records missing a word with does not contain', function () {
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group('apple juice', 'does_not_contain')]), ['pineapple', 'banana']);
});

it('limits the number of words', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->advancedSearchMaxWords(1);
        }
    };

    assertVisible(livewire($component::class)->call('setAdvancedSearch', [group('apple nothing-matches-this')]), ['apple', 'pineapple']);
});

it('searches only the selected columns', function () {
    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('BB-', columns: ['sku'])])
        ->assertCanSeeTableRecords([$this->pineapple])
        ->call('setAdvancedSearch', [group('Banana', columns: ['sku'])])
        ->assertCanNotSeeTableRecords([$this->banana]);
});

it('ignores columns that are not searchable', function () {
    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('apple', columns: ['stock', 'not_a_column'])])
        ->assertSet('advancedSearch.0.columns', []);
});

it('combines groups with and / or', function () {
    // (name contains apple AND sku contains BB) OR (name equals banana)
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [
        group('apple', columns: ['name']),
        group('BB', columns: ['sku'], operator: 'and'),
        group('=banana', operator: 'or'),
    ]), ['pineapple', 'banana']);
});

it('parses the boolean syntax', function () {
    $component = livewire(ListProducts::class)->call('setAdvancedSearch', [group('apple & =pineapple | banana')]);

    $component->assertSet('advancedSearch', [
        ['columns' => [], 'constraint' => 'contains', 'value' => 'apple', 'operator' => null],
        ['columns' => [], 'constraint' => 'equals', 'value' => 'pineapple', 'operator' => 'and'],
        ['columns' => [], 'constraint' => 'contains', 'value' => 'banana', 'operator' => 'or'],
    ]);

    assertVisible($component, ['pineapple', 'banana']);
});

it('limits the number of groups', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->advancedSearchMaxGroups(2);
        }
    };

    livewire($component::class)
        ->call('setAdvancedSearch', [group('a'), group('b', operator: 'or'), group('c', operator: 'or')])
        ->assertCount('advancedSearch', 2);
});

it('compares dates and numbers by value with equals', function () {
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group('=2025-01-15', columns: ['released_at'])]), ['apple']);
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group('=2025/02/01')]), ['pineapple']);
    assertVisible(livewire(ListProducts::class)->call('setAdvancedSearch', [group('=12.50', columns: ['price'])]), ['pineapple']);
});

it('searches relationship columns', function () {
    $brand = Brand::factory()->create(['name' => 'Orchard Co']);
    $product = Product::factory()->for($brand)->create(['name' => 'Cider']);

    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('^orchard')])
        ->assertCanSeeTableRecords([$product])
        ->assertCanNotSeeTableRecords([$this->apple]);
});

it('keeps records without related records with negated constraints', function () {
    $brand = Brand::factory()->create(['name' => 'Orchard Co']);
    $product = Product::factory()->for($brand)->create(['name' => 'Cider']);
    $orphan = Product::factory()->create(['name' => 'Lonely', 'brand_id' => null]);

    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('-orchard')])
        ->assertCanNotSeeTableRecords([$product])
        ->assertCanSeeTableRecords([$this->apple, $this->banana, $orphan]);
});

it('escapes like wildcards in search terms', function () {
    $percent = Product::factory()->create(['name' => '100% cotton']);

    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('%')])
        ->assertCanSeeTableRecords([$percent])
        ->assertCanNotSeeTableRecords([$this->apple]);
});

it('still understands a plain filament search', function () {
    assertVisible(livewire(ListProducts::class)->set('tableSearch', 'apple'), ['apple', 'pineapple']);
    assertVisible(livewire(ListProducts::class)->searchTable('^apple'), ['apple']);
});

it('summarizes the search in the indicator and resets it', function () {
    livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('apple', 'starts_with', ['name']), group('banana', operator: 'or')])
        ->assertSee('Search: Name starts with "apple" OR contains "banana"')
        ->call('resetAdvancedSearch')
        ->assertSet('advancedSearch', [])
        ->assertSet('tableSearch', '');
});

it('saves search groups in user views', function () {
    $component = livewire(ListProducts::class)
        ->call('setAdvancedSearch', [group('^apple')])
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Apples']);

    $view = Models::userView()::query()->where('name', 'Apples')->firstOrFail();

    expect($view->state['advanced_search'][0]['constraint'])->toBe('starts_with');

    $component
        ->call('applyDynamicTableView', 'default')
        ->assertSet('advancedSearch', [])
        ->call('applyDynamicTableView', 'user:' . $view->getKey())
        ->assertSet('advancedSearch.0.value', 'apple');

    assertVisible($component, ['apple']);
});

it('applies preset view search groups', function () {
    $component = new class extends ListProducts
    {
        public function getPresetViews(): array
        {
            return [
                'fruit' => PresetView::make()->defaultSearch([
                    ['columns' => ['name'], 'constraint' => 'starts_with', 'value' => 'pine', 'operator' => null],
                    ['columns' => [], 'constraint' => 'equals', 'value' => 'banana', 'operator' => 'or'],
                ]),
                'plain' => PresetView::make()->defaultSearch('juice'),
            ];
        }
    };

    $instance = livewire($component::class)->call('applyDynamicTableView', 'preset:fruit');
    assertVisible($instance, ['pineapple', 'banana']);

    $instance->call('applyDynamicTableView', 'preset:plain');
    assertVisible($instance, ['apple']);
});

it('passes the constraint to custom search queries', function () {
    $received = new ArrayObject;

    $component = new class extends ListProducts
    {
        public static ?ArrayObject $received = null;

        public function table(Table $table): Table
        {
            return parent::table($table)->columns([
                TextColumn::make('name')->searchable(query: function (Builder $query, string $search, $constraint = null) {
                    static::$received?->append($constraint);
                    [$operator, $value] = $constraint?->getOperatorAndValue($search) ?? ['like', "%{$search}%"];

                    return $query->where('name', $operator, $value);
                }),
            ]);
        }
    };

    $component::$received = $received;

    assertVisible(livewire($component::class)->call('setAdvancedSearch', [group('-^apple')]), ['pineapple', 'banana']);

    expect(collect($received)->last())->toBe(SearchConstraint::StartsWith);
});

it('renders the advanced search input', function () {
    livewire(ListProducts::class)
        ->assertSeeHtml('fdt-advanced-search')
        ->assertSee(__('filament-dynamic-tables::dynamic-tables.advanced_search.sections.constraints'))
        ->assertSee(__('filament-dynamic-tables::dynamic-tables.advanced_search.sections.columns'));
});

it('falls back to the regular search when disabled', function () {
    $component = new class extends ListProducts
    {
        public function configureDynamicTables(DynamicTablesPlugin $config): DynamicTablesPlugin
        {
            return $config->advancedSearch(false);
        }
    };

    livewire($component::class)
        ->assertDontSeeHtml('fdt-advanced-search')
        ->set('tableSearch', '^apple')
        ->assertCanNotSeeTableRecords([$this->apple]);
});
