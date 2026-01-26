<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    private SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saleService = new SaleService();
    }

    public function test_list_applies_search_filter_on_notes(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        Sale::factory()->create(['store_id' => $store->id, 'notes' => 'Customer requested delivery']);
        Sale::factory()->create(['store_id' => $store->id, 'notes' => 'Regular sale']);
        Sale::factory()->create(['store_id' => $store->id, 'notes' => null]);

        $result = $this->saleService->list($store, $user, ['search' => 'delivery'], 15);

        $this->assertCount(1, $result->items());
        $this->assertStringContainsStringIgnoringCase('delivery', $result->items()[0]->notes);
    }

    public function test_list_applies_sort_by_sale_date_desc_default(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()->subDays(2)]);
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()]);
        Sale::factory()->create(['store_id' => $store->id, 'sale_date' => now()->subDay()]);

        $result = $this->saleService->list($store, $user, [], 15);

        $items = $result->items();
        // Most recent first (desc)
        $this->assertTrue($items[0]->sale_date->isAfter($items[1]->sale_date));
    }

    public function test_list_applies_sort_by_total_amount_asc(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 100.00]);
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 50.00]);
        Sale::factory()->create(['store_id' => $store->id, 'total_amount' => 75.00]);

        $result = $this->saleService->list($store, $user, ['sort_by' => 'total_amount', 'sort_order' => 'asc'], 15);

        $items = $result->items();
        $this->assertEquals('50.00', $items[0]->total_amount);
        $this->assertEquals('75.00', $items[1]->total_amount);
        $this->assertEquals('100.00', $items[2]->total_amount);
    }
}
