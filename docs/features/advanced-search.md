# Advanced Search

Advanced search replaces the table's search field with a search input that supports constraints, column selection and AND / OR groups.

Advanced search is disabled by default:

```php
DynamicTablesPlugin::make()->advancedSearchEnabled()
```

Tables that use `->searchUsing()` keep Filament's search field.

## Using the search

Press the down arrow key or click the input to open the dropdown. It has four sections:

| Section | Content |
| --- | --- |
| Constraints | The search constraints, for example "Starts with". |
| Columns | The table's searchable columns. |
| Database | Extra database columns passed to the table's `->searchable([...])`. |
| Boolean | AND and OR, when grouping is enabled. |

Selections are shown as badges in the input: gray for the constraint, blue for columns, yellow for operators. Click an operator badge to switch between AND and OR.

| Key | Action |
| --- | --- |
| Up, Down | Move through the dropdown. |
| Enter | Select the highlighted item, or run the search. |
| Backspace | Remove the last badge when the input is empty. |
| Escape | Close the dropdown. |

The information icon shows a syntax reference, and the clear icon removes the search.

## Constraints

| Constraint | Shortcut | Matches |
| --- | --- | --- |
| Contains | | records containing every word of the term |
| Does not contain | `-` | records missing at least one word |
| Equals | `=` | the whole value |
| Does not equal | `-=` | |
| Starts with | `^` | |
| Does not start with | `-^` | |
| Ends with | `$` | |
| Does not end with | `-$` | |

Type a shortcut before the term, for example `^pro` or `-=cancelled`.

- Text in double quotes is searched as one phrase. Tables with `splitSearchTerms(false)` always search the whole term.
- Negative constraints keep records without a value, and, on relationship columns, records without related records.
- **Equals** compares dates by day and numbers by value. Dates can be written as `2025-01-15`, `2025/01/15`, `2025.01.15`, in the table's date format, or with a time, `2025-01-15 10:30:00`. The number `5` matches `5.00`. Other constraints never match date or number columns.

## Groups

With grouping enabled, users combine several searches. AND narrows the results, OR widens them:

```
(name contains John AND email contains gmail) OR (status equals active)
```

With the boolean syntax enabled, typing `&` or `|` starts a new group, for example `^pro & =apple`.

## Options

```php
use KaraOdin\FilamentDynamicTables\Enums\SearchConstraint;

DynamicTablesPlugin::make()
    ->advancedSearchEnabled()
    ->advancedSearchColumnsEnabled()
    ->advancedSearchGroupingEnabled()
    ->advancedSearchMaxGroups(3)
    ->advancedSearchBooleanSyntaxEnabled()
    ->advancedSearchConstraintSyntaxEnabled(false)
    ->advancedSearchMaxWords(5)
    ->advancedSearchDropdownColumnsFirst()
    ->advancedSearchDropdownOpenOnFocus()
    ->advancedSearchKeybindings(['command+k', 'ctrl+k'])
    ->advancedSearchDefaultConstraint(SearchConstraint::StartsWith)
    ->advancedSearchConstraints([SearchConstraint::Contains, SearchConstraint::Equals])
```

| Method | Default | Description |
| --- | --- | --- |
| `advancedSearchEnabled()` | `false` | Enables advanced search. |
| `advancedSearchColumnsEnabled()` | `false` | Lets users choose the columns to search. |
| `advancedSearchGroupingEnabled()` | `false` | Enables AND / OR groups. |
| `advancedSearchMaxGroups()` | `5` | The maximum number of groups. |
| `advancedSearchBooleanSyntaxEnabled()` | `false` | Starts a group when `&` or `|` is typed. |
| `advancedSearchConstraintSyntaxEnabled()` | `true` | Enables the `-` `=` `^` `$` shortcuts. |
| `advancedSearchMaxWords()` | `null` | Limits the number of words in a term. |
| `advancedSearchDropdownColumnsFirst()` | `false` | Lists columns before constraints. |
| `advancedSearchDropdownOpenOnFocus()` | `false` | Opens the dropdown when the input is focused. |
| `advancedSearchKeybindings()` | `[]` | Keyboard shortcuts that focus the input. |
| `advancedSearchDefaultConstraint()` | `Contains` | The constraint used when none is selected. |
| `advancedSearchConstraints()` | all eight | The constraints offered. |

Disable advanced search for a single table:

```php
public static function advancedSearchIsEnabled(): bool
{
    return false;
}
```

## Custom search queries

Columns with a custom search query receive the selected constraint as `$constraint`. The constraint passed to the query is always positive; negation is applied around the query.

```php
TextColumn::make('full_name')->searchable(query: function (Builder $query, string $search, $constraint = null) {
    [$operator, $value] = $constraint?->getOperatorAndValue($search) ?? ['like', "%{$search}%"];

    return $query->whereRaw("concat(first_name, ' ', last_name) {$operator} ?", [$value]);
});
```

Do not type-hint `$constraint`. Filament also calls the query outside advanced search, without a constraint.

## State and URL binding

The search is stored in the public `$advancedSearch` property as a list of groups:

```php
[
    ['columns' => [], 'constraint' => 'contains', 'value' => 'john', 'operator' => null],
    ['columns' => ['status'], 'constraint' => 'equals', 'value' => 'active', 'operator' => 'or'],
]
```

On List and Manage Related Records pages it is bound to the URL as `?advanced_search`. Override `shouldBindAdvancedSearchToUrl()` on the page to change that.

For compatibility:

- a plain `?search=` query parameter
- Filament's `searchTable()` test helper
- views saved before advanced search was enabled

all still work and are treated as "contains".

Groups are saved in user views. Preset views accept them in `defaultSearch()`:

```php
PresetView::make('Apple products')->defaultSearch([
    ['columns' => ['brand.name'], 'constraint' => 'equals', 'value' => 'Apple'],
])
```

## Page widgets

Page widgets that read the table through `InteractsWithPageTable` should use the package's version of the trait, and the page should use `ExposesTableToWidgets`. The widgets then apply the advanced search, multi-sort and active preset view.

```php
use KaraOdin\FilamentDynamicTables\Widgets\Concerns\ExposesTableToWidgets;
use KaraOdin\FilamentDynamicTables\Widgets\Concerns\InteractsWithPageTable;

class ListProducts extends ListRecords
{
    use DynamicTables;
    use ExposesTableToWidgets;
}

class ProductStats extends StatsOverviewWidget
{
    use InteractsWithPageTable;
}
```
