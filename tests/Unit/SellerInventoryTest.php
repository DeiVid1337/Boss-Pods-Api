<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Tests\TestCase;

class SellerInventoryTest extends TestCase
{
    public function test_seller_inventory_has_user_relationship(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $user->id,
            'store_product_id' => $storeProduct->id,
        ]);

        $this->assertInstanceOf(User::class, $inventory->user);
        $this->assertEquals($user->id, $inventory->user->id);
    }

    public function test_seller_inventory_has_store_product_relationship(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $user->id,
            'store_product_id' => $storeProduct->id,
        ]);

        $this->assertInstanceOf(StoreProduct::class, $inventory->storeProduct);
        $this->assertEquals($storeProduct->id, $inventory->storeProduct->id);
    }

    public function test_seller_inventory_scope_for_user_filters_by_user_id(): void
    {
        $user1 = User::factory()->create(['role' => 'seller']);
        $user2 = User::factory()->create(['role' => 'seller']);
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $user1->id,
            'store_product_id' => $storeProduct->id,
        ]);
        SellerInventory::factory()->create([
            'user_id' => $user2->id,
            'store_product_id' => $storeProduct->id,
        ]);

        $user1Inventories = SellerInventory::forUser($user1->id)->get();

        $this->assertCount(1, $user1Inventories);
        $this->assertEquals($user1->id, $user1Inventories->first()->user_id);
    }

    public function test_seller_inventory_scope_for_store_product_filters_by_store_product_id(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $store = Store::factory()->create();
        $product1 = \App\Models\Product::factory()->create();
        $product2 = \App\Models\Product::factory()->create();
        $storeProduct1 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
        ]);
        $storeProduct2 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $user->id,
            'store_product_id' => $storeProduct1->id,
        ]);
        SellerInventory::factory()->create([
            'user_id' => $user->id,
            'store_product_id' => $storeProduct2->id,
        ]);

        $product1Inventories = SellerInventory::forStoreProduct($storeProduct1->id)->get();

        $this->assertCount(1, $product1Inventories);
        $this->assertEquals($storeProduct1->id, $product1Inventories->first()->store_product_id);
    }

    public function test_seller_inventory_soft_deletes(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $user->id,
            'store_product_id' => $storeProduct->id,
        ]);

        $inventory->delete();

        $this->assertSoftDeleted('seller_inventory', ['id' => $inventory->id]);
        $this->assertNull(SellerInventory::find($inventory->id));
        $this->assertNotNull(SellerInventory::withTrashed()->find($inventory->id));
    }
}
