<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Enums\Category;
use Workbench\App\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'category' => fake()->randomElement(Category::cases()),
            'price' => fake()->randomFloat(2, 5, 900),
            'stock' => fake()->numberBetween(0, 250),
            'is_active' => fake()->boolean(80),
            'released_at' => fake()->dateTimeBetween('-3 years'),
        ];
    }
}
