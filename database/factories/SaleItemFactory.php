<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 5, 50);
        $subtotal = $quantity * $unitPrice;

        return [
            'sale_id' => Sale::factory(),
            'store_product_id' => StoreProduct::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
        ];
    }
}
