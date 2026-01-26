<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_get_users_as_admin_returns_all_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'store_id', 'is_active'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(4, $response->json('data')); // 3 + admin
    }

    public function test_get_users_as_manager_returns_only_same_store_users(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);
        User::factory()->create(['role' => 'seller', 'store_id' => $store1->id]);
        User::factory()->create(['role' => 'seller', 'store_id' => $store2->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // manager + seller from store1
    }

    public function test_get_users_as_seller_returns_403(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_get_users_with_role_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'seller']);
        User::factory()->create(['role' => 'seller']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?role=seller');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $user) {
            $this->assertEquals('seller', $user['role']);
        }
    }

    public function test_get_user_by_id_as_admin_returns_any_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_get_user_by_id_as_manager_for_same_store_returns_200(): void
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);
        $user = User::factory()->create(['store_id' => $store->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_get_user_by_id_as_manager_for_other_store_returns_403(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => $store1->id]);
        $user = User::factory()->create(['store_id' => $store2->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(403);
    }

    public function test_get_user_by_id_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users/99999');

        $response->assertStatus(404);
    }

    public function test_post_users_as_admin_creates_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Jane Seller',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'seller',
            'store_id' => $store->id,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.email', $data['email'])
            ->assertJsonPath('data.role', $data['role'])
            ->assertJsonPath('data.store_id', $data['store_id']);

        $this->assertDatabaseHas('users', [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'store_id' => $data['store_id'],
        ]);

        // Verify password is hashed
        $user = User::where('email', $data['email'])->first();
        $this->assertNotEquals($data['password'], $user->password);
    }

    public function test_post_users_as_manager_returns_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'seller',
        ]);

        $response->assertStatus(403);
    }

    public function test_post_users_with_manager_role_without_store_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Jane Manager',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'manager',
        ];

        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_post_users_with_seller_role_without_store_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Jane Seller',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'seller',
        ];

        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_post_users_with_admin_role_store_id_optional(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Admin User',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ];

        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.store_id', null);
    }

    public function test_post_users_duplicate_email_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'New User',
            'email' => $existingUser->email,
            'password' => 'password123',
            'role' => 'seller',
            'store_id' => Store::factory()->create()->id,
        ];

        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_put_users_as_admin_updates_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $user = User::factory()->create(['role' => 'seller', 'store_id' => $store->id]);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->putJson("/api/v1/users/{$user->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.email', $data['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
    }

    public function test_put_users_as_manager_returns_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $user = User::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_put_users_update_password_only_when_provided(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Create user with admin role to avoid store_id validation
        $user = User::factory()->create(['role' => 'admin', 'password' => 'oldpassword']);
        $oldPasswordHash = $user->password;

        Sanctum::actingAs($admin);

        // Update without password
        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($oldPasswordHash, $user->fresh()->password);

        // Update with new password
        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $this->assertNotEquals($oldPasswordHash, $user->fresh()->password);
    }

    public function test_put_users_with_manager_role_without_store_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'manager']);

        Sanctum::actingAs($admin);

        $data = [
            'role' => 'manager',
            'store_id' => null,
        ];

        $response = $this->putJson("/api/v1/users/{$user->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_put_users_role_change_to_admin_clears_store_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store = Store::factory()->create();
        $user = User::factory()->create(['role' => 'manager', 'store_id' => $store->id]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/users/{$user->id}", ['role' => 'admin']);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.store_id', null);
        $this->assertNull($user->fresh()->store_id);
    }

    public function test_delete_users_as_admin_soft_deletes_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'User deleted successfully.']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_users_self_as_admin_returns_403(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'You cannot delete your own account.']);
    }

    public function test_delete_users_as_manager_returns_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $user = User::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(403);
    }

    public function test_all_user_endpoints_without_token_return_401(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/users')->assertStatus(401);
        $this->getJson("/api/v1/users/{$user->id}")->assertStatus(401);
        $this->postJson('/api/v1/users', [])->assertStatus(401);
        $this->putJson("/api/v1/users/{$user->id}", [])->assertStatus(401);
        $this->deleteJson("/api/v1/users/{$user->id}")->assertStatus(401);
    }

    public function test_get_users_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        User::factory()->count(25)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(26, $response->json('meta.total')); // 25 + admin
    }

    public function test_get_users_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?per_page=200');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_get_users_with_store_id_filter_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        User::factory()->create(['store_id' => $store1->id]);
        User::factory()->create(['store_id' => $store1->id]);
        User::factory()->create(['store_id' => $store2->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/users?store_id={$store1->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $user) {
            $this->assertEquals($store1->id, $user['store_id']);
        }
    }

    public function test_get_users_with_search_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@boss.local']);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@test.com']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?search=example');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_users_with_sort_by_email_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'zzz@example.com']);
        User::factory()->create(['email' => 'zebra@example.com']);
        User::factory()->create(['email' => 'alpha@example.com']);
        User::factory()->create(['email' => 'beta@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?sort_by=email&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('alpha@example.com', $data[0]['email']);
        $this->assertEquals('beta@example.com', $data[1]['email']);
        $this->assertEquals('zebra@example.com', $data[2]['email']);
        $this->assertEquals('zzz@example.com', $data[3]['email']);
    }

    public function test_get_users_with_invalid_sort_by_uses_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@boss.local', 'name' => 'Zzz Admin']);
        User::factory()->create(['name' => 'Zebra']);
        User::factory()->create(['name' => 'Alpha']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?sort_by=invalid_column');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha', $data[0]['name']); // Defaults to name asc
    }

    public function test_get_users_cache_hit_on_second_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@boss.local']);
        User::factory()->create(['name' => 'Test User']);

        Sanctum::actingAs($admin);

        // First request
        $response1 = $this->getJson('/api/v1/users');
        $response1->assertStatus(200);

        // Second request with same params should hit cache
        $response2 = $this->getJson('/api/v1/users');
        $response2->assertStatus(200);

        // Both should return same data
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }
}
