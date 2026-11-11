<?php

use KaraOdin\FilamentDynamicTables\Tests\TestCase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Models\User;

uses(TestCase::class)->in(__DIR__);

function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    test()->actingAs($user);

    return $user;
}

function actingAsAdmin(): User
{
    return actingAsUser(['is_admin' => true]);
}

/**
 * The views listed in the favorites bar (the view manager dropdown is part
 * of the page too, so page-wide assertions would also see its views).
 */
function favoritesBarHtml($component): string
{
    $html = $component->html();
    $start = strpos($html, 'fdt-favorites-bar-views');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</nav>', $start);

    return substr($html, $start, $end === false ? null : $end - $start);
}

/**
 * Livewire::test() works on Livewire 3 and 4. The pest-plugin-livewire
 * helper is not used because its Livewire 4 release requires PHP 8.3.
 *
 * @param  array<string, mixed>  $params
 */
function livewire(string $component, array $params = []): Testable
{
    return Livewire::test($component, $params);
}
