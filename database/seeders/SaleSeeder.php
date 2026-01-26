<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{

    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        $stores = Store::all();
        $customers = Customer::all();
        $users = User::whereIn('role', ['manager', 'seller'])->get();

        if ($stores->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Stores or users not found. Skipping sales seeding.');
            return;
        }

        $saleService = app(SaleService::class);

        $salesToCreate = min(5, max(3, (int) ceil($stores->count() * 1.5)));

        for ($i = 0; $i < $salesToCreate; $i++) {
            $store = $stores->random();
            $user = $users->where('store_id', $store->id)->first() ?? $users->random();

            $storeProducts = StoreProduct::where('store_id', $store->id)
                ->where('stock_quantity', '>', 5)
                ->where('is_active', true)
                ->get();

            if ($storeProducts->isEmpty()) {
                $this->command->warn("No store products with stock for store {$store->id}. Skipping sale.");
                continue;
            }

            $items = [];
            $productsToSell = $storeProducts->random(min(3, $storeProducts->count()));

            foreach ($productsToSell as $storeProduct) {
                $quantity = fake()->numberBetween(1, min(5, $storeProduct->stock_quantity - 1));
                $items[] = [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => $quantity,
                ];
            }

            $saleData = [
                'items' => $items,
                'customer_id' => $customers->isNotEmpty() && fake()->boolean(70) ? $customers->random()->id : null,
                'notes' => fake()->optional(0.5)->sentence(),
            ];

            try {
                $saleService->createSale($store, $user, $saleData);
                $this->command->info("Sale created for store {$store->id} by user {$user->id}");
            } catch (\Exception $e) {
                $this->command->error("Failed to create sale: {$e->getMessage()}");
            }
        }

        $this->command->info('Sales seeded successfully.');
    }
}
