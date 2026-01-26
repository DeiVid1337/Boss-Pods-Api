<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Seeder;

class StoreProductSeeder extends Seeder
{

    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        $stores = Store::whereIn('name', ['Palmas', 'Guarujá'])->get();
        $products = Product::all();

        if ($stores->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Stores or products not found. Skipping store products seeding.');
            return;
        }

        $inventoryData = [
            ['Vaporesso', 'XROS 3', 'Mint', 25.00, 45.00, 50, 10],
            ['Vaporesso', 'XROS 3', 'Tobacco', 25.00, 45.00, 50, 10],
            ['Vaporesso', 'XROS 3', 'Fruit', 25.00, 45.00, 50, 10],
            ['SMOK', 'Nord 5', 'Mint', 30.00, 55.00, 40, 8],
            ['SMOK', 'Nord 5', 'Tobacco', 30.00, 55.00, 40, 8],
            ['SMOK', 'Nord 5', 'Vanilla', 30.00, 55.00, 40, 8],
            ['Uwell', 'Caliburn G3', 'Mint', 28.00, 50.00, 45, 9],
            ['Uwell', 'Caliburn G3', 'Fruit', 28.00, 50.00, 45, 9],
            ['Uwell', 'Caliburn G3', 'Coffee', 28.00, 50.00, 45, 9],
            ['Geekvape', 'Aegis Pod', 'Mint', 22.00, 40.00, 60, 12],
            ['Geekvape', 'Aegis Pod', 'Tobacco', 22.00, 40.00, 60, 12],
            ['Geekvape', 'Aegis Pod', 'Fruit', 22.00, 40.00, 60, 12],
        ];

        foreach ($stores as $store) {
            foreach ($inventoryData as $item) {
                [$brand, $name, $flavor, $costPrice, $salePrice, $stockQuantity, $minStockLevel] = $item;

                $product = $products->firstWhere(function ($product) use ($brand, $name, $flavor) {
                    return $product->brand === $brand
                        && $product->name === $name
                        && $product->flavor === $flavor;
                });

                if (!$product) {
                    $this->command->warn("Product not found: {$brand} - {$name} - {$flavor}");
                    continue;
                }

                StoreProduct::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'cost_price' => $costPrice,
                        'sale_price' => $salePrice,
                        'stock_quantity' => $stockQuantity,
                        'min_stock_level' => $minStockLevel,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Store products seeded successfully.');
    }
}
