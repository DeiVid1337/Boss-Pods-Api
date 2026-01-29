<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SellerInventory;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerInventoryControllerTest extends TestCase
{
    public function test_post_withdraw_as_seller_creates_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/withdraw", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 25],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'user_id', 'store_product_id', 'quantity'],
                ],
            ]);

        $this->assertDatabaseHas('seller_inventory', [
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 25,
        ]);

        $this->assertEquals(100, $storeProduct->fresh()->stock_quantity);
    }

    public function test_post_withdraw_validates_available_quantity(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
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

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/withdraw", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 20],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_post_withdraw_seller_cannot_withdraw_for_another_seller(): void
    {
        $store = Store::factory()->create();
        $seller1 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $seller2 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        Sanctum::actingAs($seller1);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/withdraw", [
            'seller_id' => $seller2->id,
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 25],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_post_withdraw_manager_can_authorize_for_seller_in_store(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/withdraw", [
            'seller_id' => $seller->id,
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 25],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('seller_inventory', [
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 25,
        ]);
    }

    public function test_post_return_decrements_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 30,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/return", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(20, $inventory->fresh()->quantity);
        $this->assertEquals(100, $storeProduct->fresh()->stock_quantity);
    }

    public function test_post_return_validates_seller_has_quantity(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
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

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/inventory/return", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_get_user_inventory_as_seller_returns_own_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        $storeProduct1 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
        ]);
        $storeProduct2 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
        ]);
        $storeProduct3 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product3->id,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct1->id,
        ]);
        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct2->id,
        ]);
        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct3->id,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/users/{$seller->id}/inventory");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'store_product_id', 'quantity'],
                ],
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_user_inventory_as_manager_for_same_store_returns_200(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/users/{$seller->id}/inventory");

        $response->assertStatus(200);
    }

    public function test_get_user_inventory_as_manager_for_other_store_returns_403(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store2->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/users/{$seller->id}/inventory");

        $response->assertStatus(403);
    }

    public function test_get_store_sellers_inventory_as_manager_returns_all_sellers(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $seller1 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $seller2 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        SellerInventory::factory()->create([
            'user_id' => $seller1->id,
            'store_product_id' => $storeProduct->id,
        ]);
        SellerInventory::factory()->create([
            'user_id' => $seller2->id,
            'store_product_id' => $storeProduct->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sellers/inventory");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'store_product_id', 'quantity'],
                ],
                'meta',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_store_sellers_inventory_as_seller_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sellers/inventory");

        $response->assertStatus(403);
    }

    public function test_post_sales_with_seller_inventory_decrements_both(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 100,
        ]);

        $inventory = SellerInventory::factory()->create([
            'user_id' => $seller->id,
            'store_product_id' => $storeProduct->id,
            'quantity' => 30,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(20, $inventory->fresh()->quantity);
        $this->assertEquals(90, $storeProduct->fresh()->stock_quantity);
    }

    public function test_post_sales_with_seller_cannot_sell_more_than_inventory(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
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

        Sanctum::actingAs($seller);

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }
}
