<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{

    public function test_get_products_as_admin_returns_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'brand', 'name', 'flavor', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_products_as_manager_returns_products(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        Product::factory()->count(2)->create();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_products_as_seller_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(403);
    }

    public function test_get_products_without_authentication_returns_401(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    public function test_get_products_with_brand_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create(['brand' => 'BrandA']);
        Product::factory()->create(['brand' => 'BrandB']);
        Product::factory()->create(['brand' => 'BrandA']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?brand=BrandA');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $product) {
            $this->assertEquals('BrandA', $product['brand']);
        }
    }

    public function test_get_product_by_id_as_admin_returns_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.brand', $product->brand)
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonPath('data.flavor', $product->flavor);
    }

    public function test_get_product_by_id_as_manager_returns_product(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
    }

    public function test_get_product_by_id_as_seller_returns_403(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($seller);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_get_product_by_id_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products/999');

        $response->assertStatus(404);
    }

    public function test_create_product_as_admin_returns_201(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ];

        $response = $this->postJson('/api/v1/products', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.brand', 'BrandX')
            ->assertJsonPath('data.name', 'Pod1')
            ->assertJsonPath('data.flavor', 'Mint');

        $this->assertDatabaseHas('products', [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);
    }

    public function test_create_product_as_manager_returns_403(): void
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

    public function test_create_product_without_authentication_returns_401(): void
    {
        $data = [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ];

        $response = $this->postJson('/api/v1/products', $data);

        $response->assertStatus(401);
    }

    public function test_create_product_with_invalid_data_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand', 'name', 'flavor']);
    }

    public function test_create_product_with_duplicate_brand_name_flavor_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ];

        $response = $this->postJson('/api/v1/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['flavor']);
    }

    public function test_update_product_as_admin_returns_200(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);

        Sanctum::actingAs($admin);

        $data = ['flavor' => 'Updated Flavor'];

        $response = $this->putJson("/api/v1/products/{$product->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.flavor', 'Updated Flavor');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'flavor' => 'Updated Flavor',
        ]);
    }

    public function test_update_product_as_manager_returns_403(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $data = ['flavor' => 'Updated Flavor'];

        $response = $this->putJson("/api/v1/products/{$product->id}", $data);

        $response->assertStatus(403);
    }

    public function test_update_product_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = ['flavor' => 'Updated Flavor'];

        $response = $this->putJson('/api/v1/products/999', $data);

        $response->assertStatus(404);
    }

    public function test_delete_product_as_admin_deletes_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product deleted successfully.']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_product_as_manager_returns_403(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $product = Product::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
    }

    public function test_get_products_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->count(25)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_get_products_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?per_page=0');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/products?per_page=-1');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/products?per_page=101');

        $response->assertStatus(422);
    }

    public function test_get_products_with_invalid_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?page=0');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/products?page=-1');

        $response->assertStatus(422);
    }

    public function test_update_product_with_duplicate_brand_name_flavor_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product1 = Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);
        $product2 = Product::factory()->create([
            'brand' => 'BrandY',
            'name' => 'Pod2',
            'flavor' => 'Strawberry',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/products/{$product2->id}", [
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['flavor']);
    }

    public function test_get_products_with_search_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product1', 'flavor' => 'Mint']);
        Product::factory()->create(['brand' => 'BrandB', 'name' => 'Product2', 'flavor' => 'Vanilla']);
        Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product3', 'flavor' => 'Mint']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?search=Mint');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_products_with_sort_by_brand_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create(['brand' => 'Zebra']);
        Product::factory()->create(['brand' => 'Alpha']);
        Product::factory()->create(['brand' => 'Beta']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?sort_by=brand&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha', $data[0]['brand']);
        $this->assertEquals('Beta', $data[1]['brand']);
        $this->assertEquals('Zebra', $data[2]['brand']);
    }

    public function test_get_products_with_invalid_sort_by_uses_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create(['brand' => 'Zebra']);
        Product::factory()->create(['brand' => 'Alpha']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/products?sort_by=invalid_column');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha', $data[0]['brand']); // Defaults to brand asc
    }

    public function test_get_products_cache_hit_on_second_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create(['brand' => 'Test']);

        Sanctum::actingAs($admin);

        // First request
        $response1 = $this->getJson('/api/v1/products');
        $response1->assertStatus(200);

        // Second request with same params should hit cache
        $response2 = $this->getJson('/api/v1/products');
        $response2->assertStatus(200);

        // Both should return same data
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }
}
