<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreAuthorizationTest extends TestCase
{

    public function test_ensure_store_access_middleware_blocks_manager_from_other_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/stores/{$store2->id}");

        $response->assertStatus(403);
    }

    public function test_ensure_store_access_middleware_allows_admin_to_access_any_store(): void
    {
        $store = Store::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200);
    }

    public function test_ensure_store_access_middleware_blocks_manager_from_updating_other_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $data = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/stores/{$store2->id}", $data);

        $response->assertStatus(403);
    }

    public function test_ensure_store_access_middleware_blocks_manager_from_deleting_other_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/v1/stores/{$store2->id}");

        $response->assertStatus(403);
    }

    public function test_store_policy_blocks_manager_from_creating_store(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $data = ['name' => 'New Store'];

        $response = $this->postJson('/api/v1/stores', $data);

        $response->assertStatus(403);
    }

    public function test_store_policy_blocks_seller_from_creating_store(): void
    {
        $store = Store::factory()->create();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sanctum::actingAs($seller);

        $data = ['name' => 'New Store'];

        $response = $this->postJson('/api/v1/stores', $data);

        $response->assertStatus(403);
    }
}
