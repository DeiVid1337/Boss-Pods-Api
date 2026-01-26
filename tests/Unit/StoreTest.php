<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_active_scope_returns_only_active_stores(): void
    {
        Store::factory()->create(['is_active' => true]);
        Store::factory()->create(['is_active' => false]);
        Store::factory()->create(['is_active' => true]);

        $activeStores = Store::active()->get();

        $this->assertCount(2, $activeStores);
        foreach ($activeStores as $store) {
            $this->assertTrue($store->is_active);
        }
    }

    public function test_store_has_users_relationship(): void
    {
        $store = Store::factory()->create();
        \App\Models\User::factory()->count(2)->create(['store_id' => $store->id]);

        $this->assertCount(2, $store->users);
    }

    public function test_store_has_store_products_relationship(): void
    {
        $store = Store::factory()->create();
        \App\Models\Product::factory()->create();
        \App\Models\StoreProduct::factory()->count(2)->create(['store_id' => $store->id]);

        $this->assertCount(2, $store->storeProducts);
    }

    public function test_store_soft_deletes(): void
    {
        $store = Store::factory()->create();

        $store->delete();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertNull(Store::find($store->id));
        $this->assertNotNull(Store::withTrashed()->find($store->id));
    }
}
