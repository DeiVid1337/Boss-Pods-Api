<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Sale;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_customer_scope_by_phone_filters_by_phone(): void
    {
        $phone = '+351912345678';
        $customer1 = Customer::factory()->create(['phone' => $phone]);
        Customer::factory()->create(['phone' => '+351987654321']);

        $results = Customer::byPhone($phone)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($customer1->id, $results->first()->id);
        $this->assertEquals($phone, $results->first()->phone);
    }

    public function test_customer_increment_purchases_increases_total_purchases(): void
    {
        $customer = Customer::factory()->create(['total_purchases' => 5]);

        $customer->incrementPurchases(3);

        $this->assertEquals(8, $customer->fresh()->total_purchases);
    }

    public function test_customer_has_sales_relationship(): void
    {
        $customer = Customer::factory()->create();
        $sale = Sale::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($customer->sales->contains($sale));
        $this->assertEquals($customer->id, $sale->customer_id);
    }
}
