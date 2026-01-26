<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    public function test_get_sales_as_admin_returns_all_store_sales(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);
        Sale::factory()->count(3)->create(['store_id' => $store->id, 'user_id' => $user->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'store_id', 'user_id', 'total_amount', 'sale_date'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_sales_as_manager_returns_store_sales(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        Sale::factory()->count(2)->create(['store_id' => $store->id, 'user_id' => $manager->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_sales_as_seller_returns_only_own_sales(): void
    {
        $store = Store::factory()->create();
        $seller1 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $seller2 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sale::factory()->count(2)->create(['store_id' => $store->id, 'user_id' => $seller1->id]);
        Sale::factory()->count(3)->create(['store_id' => $store->id, 'user_id' => $seller2->id]);

        Sanctum::actingAs($seller1);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_sales_with_date_range_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-01-15'),
        ]);
        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-01-20'),
        ]);
        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-02-01'),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales?from=2025-01-01&to=2025-01-31");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_sale_by_id_as_seller_for_own_sale_returns_200(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $seller->id]);

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales/{$sale->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $sale->id);
    }

    public function test_get_sale_by_id_as_seller_for_other_sale_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller1 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $seller2 = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $seller2->id]);

        Sanctum::actingAs($seller1);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales/{$sale->id}");

        $response->assertStatus(403);
    }

    public function test_get_sale_by_id_with_wrong_store_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store1->id]);
        $sale = Sale::factory()->create(['store_id' => $store1->id, 'user_id' => $user->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store2->id}/sales/{$sale->id}");

        $response->assertStatus(404);
    }

    public function test_post_sales_creates_sale_items_decrements_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'sale_price' => 25.50,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '76.50')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'store_id',
                    'user_id',
                    'total_amount',
                    'items' => [
                        '*' => ['id', 'store_product_id', 'quantity', 'unit_price', 'subtotal'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('sales', [
            'store_id' => $store->id,
            'user_id' => $admin->id,
            'total_amount' => 76.50,
        ]);

        $this->assertEquals(7, $storeProduct->fresh()->stock_quantity);
    }

    public function test_post_sales_with_customer_id_increments_total_purchases(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $customer = Customer::factory()->create(['total_purchases' => 5]);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'sale_price' => 20.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'customer_id' => $customer->id,
            'items' => [
                [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(201);

        $this->assertEquals(7, $customer->fresh()->total_purchases);
    }

    public function test_post_sales_with_insufficient_stock_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 5,
            'sale_price' => 20.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_post_sales_with_store_product_from_another_store_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'sale_price' => 20.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store1->id}/sales", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.store_product_id']);
    }

    public function test_post_sales_without_customer_id_walk_in_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'sale_price' => 20.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                [
                    'store_product_id' => $storeProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_id', null);
    }

    public function test_all_sales_endpoints_without_token_return_401(): void
    {
        $store = Store::factory()->create();
        $sale = Sale::factory()->create(['store_id' => $store->id]);

        $this->getJson("/api/v1/stores/{$store->id}/sales")->assertStatus(401);
        $this->getJson("/api/v1/stores/{$store->id}/sales/{$sale->id}")->assertStatus(401);
        $this->postJson("/api/v1/stores/{$store->id}/sales", [])->assertStatus(401);
    }

    public function test_get_sales_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        Sale::factory()->count(25)->create(['store_id' => $store->id, 'user_id' => $user->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales?per_page=10&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_get_sales_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales?per_page=200");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_post_sales_with_multiple_items_calculates_total_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $storeProduct1 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'stock_quantity' => 10,
            'sale_price' => 25.00,
        ]);
        $storeProduct2 = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'stock_quantity' => 10,
            'sale_price' => 30.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                [
                    'store_product_id' => $storeProduct1->id,
                    'quantity' => 2,
                ],
                [
                    'store_product_id' => $storeProduct2->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '140.00'); // (2 * 25) + (3 * 30) = 50 + 90 = 140

        $this->assertEquals(8, $storeProduct1->fresh()->stock_quantity);
        $this->assertEquals(7, $storeProduct2->fresh()->stock_quantity);
    }

    public function test_post_sales_with_duplicate_store_product_id_insufficient_aggregate_stock_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 8,
            'sale_price' => 10.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 5],
                ['store_product_id' => $storeProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
        $this->assertEquals(8, $storeProduct->fresh()->stock_quantity);
    }

    public function test_post_sales_with_duplicate_store_product_id_sufficient_aggregate_stock_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'sale_price' => 10.00,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'items' => [
                ['store_product_id' => $storeProduct->id, 'quantity' => 3],
                ['store_product_id' => $storeProduct->id, 'quantity' => 4],
            ],
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/sales", $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '70.00');
        $this->assertEquals(3, $storeProduct->fresh()->stock_quantity);
    }

    public function test_get_sales_with_search_on_notes_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        Sale::factory()->create(['store_id' => $store->id, 'notes' => 'Customer requested delivery']);
        Sale::factory()->create(['store_id' => $store->id, 'notes' => 'Regular sale']);
        Sale::factory()->create(['store_id' => $store->id, 'notes' => null]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales?search=delivery");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsStringIgnoringCase('delivery', $response->json('data.0.notes'));
    }

    public function test_get_sales_with_sort_by_total_amount_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 100.00]);
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 50.00]);
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 75.00]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales?sort_by=total_amount&sort_order=asc");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('50.00', $data[0]['total_amount']);
        $this->assertEquals('75.00', $data[1]['total_amount']);
        $this->assertEquals('100.00', $data[2]['total_amount']);
    }

    public function test_get_sales_defaults_to_sale_date_desc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()->subDays(2)]);
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()]);
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()->subDay()]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}/sales");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Most recent first (desc default)
        $this->assertTrue(
            strtotime($data[0]['sale_date']) >= strtotime($data[1]['sale_date'])
        );
    }
}
