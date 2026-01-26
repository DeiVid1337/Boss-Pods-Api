<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreProductIntegrationTest extends TestCase
{

    public function test_delete_product_referenced_by_store_products_returns_409(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete product: it is referenced by store products.',
            ]);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_delete_store_soft_deletes_and_preserves_references(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertDatabaseHas('store_products', ['id' => $storeProduct->id]);
    }
}
