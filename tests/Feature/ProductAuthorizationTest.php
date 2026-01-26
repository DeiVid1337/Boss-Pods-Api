<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{

    public function test_product_policy_blocks_seller_from_viewing_products(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(403);
    }

    public function test_product_policy_blocks_seller_from_viewing_single_product(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_product_policy_blocks_manager_from_creating_product(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $data = [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ];

        $response = $this->postJson('/api/v1/products', $data);

        $response->assertStatus(403);
    }

    public function test_product_policy_blocks_manager_from_updating_product(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $data = ['flavor' => 'Updated Flavor'];

        $response = $this->putJson("/api/v1/products/{$product->id}", $data);

        $response->assertStatus(403);
    }

    public function test_product_policy_blocks_manager_from_deleting_product(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_product_policy_allows_manager_to_view_products(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        Product::factory()->count(2)->create();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
    }
}
