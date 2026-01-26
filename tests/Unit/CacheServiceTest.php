<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Store;
use App\Models\User;
use App\Services\CacheService;
use App\Services\StoreService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;
    private StoreService $storeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        $this->storeService = new StoreService();
        Cache::flush();
    }

    public function test_get_list_caches_result(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Test Store']);

        $callbackCallCount = 0;
        $callback = function () use (&$callbackCallCount) {
            $callbackCallCount++;
            return $this->storeService->list(
                User::factory()->create(['role' => 'admin']),
                [],
                15
            );
        };

        // First call - cache miss
        $result1 = $this->cacheService->getList('stores', $admin, [], 15, $callback);
        $this->assertEquals(1, $callbackCallCount);

        // Second call - cache hit
        $result2 = $this->cacheService->getList('stores', $admin, [], 15, $callback);
        $this->assertEquals(1, $callbackCallCount); // Should not increment

        $this->assertEquals($result1->total(), $result2->total());
    }

    public function test_get_show_caches_result(): void
    {
        $store = Store::factory()->create();

        $callbackCallCount = 0;
        $callback = function () use ($store, &$callbackCallCount) {
            $callbackCallCount++;
            return Store::find($store->id);
        };

        // First call - cache miss
        $result1 = $this->cacheService->getShow('stores', $store->id, $callback);
        $this->assertEquals(1, $callbackCallCount);

        // Second call - cache hit
        $result2 = $this->cacheService->getShow('stores', $store->id, $callback);
        $this->assertEquals(1, $callbackCallCount); // Should not increment

        $this->assertEquals($result1->id, $result2->id);
    }

    public function test_invalidate_show_removes_cache(): void
    {
        $store = Store::factory()->create();

        $callbackCallCount = 0;
        $callback = function () use ($store, &$callbackCallCount) {
            $callbackCallCount++;
            return Store::find($store->id);
        };

        // Cache it
        $this->cacheService->getShow('stores', $store->id, $callback);
        $this->assertEquals(1, $callbackCallCount);

        // Invalidate
        $this->cacheService->invalidateShow('stores', $store->id);

        // Should call callback again
        $this->cacheService->getShow('stores', $store->id, $callback);
        $this->assertEquals(2, $callbackCallCount);
    }

    public function test_list_cache_key_includes_user_context(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager', 'store_id' => Store::factory()->create()->id]);

        $callbackCallCount = 0;
        $callback = function () use (&$callbackCallCount) {
            $callbackCallCount++;
            return $this->storeService->list(
                User::factory()->create(['role' => 'admin']),
                [],
                15
            );
        };

        // Different users should have different cache keys
        $this->cacheService->getList('stores', $admin, [], 15, $callback);
        $this->cacheService->getList('stores', $manager, [], 15, $callback);

        // Both should call callback (different cache keys)
        $this->assertEquals(2, $callbackCallCount);
    }
}
