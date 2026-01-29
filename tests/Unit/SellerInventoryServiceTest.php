<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\SellerInventoryService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SellerInventoryServiceTest extends TestCase
{
    private SellerInventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SellerInventoryService();
    }

    public function test_withdraw_creates_new_inventory_record(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $result = $this->service->withdraw($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 25],
        ]);

        $this->assertCount(1, $result);
        $inventory = SellerInventory::where('user_id', $seller->id)
            ->where('store_product_id', $storeProduct->id)
            ->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(25, $inventory->quantity);
        $this->assertEquals(100, $storeProduct->fresh()->stock_quantity);
    }

    public function test_withdraw_increments_existing_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 10,
        ]);

        $this->service->withdraw($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 15],
        ]);

        $inventory = SellerInventory::where('user_id', $seller->id)
            ->where('store_product_id', $storeProduct->id)
            ->first();
        $this->assertEquals(25, $inventory->quantity);
    }

    public function test_withdraw_restores_soft_deleted_inventory_and_increments(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 5,
        ]);
        $inventory->delete();

        $this->service->withdraw($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 10],
        ]);

        $restored = SellerInventory::where('user_id', $seller->id)
            ->where('store_product_id', $storeProduct->id)
            ->first();

        $this->assertNotNull($restored);
        $this->assertEquals(15, $restored->quantity);
        $this->assertNull($restored->deleted_at);
    }

    public function test_withdraw_validates_available_quantity(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 50,
        ]);

        $otherSeller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        SellerInventory::factory()->create([
            'user_id' => $otherSeller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 40,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->withdraw($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 20],
        ]);
    }

    public function test_return_decrements_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 30,
        ]);

        $this->service->return($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 10],
        ]);

        $inventory = SellerInventory::where('user_id', $seller->id)
            ->where('store_product_id', $storeProduct->id)
            ->first();
        $this->assertEquals(20, $inventory->quantity);
        $this->assertEquals(100, $storeProduct->fresh()->stock_quantity);
    }

    public function test_return_soft_deletes_when_quantity_reaches_zero(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 10,
        ]);

        $this->service->return($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 10],
        ]);

        $this->assertSoftDeleted('seller_inventory', ['id' => $inventory->id]);
    }

    public function test_return_validates_seller_has_quantity(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 5,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->return($store, $seller, [
            ['store_product_id' => $storeProduct->id, 'quantity' => 10],
        ]);
    }

    public function test_get_available_quantity_calculates_correctly(): void
    {
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 30,
        ]);

        $available = $this->service->getAvailableQuantity($storeProduct);

        $this->assertEquals(70, $available);
    }
}
