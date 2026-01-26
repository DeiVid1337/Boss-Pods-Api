<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\CustomerService;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    private CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customerService = new CustomerService();
    }

    public function test_list_applies_sort_by_name_asc(): void
    {
        Customer::factory()->create(['name' => 'Zebra Customer']);
        Customer::factory()->create(['name' => 'Alpha Customer']);
        Customer::factory()->create(['name' => 'Beta Customer']);

        $result = $this->customerService->list(['sort_by' => 'name', 'sort_order' => 'asc'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha Customer', $items[0]->name);
        $this->assertEquals('Beta Customer', $items[1]->name);
        $this->assertEquals('Zebra Customer', $items[2]->name);
    }

    public function test_list_applies_sort_by_total_purchases_desc(): void
    {
        Customer::factory()->create(['total_purchases' => 10]);
        Customer::factory()->create(['total_purchases' => 30]);
        Customer::factory()->create(['total_purchases' => 20]);

        $result = $this->customerService->list(['sort_by' => 'total_purchases', 'sort_order' => 'desc'], 15);

        $items = $result->items();
        $this->assertEquals(30, $items[0]->total_purchases);
        $this->assertEquals(20, $items[1]->total_purchases);
        $this->assertEquals(10, $items[2]->total_purchases);
    }

    public function test_list_defaults_to_name_asc_when_invalid_sort_by(): void
    {
        Customer::factory()->create(['name' => 'Zebra']);
        Customer::factory()->create(['name' => 'Alpha']);

        $result = $this->customerService->list(['sort_by' => 'invalid'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha', $items[0]->name);
    }
}
