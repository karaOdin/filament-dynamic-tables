# Testing and the Workbench

## Running the checks

```bash
composer test      # Pest test suite
composer analyse   # PHPStan
composer lint      # Pint, fixes code style
composer test:lint # Pint, checks code style without changes
```

The test suite runs against the installed Filament version. To test Filament 4, require it in a separate copy of the repository:

```bash
composer require "filament/filament:^4.16" --with-all-dependencies
composer test
```

## Workbench application

The repository includes a workbench application with products and brands, preset views, a table widget, a standalone table and the user views resource. Every optional feature is enabled in its panel provider.

```bash
composer serve
```

Open `http://127.0.0.1:8000/admin` and sign in with `admin@example.com` and the password `password`.

## Building assets

The stylesheet and the advanced search component are built with esbuild:

```bash
npm install
npm run build
```

The compiled files in `resources/dist` are committed. After rebuilding, run `php artisan filament:assets` in the application that uses the package.

## Testing your own tables

Use Filament's and Livewire's testing helpers. The package's state is available on the component:

```php
use function Pest\Livewire\livewire;

it('applies a preset view', function () {
    livewire(ListProducts::class)
        ->call('applyDynamicTableView', 'preset:active')
        ->assertSet('activeDynamicTableView', 'preset:active')
        ->assertCanSeeTableRecords($activeProducts);
});

it('saves a view', function () {
    livewire(ListProducts::class)
        ->callAction('dynamicTablesSaveView', data: ['name' => 'My view'])
        ->assertNotified();
});
```
