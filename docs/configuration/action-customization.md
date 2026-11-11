# Action Customization

The quick save, view manager and multi-sort triggers, and the edit view modal, are regular Filament actions. Customize them with a closure that receives the action:

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

DynamicTablesPlugin::make()
    ->quickSaveTriggerAction(fn (Action $action) => $action->label('Save as')->button())
    ->viewManagerTriggerAction(fn (Action $action) => $action->button()->label('My views')->icon(Heroicon::OutlinedSquares2x2))
    ->multiSortTriggerAction(fn (Action $action) => $action->color('primary'))
    ->modifyEditViewActionUsing(fn (Action $action) => $action->slideOver(false))
```

| Method | Action |
| --- | --- |
| `quickSaveTriggerAction()` | The quick save button. |
| `viewManagerTriggerAction()` | The view manager button. |
| `multiSortTriggerAction()` | The multi-sort button. |
| `modifyEditViewActionUsing()` | The edit view modal. |

## Overriding action methods

For full control, override the action methods on the table component:

| Method | Action |
| --- | --- |
| `dynamicTablesSaveViewAction()` | Quick save |
| `dynamicTablesViewManagerAction()` | View manager |
| `dynamicTablesMultiSortAction()` | Multi-sort |
| `dynamicTablesEditViewAction()` | Edit view |
| `dynamicTablesReplaceViewAction()` | Replace view |
| `dynamicTablesDeleteViewAction()` | Delete view |

The methods come from a trait, so alias the original method to build on it:

```php
use Filament\Actions\Action;
use KaraOdin\FilamentDynamicTables\DynamicTables;

class ListProducts extends ListRecords
{
    use DynamicTables {
        dynamicTablesDeleteViewAction as baseDynamicTablesDeleteViewAction;
    }

    public function dynamicTablesDeleteViewAction(): Action
    {
        return $this->baseDynamicTablesDeleteViewAction()
            ->modalDescription('This view will be removed for every user.');
    }
}
```

To change icons without customizing actions, use [icon aliases](icon-aliases.md).
