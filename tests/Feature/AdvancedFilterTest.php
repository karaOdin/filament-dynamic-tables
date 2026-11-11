<?php

use KaraOdin\FilamentDynamicTables\Filters\AdvancedFilter;
use KaraOdin\FilamentDynamicTables\Filters\BooleanFilter;
use KaraOdin\FilamentDynamicTables\Filters\DateFilter;
use KaraOdin\FilamentDynamicTables\Filters\NumericFilter;
use KaraOdin\FilamentDynamicTables\Filters\Operators\DateOperator;
use KaraOdin\FilamentDynamicTables\Filters\Operators\NumericOperator;
use KaraOdin\FilamentDynamicTables\Filters\Operators\TextOperator;
use KaraOdin\FilamentDynamicTables\Filters\SelectFilter;
use KaraOdin\FilamentDynamicTables\Filters\TextFilter;
use KaraOdin\FilamentDynamicTables\Filters\WrappedFilter;
use KaraOdin\FilamentDynamicTables\Models\UserView;
use KaraOdin\FilamentDynamicTables\Support\Models;
use Workbench\App\Enums\Category;
use Workbench\App\Filament\Resources\Products\Pages\ListProducts;
use Workbench\App\Models\Brand;
use Workbench\App\Models\Product;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsUser();
});

function builderState(array $groups): array
{
    $state = [];

    foreach ($groups as $g => $filters) {
        $rules = [];

        foreach ($filters as $r => [$type, $data]) {
            $rules["r{$g}{$r}"] = ['type' => $type, 'data' => $data];
        }

        $state["g{$g}"] = ['filters' => $rules];
    }

    return ['or_groups' => $state];
}

it('generates a filter for each column', function () {
    /** @var AdvancedFilter $filter */
    $filter = livewire(ListProducts::class)->instance()->getTable()->getFilter('advanced_filter_builder');

    $filters = $filter->getBuilderFilters();

    expect($filters['name'])->toBeInstanceOf(TextFilter::class)
        ->and($filters['sku'])->toBeInstanceOf(TextFilter::class)
        ->and($filters['category'])->toBeInstanceOf(SelectFilter::class)
        ->and($filters['price'])->toBeInstanceOf(NumericFilter::class)
        ->and($filters['stock'])->toBeInstanceOf(NumericFilter::class)
        ->and($filters['is_active'])->toBeInstanceOf(BooleanFilter::class)
        ->and($filters['released_at'])->toBeInstanceOf(DateFilter::class)
        ->and($filters['brand.name'])->toBeInstanceOf(TextFilter::class)
        ->and($filters['brand.name']->hasOptions())->toBeTrue()
        ->and($filters['discontinued'])->toBeInstanceOf(WrappedFilter::class)
        ->and($filters['price']->getIcon())->toBe('heroicon-o-currency-dollar')
        ->and($filters['category']->getOptions())->toHaveKey(Category::Electronics->value);
});

it('shows the default filters in the first group', function () {
    $state = livewire(ListProducts::class)->get('tableFilters.advanced_filter_builder');

    expect($state['or_groups'])->toHaveCount(1)
        ->and(collect(collect($state['or_groups'])->first()['filters'])->pluck('type')->all())->toBe(['category']);
});

it('filters with text operators', function () {
    $phone = Product::factory()->create(['name' => 'Smart phone']);
    $laptop = Product::factory()->create(['name' => 'Gaming laptop']);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['name', ['operator' => TextOperator::CONTAINS, 'value' => 'phone']]]]))
        ->assertCanSeeTableRecords([$phone])
        ->assertCanNotSeeTableRecords([$laptop])
        ->set('tableFilters.advanced_filter_builder', builderState([[['name', ['operator' => TextOperator::DOES_NOT_START_WITH, 'value' => 'smart']]]]))
        ->assertCanSeeTableRecords([$laptop])
        ->assertCanNotSeeTableRecords([$phone])
        ->set('tableFilters.advanced_filter_builder', builderState([[['name', ['operator' => TextOperator::ENDS_WITH, 'value' => 'LAPTOP']]]]))
        ->assertCanSeeTableRecords([$laptop])
        ->assertCanNotSeeTableRecords([$phone]);
});

