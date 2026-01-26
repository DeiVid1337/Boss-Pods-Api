<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreProductControllerTest extends TestCase
{
    public function test_get_store_products_as_admin_returns_paginated_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
        ]);
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
        ]);
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product3->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'store_id', 'product_id', 'cost_price', 'sale_price', 'stock_quantity', 'min_stock_level', 'is_active'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_store_products_as_manager_for_assigned_store_returns_list(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
        ]);
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_store_products_as_manager_for_other_store_returns_403(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store2->id}/products");

        $response->assertStatus(403);
    }

    public function test_get_store_products_as_seller_for_assigned_store_returns_list(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products");

        $response->assertStatus(200);
    }

    public function test_get_store_products_with_low_stock_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'stock_quantity' => 5,
            'min_stock_level' => 10,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'stock_quantity' => 15,
            'min_stock_level' => 10,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?low_stock=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(5, $response->json('data.0.stock_quantity'));
    }

    public function test_get_store_products_with_is_active_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'is_active' => true,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?is_active=1");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }

    public function test_get_store_product_by_id_as_manager_for_own_store_returns_200(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $storeProduct->id);
    }

    public function test_get_store_product_by_id_with_wrong_store_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store2->id}/products/{$storeProduct->id}");

        $response->assertStatus(404);
    }

    public function test_create_store_product_as_admin_creates_store_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $data = [
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
            'stock_quantity' => 100,
            'min_stock_level' => 10,
            'is_active' => true,
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/products", $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.cost_price', '10.00')
            ->assertJsonPath('data.sale_price', '15.00');

        $this->assertDatabaseHas('store_products', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
        ]);
    }

    public function test_create_store_product_as_manager_for_assigned_store_creates(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $data = [
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
            'stock_quantity' => 100,
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/products", $data);

        $response->assertStatus(201);
    }

    public function test_create_store_product_as_seller_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($seller);

        $data = [
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
            'stock_quantity' => 100,
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/products", $data);

        $response->assertStatus(403);
    }

    public function test_create_store_product_duplicate_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
            'stock_quantity' => 100,
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/products", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_create_store_product_sale_price_less_than_cost_price_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $data = [
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 5.00,
            'stock_quantity' => 100,
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/products", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sale_price']);
    }

    public function test_update_store_product_as_manager_updates(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'cost_price' => 10.00,
            'sale_price' => 15.00,
        ]);

        Sanctum::actingAs($manager);

        $data = ['sale_price' => 20.00];

        $response = $this->putJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.sale_price', '20.00');

        $this->assertDatabaseHas('store_products', [
            'id' => $storeProduct->id,
            'sale_price' => 20.00,
        ]);
    }

    public function test_update_store_product_as_seller_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($seller);

        $data = ['sale_price' => 20.00];

        $response = $this->putJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}", $data);

        $response->assertStatus(403);
    }

    public function test_delete_store_product_as_admin_soft_deletes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Store product removed successfully.']);

        $this->assertSoftDeleted('store_products', ['id' => $storeProduct->id]);
    }

    public function test_delete_store_product_with_sale_items_returns_409(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $sale = \App\Models\Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $admin->id,
        ]);

        \App\Models\SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'store_product_id' => $storeProduct->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}");

        $response->assertStatus(409)
            ->assertJson(['message' => 'Cannot delete: product has sales history.']);
    }

    public function test_all_store_product_endpoints_without_token_return_401(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $this->getJson("/api/v1/stores/{$store->id}/products")->assertStatus(401);
        $this->getJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}")->assertStatus(401);
        $this->postJson("/api/v1/stores/{$store->id}/products", [])->assertStatus(401);
        $this->putJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}", [])->assertStatus(401);
        $this->deleteJson("/api/v1/stores/{$store->id}/products/{$storeProduct->id}")->assertStatus(401);
    }

    public function test_get_store_products_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        
        $products = Product::factory()->count(25)->create();
        foreach ($products as $product) {
            StoreProduct::factory()->create([
                'store_id' => $store->id,
                'product_id' => $product->id,
            ]);
        }

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?per_page=10&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_get_store_products_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?per_page=0");

        $response->assertStatus(422);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?per_page=101");

        $response->assertStatus(422);
    }

    public function test_get_store_products_with_search_filters_by_product_attributes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product1', 'flavor' => 'Mint']);
        $product2 = Product::factory()->create(['brand' => 'BrandB', 'name' => 'Product2', 'flavor' => 'Vanilla']);
        $product3 = Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product3', 'flavor' => 'Mint']);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product1->id]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product2->id]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product3->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?search=Mint");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_store_products_with_sort_by_product_name_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Zebra Product']);
        $product2 = Product::factory()->create(['name' => 'Alpha Product']);
        $product3 = Product::factory()->create(['name' => 'Beta Product']);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product1->id]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product2->id]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product3->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?sort_by=product_name&sort_order=asc");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha Product', $data[0]['product']['name']);
        $this->assertEquals('Beta Product', $data[1]['product']['name']);
        $this->assertEquals('Zebra Product', $data[2]['product']['name']);
    }

    public function test_get_store_products_with_invalid_sort_by_uses_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Zebra Product']);
        $product2 = Product::factory()->create(['name' => 'Alpha Product']);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product1->id]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product2->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/products?sort_by=invalid_column");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha Product', $data[0]['product']['name']); // Defaults to product_name asc
    }
}
