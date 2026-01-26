<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class SaleTest extends TestCase
{
    public function test_sale_scope_for_store_filters_by_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $user = User::factory()->create();

        Sale::factory()->create(['store_id' => $store1->id, 'user_id' => $user->id]);
        Sale::factory()->create(['store_id' => $store1->id, 'user_id' => $user->id]);
        Sale::factory()->create(['store_id' => $store2->id, 'user_id' => $user->id]);

        $store1Sales = Sale::forStore($store1->id)->get();

        $this->assertCount(2, $store1Sales);
        foreach ($store1Sales as $sale) {
            $this->assertEquals($store1->id, $sale->store_id);
        }
    }

    public function test_sale_scope_for_user_filters_by_user(): void
    {
        $store = Store::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user1->id]);
        Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user1->id]);
        Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user2->id]);

        $user1Sales = Sale::forUser($user1->id)->get();

        $this->assertCount(2, $user1Sales);
        foreach ($user1Sales as $sale) {
            $this->assertEquals($user1->id, $sale->user_id);
        }
    }

    public function test_sale_scope_by_date_range_filters_by_date_range(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();

        $start = Carbon::parse('2025-01-01');
        $end = Carbon::parse('2025-01-31');

        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-01-15'),
        ]);
        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-01-20'),
        ]);
        Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'sale_date' => Carbon::parse('2025-02-01'),
        ]);

        $sales = Sale::byDateRange($start, $end)->get();

        $this->assertCount(2, $sales);
        foreach ($sales as $sale) {
            $this->assertTrue($sale->sale_date >= $start && $sale->sale_date <= $end);
        }
    }

    public function test_sale_has_store_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user->id]);

        $this->assertInstanceOf(Store::class, $sale->store);
        $this->assertEquals($store->id, $sale->store->id);
    }

    public function test_sale_has_user_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $sale->user);
        $this->assertEquals($user->id, $sale->user->id);
    }

    public function test_sale_has_customer_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $customer = \App\Models\Customer::factory()->create();
        $sale = Sale::factory()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertInstanceOf(\App\Models\Customer::class, $sale->customer);
        $this->assertEquals($customer->id, $sale->customer->id);
    }

    public function test_sale_has_sale_items_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user->id]);
        \App\Models\SaleItem::factory()->count(2)->create(['sale_id' => $sale->id]);

        $this->assertCount(2, $sale->saleItems);
    }
}