it('escapes like wildcards', function () {
    $percent = Product::factory()->create(['name' => '100% cotton']);
    $other = Product::factory()->create(['name' => '1000 cotton']);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['name', ['operator' => TextOperator::CONTAINS, 'value' => '0%']]]]))
        ->assertCanSeeTableRecords([$percent])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters with numeric operators', function () {
    $cheap = Product::factory()->create(['price' => 10]);
    $mid = Product::factory()->create(['price' => 150]);
    $expensive = Product::factory()->create(['price' => 500]);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 100]]]]))
        ->assertCanSeeTableRecords([$mid, $expensive])
        ->assertCanNotSeeTableRecords([$cheap])
        ->set('tableFilters.advanced_filter_builder', builderState([[['price', ['operator' => NumericOperator::BETWEEN, 'value_start' => 100, 'value_end' => 200]]]]))
        ->assertCanSeeTableRecords([$mid])
        ->assertCanNotSeeTableRecords([$cheap, $expensive])
        ->set('tableFilters.advanced_filter_builder', builderState([[['price', ['operator' => NumericOperator::NOT_BETWEEN, 'value_start' => 100, 'value_end' => 200]]]]))
        ->assertCanSeeTableRecords([$cheap, $expensive])
        ->assertCanNotSeeTableRecords([$mid]);
});

it('filters with date operators', function () {
    $recent = Product::factory()->create(['released_at' => now()->subDays(3)]);
    $old = Product::factory()->create(['released_at' => now()->subYears(2)]);
    $none = Product::factory()->create(['released_at' => null]);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['released_at', ['operator' => DateOperator::IN_THE_LAST, 'value' => 1, 'unit' => 'weeks']]]]))
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old, $none])
        ->set('tableFilters.advanced_filter_builder', builderState([[['released_at', ['operator' => DateOperator::BEFORE, 'date' => now()->subYear()->toDateString()]]]]))
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$recent, $none])
        ->set('tableFilters.advanced_filter_builder', builderState([[['released_at', ['operator' => DateOperator::IS_EMPTY]]]]))
        ->assertCanSeeTableRecords([$none])
        ->assertCanNotSeeTableRecords([$recent, $old]);
});

it('filters with select, boolean and relationship filters', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    $acmeActive = Product::factory()->for($brand)->create(['category' => Category::Electronics, 'is_active' => true]);
    $otherInactive = Product::factory()->create(['category' => Category::Books, 'is_active' => false]);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['category', ['operator' => TextOperator::IS, 'value' => [Category::Electronics->value]]]]]))
        ->assertCanSeeTableRecords([$acmeActive])
        ->assertCanNotSeeTableRecords([$otherInactive])
        ->set('tableFilters.advanced_filter_builder', builderState([[['is_active', ['operator' => 'is_false']]]]))
        ->assertCanSeeTableRecords([$otherInactive])
        ->assertCanNotSeeTableRecords([$acmeActive])
        ->set('tableFilters.advanced_filter_builder', builderState([[['brand.name', ['operator' => TextOperator::IS_NOT, 'value' => [$brand->getKey()]]]]]))
        ->assertCanSeeTableRecords([$otherInactive])
        ->assertCanNotSeeTableRecords([$acmeActive]);
});

it('combines filters in a group with and, and groups with or', function () {
    $cheapLow = Product::factory()->create(['price' => 10, 'stock' => 1]);
    $cheapHigh = Product::factory()->create(['price' => 10, 'stock' => 100]);
    $expensive = Product::factory()->create(['price' => 500, 'stock' => 100]);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([
            [
                ['price', ['operator' => NumericOperator::LESS_THAN, 'value' => 50]],
                ['stock', ['operator' => NumericOperator::LESS_THAN, 'value' => 5]],
            ],
            [
                ['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 400]],
            ],
        ]))
        ->assertCanSeeTableRecords([$cheapLow, $expensive])
        ->assertCanNotSeeTableRecords([$cheapHigh]);
});

it('uses regular filament filters inside groups', function () {
    $discontinued = Product::factory()->create(['is_active' => false, 'stock' => 0]);
    $active = Product::factory()->create(['is_active' => true, 'stock' => 10]);

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['discontinued', ['isActive' => true]]]]))
        ->assertCanSeeTableRecords([$discontinued])
        ->assertCanNotSeeTableRecords([$active]);
});

it('ignores filters without a value', function () {
    $products = Product::factory()->count(3)->create();

    livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['name', ['operator' => TextOperator::CONTAINS, 'value' => '']]]]))
        ->assertCanSeeTableRecords($products);
});

