<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Tests\TestCase;

class StoreProductAvailableQuantityTest extends TestCase
{
    public function test_available_quantity_calculates_correctly(): void
    {
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $seller1 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $seller2 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        SellerInventory::factory()->create([
            'user_id' => $seller1->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 20,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller2->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 30,
        ]);

        $this->assertEquals(50, $storeProduct->available_quantity);
    }

    public function test_available_quantity_returns_zero_when_all_stock_withdrawn(): void
    {
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 50,
        ]);

        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 50,
        ]);

        $this->assertEquals(0, $storeProduct->available_quantity);
    }

    public function test_available_quantity_never_negative(): void
    {
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 15,
        ]);

        $this->assertEquals(0, $storeProduct->available_quantity);
    }
}
