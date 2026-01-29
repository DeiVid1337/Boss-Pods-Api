<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SellerInventory;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerInventoryFactory extends Factory
{
    protected $model = SellerInventory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_product_id' => StoreProduct::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
        ];
    }
}
