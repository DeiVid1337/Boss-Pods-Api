<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Store;
use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_scope_admins_filters_by_admin_role(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'seller']);

        $admins = User::admins()->get();

        $this->assertCount(1, $admins);
        $this->assertEquals('admin', $admins->first()->role);
    }

    public function test_user_scope_managers_filters_by_manager_role(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'manager']);

        $managers = User::managers()->get();

        $this->assertCount(2, $managers);
        foreach ($managers as $manager) {
            $this->assertEquals('manager', $manager->role);
        }
    }

    public function test_user_scope_sellers_filters_by_seller_role(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'seller']);
        User::factory()->create(['role' => 'seller']);

        $sellers = User::sellers()->get();

        $this->assertCount(2, $sellers);
        foreach ($sellers as $seller) {
            $this->assertEquals('seller', $seller->role);
        }
    }

    public function test_user_scope_for_store_filters_by_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();

        User::factory()->create(['store_id' => $store1->id]);
        User::factory()->create(['store_id' => $store1->id]);
        User::factory()->create(['store_id' => $store2->id]);

        $store1Users = User::forStore($store1->id)->get();

        $this->assertCount(2, $store1Users);
        foreach ($store1Users as $user) {
            $this->assertEquals($store1->id, $user->store_id);
        }
    }

    public function test_user_is_admin_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isManager());
        $this->assertFalse($admin->isSeller());
    }

    public function test_user_is_manager_returns_true_for_manager(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->assertFalse($manager->isAdmin());
        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isSeller());
    }

    public function test_user_is_seller_returns_true_for_seller(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);

        $this->assertFalse($seller->isAdmin());
        $this->assertFalse($seller->isManager());
        $this->assertTrue($seller->isSeller());
    }

    public function test_user_has_store_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $this->assertInstanceOf(Store::class, $user->store);
        $this->assertEquals($store->id, $user->store->id);
    }

    public function test_user_has_sales_relationship(): void
    {
        $user = User::factory()->create();
        $sale = \App\Models\Sale::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->sales->contains($sale));
        $this->assertEquals($user->id, $sale->user_id);
    }

    public function test_user_soft_deletes(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }
}
