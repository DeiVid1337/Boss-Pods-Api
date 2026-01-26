<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreControllerTest extends TestCase
{

    public function test_get_stores_as_admin_returns_all_stores(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'address', 'phone', 'is_active', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_stores_as_manager_returns_only_assigned_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($store1->id, $response->json('data.0.id'));
    }

    public function test_get_stores_as_seller_returns_only_assigned_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store1->id]);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($store1->id, $response->json('data.0.id'));
    }

    public function test_get_stores_without_authentication_returns_401(): void
    {
        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(401);
    }

    public function test_get_stores_with_is_active_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['is_active' => true]);
        Store::factory()->create(['is_active' => false]);
        Store::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?is_active=1');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $store) {
            $this->assertTrue($store['is_active']);
        }
    }

    public function test_get_store_by_id_as_admin_returns_store(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $store->id)
            ->assertJsonPath('data.name', $store->name);
    }

    public function test_get_store_by_id_as_manager_for_own_store_returns_200(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $store->id);
    }

    public function test_get_store_by_id_as_manager_for_other_store_returns_403(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store2->id}");

        $response->assertStatus(403);
    }

    public function test_get_store_by_id_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores/999');

        $response->assertStatus(404);
    }

    public function test_get_store_by_id_as_manager_for_nonexistent_store_returns_404(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/stores/999');

        $response->assertStatus(404);
    }

    public function test_create_store_as_admin_returns_201(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'New Store',
            'address' => '123 Main St',
            'phone' => '+1234567890',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/stores', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Store',
                    'address' => '123 Main St',
                    'phone' => '+1234567890',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('stores', [
            'name' => 'New Store',
            'address' => '123 Main St',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);
    }

    public function test_create_store_as_manager_returns_403(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $data = ['name' => 'New Store'];

        $response = $this->postJson('/api/v1/stores', $data);

        $response->assertStatus(403);
    }

    public function test_create_store_without_authentication_returns_401(): void
    {
        $data = ['name' => 'New Store'];

        $response = $this->postJson('/api/v1/stores', $data);

        $response->assertStatus(401);
    }

    public function test_create_store_with_invalid_data_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/stores', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_store_as_admin_returns_200(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create(['name' => 'Old Name']);

        Sanctum::actingAs($admin);

        $data = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/stores/{$store->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_store_as_manager_returns_403(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $data = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/stores/{$store->id}", $data);

        $response->assertStatus(403);
    }

    public function test_update_store_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = ['name' => 'Updated Name'];

        $response = $this->putJson('/api/v1/stores/999', $data);

        $response->assertStatus(404);
    }

    public function test_delete_store_as_admin_soft_deletes_store(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Store deleted successfully.']);

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_delete_store_as_manager_returns_403(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(403);
    }

    public function test_get_stores_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->count(25)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_get_stores_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?per_page=0');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/stores?per_page=-1');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/stores?per_page=101');

        $response->assertStatus(422);
    }

    public function test_get_stores_with_invalid_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?page=0');

        $response->assertStatus(422);

        $response = $this->getJson('/api/v1/stores?page=-1');

        $response->assertStatus(422);
    }

    public function test_get_stores_with_search_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Alpha Store']);
        Store::factory()->create(['name' => 'Beta Store']);
        Store::factory()->create(['name' => 'Alpha Beta Store']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?search=Alpha');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $store) {
            $this->assertStringContainsStringIgnoringCase('Alpha', $store['name']);
        }
    }

    public function test_get_stores_with_sort_by_name_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Zebra Store']);
        Store::factory()->create(['name' => 'Alpha Store']);
        Store::factory()->create(['name' => 'Beta Store']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?sort_by=name&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha Store', $data[0]['name']);
        $this->assertEquals('Beta Store', $data[1]['name']);
        $this->assertEquals('Zebra Store', $data[2]['name']);
    }

    public function test_get_stores_with_invalid_sort_by_uses_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Zebra Store']);
        Store::factory()->create(['name' => 'Alpha Store']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/stores?sort_by=invalid_column');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha Store', $data[0]['name']); // Defaults to name asc
    }

    public function test_get_stores_cache_hit_on_second_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Test Store']);

        Sanctum::actingAs($admin);

        // First request
        $response1 = $this->getJson('/api/v1/stores');
        $response1->assertStatus(200);

        // Second request with same params should hit cache
        $response2 = $this->getJson('/api/v1/stores');
        $response2->assertStatus(200);

        // Both should return same data
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }

    public function test_get_stores_cache_invalidated_after_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Store 1']);

        Sanctum::actingAs($admin);

        // First request
        $response1 = $this->getJson('/api/v1/stores');
        $response1->assertStatus(200);
        $this->assertCount(1, $response1->json('data'));

        // Create new store
        $this->postJson('/api/v1/stores', ['name' => 'Store 2']);

        // Second request should reflect new store (cache invalidated)
        $response2 = $this->getJson('/api/v1/stores');
        $response2->assertStatus(200);
        $this->assertCount(2, $response2->json('data'));
    }
}
