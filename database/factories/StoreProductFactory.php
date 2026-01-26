<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreProductFactory extends Factory
{
    protected $model = StoreProduct::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'product_id' => Product::factory(),
            'cost_price' => fake()->randomFloat(2, 5, 20),
            'sale_price' => fake()->randomFloat(2, 10, 30),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'min_stock_level' => fake()->numberBetween(5, 20),
            'is_active' => true,
        ];
    }
}
