<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use Tests\TestCase;

class StoreServiceTest extends TestCase
{
    private StoreService $storeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeService = new StoreService();
    }

    public function test_list_applies_search_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Store Alpha']);
        Store::factory()->create(['name' => 'Store Beta']);
        Store::factory()->create(['name' => 'Alpha Store']);

        $result = $this->storeService->list($admin, ['search' => 'Alpha'], 15);

        $this->assertCount(2, $result->items());
        foreach ($result->items() as $store) {
            $this->assertStringContainsStringIgnoringCase('Alpha', $store->name);
        }
    }

    public function test_list_applies_sort_by_name_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Zebra Store']);
        Store::factory()->create(['name' => 'Alpha Store']);
        Store::factory()->create(['name' => 'Beta Store']);

        $result = $this->storeService->list($admin, ['sort_by' => 'name', 'sort_order' => 'asc'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha Store', $items[0]->name);
        $this->assertEquals('Beta Store', $items[1]->name);
        $this->assertEquals('Zebra Store', $items[2]->name);
    }

    public function test_list_applies_sort_by_name_desc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Alpha Store']);
        Store::factory()->create(['name' => 'Beta Store']);
        Store::factory()->create(['name' => 'Zebra Store']);

        $result = $this->storeService->list($admin, ['sort_by' => 'name', 'sort_order' => 'desc'], 15);

        $items = $result->items();
        $this->assertEquals('Zebra Store', $items[0]->name);
        $this->assertEquals('Beta Store', $items[1]->name);
        $this->assertEquals('Alpha Store', $items[2]->name);
    }

    public function test_list_defaults_to_name_asc_when_invalid_sort_by(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Store::factory()->create(['name' => 'Zebra Store']);
        Store::factory()->create(['name' => 'Alpha Store']);

        $result = $this->storeService->list($admin, ['sort_by' => 'invalid_column'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha Store', $items[0]->name);
    }
}