it('shows one colored indicator per filter with group labels', function () {
    /** @var AdvancedFilter $filter */
    $filter = livewire(ListProducts::class)->instance()->getTable()->getFilter('advanced_filter_builder');

    $indicators = $filter->getGroupIndicators(builderState([
        [['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 100]]],
        [['name', ['operator' => TextOperator::CONTAINS, 'value' => 'phone']]],
    ]));

    expect($indicators)->toHaveCount(2)
        ->and(array_keys($indicators))->toBe(['g0.r00', 'g1.r10'])
        ->and((string) $indicators['g0.r00']->getLabel())->toBe('Filter group 1: Price greater than 100')
        ->and((string) $indicators['g1.r10']->getLabel())->toBe('Filter group 2: Name contains "phone"')
        ->and($indicators['g0.r00']->getColor())->toBe('primary')
        ->and($indicators['g1.r10']->getColor())->toBe('info');

    $single = $filter->getGroupIndicators(builderState([[['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 100]]]]));

    expect((string) collect($single)->first()->getLabel())->toBe('Price greater than 100');
});

it('removes a single filter from its indicator', function () {
    $cheap = Product::factory()->create(['price' => 10, 'name' => 'Phone']);
    $expensive = Product::factory()->create(['price' => 500, 'name' => 'Phone pro']);

    $component = livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[
            ['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 100]],
            ['name', ['operator' => TextOperator::CONTAINS, 'value' => 'phone']],
        ]]))
        ->assertCanSeeTableRecords([$expensive])
        ->assertCanNotSeeTableRecords([$cheap])
        ->call('removeTableFilter', 'advanced_filter_builder', 'g0.r00')
        ->assertCanSeeTableRecords([$cheap, $expensive]);

    // The repeater generates new item keys when it is filled.
    $rules = collect($component->get('tableFilters.advanced_filter_builder.or_groups'))->first()['filters'];

    expect($rules)->toHaveCount(1)->and(collect($rules)->first()['type'])->toBe('name');
});

it('saves the builder in a user view and restores it', function () {
    $cheap = Product::factory()->create(['price' => 10]);
    $expensive = Product::factory()->create(['price' => 500]);

    $component = livewire(ListProducts::class)
        ->set('tableFilters.advanced_filter_builder', builderState([[['price', ['operator' => NumericOperator::GREATER_THAN, 'value' => 100]]]]))
        ->callAction('dynamicTablesSaveView', data: ['name' => 'Expensive'])
        ->assertHasNoActionErrors();

    /** @var UserView $view */
    $view = Models::userView()::query()->where('name', 'Expensive')->firstOrFail();

    expect($view->state['filters']['advanced_filter_builder']['or_groups'])->not->toBeEmpty();

    $component
        ->call('applyDynamicTableView', 'default')
        ->assertCanSeeTableRecords([$cheap, $expensive])
        ->call('applyDynamicTableView', 'user:' . $view->getKey())
        ->assertCanSeeTableRecords([$expensive])
        ->assertCanNotSeeTableRecords([$cheap])
        ->assertSet('activeDynamicTableView', 'user:' . $view->getKey());

    expect($component->instance()->isActiveDynamicTableViewModified())->toBeFalse();
});

it('accepts the short form in preset view default filters', function () {
    /** @var AdvancedFilter $filter */
    $filter = livewire(ListProducts::class)->instance()->getTable()->getFilter('advanced_filter_builder');

    $state = $filter->normalizeState([
        ['category' => ['value' => 'electronics'], 'price' => ['operator' => 'greater_than', 'value' => 10]],
        ['category' => ['value' => 'books']],
    ]);

    expect($state['or_groups'])->toHaveCount(2)
        ->and($filter->getComparableState($state))->toBe([
            [
                ['type' => 'category', 'data' => ['value' => 'electronics']],
                ['type' => 'price', 'data' => ['operator' => 'greater_than', 'value' => 10]],
            ],
            [
                ['type' => 'category', 'data' => ['value' => 'books']],
            ],
        ]);
});

it('respects included and excluded columns', function () {
    $table = livewire(ListProducts::class)->instance()->getTable();

    $filter = AdvancedFilter::make()->excludeColumns(['sku', 'brand.name']);
    $filter->table($table);

    expect(array_keys($filter->getBuilderFilters()))->not->toContain('sku')
        ->and(array_keys($filter->getBuilderFilters()))->not->toContain('brand.name')
        ->and(array_keys($filter->getBuilderFilters()))->toContain('name');

    $filter = AdvancedFilter::make()->includeColumns(['name', 'price']);
    $filter->table($table);

    expect(array_keys($filter->getBuilderFilters()))->toBe(['name', 'price']);
});

it('limits operators', function () {
    $filter = TextFilter::make('name')
        ->includeOperators([TextOperator::IS, TextOperator::CONTAINS, TextOperator::STARTS_WITH])
        ->excludeOperators([TextOperator::IS])
        ->defaultOperator(TextOperator::STARTS_WITH);

    expect($filter->getOperators())->toBe([TextOperator::CONTAINS, TextOperator::STARTS_WITH])
        ->and($filter->getDefaultOperator())->toBe(TextOperator::STARTS_WITH);
});

it('renders the builder with the expand view link', function () {
    livewire(ListProducts::class)
        ->assertSeeHtml('fdt-filter-builder')
        ->assertSee(__('filament-dynamic-tables::dynamic-tables.filter_builder.expand_view'))
        ->call('expandDynamicTableFilterBuilder')
        ->assertSet('isDynamicTableFilterBuilderExpanded', true)
        ->assertDontSee(__('filament-dynamic-tables::dynamic-tables.filter_builder.expand_view'));
});
