<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Brand;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Sam Rivera',
            'email' => 'sam@example.com',
        ]);

        Brand::factory(6)->create()->each(function (Brand $brand): void {
            Product::factory(12)->for($brand)->create();
        });
    }
}
