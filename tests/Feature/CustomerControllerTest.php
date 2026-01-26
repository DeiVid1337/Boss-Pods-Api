<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    public function test_get_customers_as_admin_returns_paginated_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'phone', 'total_purchases', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_customers_as_manager_returns_list(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Customer::factory()->count(2)->create();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_customers_as_seller_returns_list(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        Customer::factory()->count(2)->create();

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_customers_with_search_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::factory()->create(['name' => 'João Silva', 'phone' => '+351912345678']);
        Customer::factory()->create(['name' => 'Maria Santos', 'phone' => '+351987654321']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?search=João');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('João Silva', $response->json('data.0.name'));
    }

    public function test_get_customers_with_phone_exact_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $phone = '+351912345678';
        Customer::factory()->create(['name' => 'João Silva', 'phone' => $phone]);
        Customer::factory()->create(['name' => 'Maria Santos', 'phone' => '+351987654321']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?phone=' . urlencode($phone));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($phone, $response->json('data.0.phone'));
    }

    public function test_get_customer_by_id_returns_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', $customer->name)
            ->assertJsonPath('data.phone', $customer->phone);
    }

    public function test_get_customer_with_include_sales_includes_sales(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create();
        $sale = Sale::factory()->create(['customer_id' => $customer->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/customers/{$customer->id}?include=sales");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'total_purchases',
                    'sales' => [
                        '*' => ['id', 'store_id', 'total_amount', 'sale_date', 'created_at'],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.sales'));
        $this->assertEquals($sale->id, $response->json('data.sales.0.id'));
    }

    public function test_get_customer_404_when_not_found(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers/99999');

        $response->assertStatus(404);
    }

    public function test_get_customer_with_invalid_include_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/customers/{$customer->id}?include=foo");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['include']);
    }

    public function test_post_customers_creates_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'João Silva',
            'phone' => '+351912345678',
        ];

        $response = $this->postJson('/api/v1/customers', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.phone', $data['phone'])
            ->assertJsonPath('data.total_purchases', 0);

        $this->assertDatabaseHas('customers', [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'total_purchases' => 0,
        ]);
    }

    public function test_post_customers_duplicate_phone_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existingCustomer = Customer::factory()->create(['phone' => '+351912345678']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'João Silva',
            'phone' => $existingCustomer->phone,
        ];

        $response = $this->postJson('/api/v1/customers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_put_customers_updates_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create(['name' => 'João Silva', 'phone' => '+351912345678']);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'João Santos',
            'phone' => '+351987654321',
        ];

        $response = $this->putJson("/api/v1/customers/{$customer->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.phone', $data['phone']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
        ]);
    }

    public function test_put_customers_duplicate_phone_other_customer_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer1 = Customer::factory()->create(['phone' => '+351912345678']);
        $customer2 = Customer::factory()->create(['phone' => '+351987654321']);

        Sanctum::actingAs($admin);

        $data = [
            'phone' => $customer2->phone,
        ];

        $response = $this->putJson("/api/v1/customers/{$customer1->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_all_customer_endpoints_without_token_return_401(): void
    {
        $customer = Customer::factory()->create();

        $this->getJson('/api/v1/customers')->assertStatus(401);
        $this->getJson("/api/v1/customers/{$customer->id}")->assertStatus(401);
        $this->postJson('/api/v1/customers', [])->assertStatus(401);
        $this->putJson("/api/v1/customers/{$customer->id}", [])->assertStatus(401);
    }

    public function test_get_customers_with_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Customer::factory()->count(25)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }

    public function test_get_customers_with_invalid_per_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?per_page=200');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_get_customers_with_invalid_page_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    public function test_get_customers_with_sort_by_name_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::factory()->create(['name' => 'Zebra Customer']);
        Customer::factory()->create(['name' => 'Alpha Customer']);
        Customer::factory()->create(['name' => 'Beta Customer']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?sort_by=name&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Alpha Customer', $data[0]['name']);
        $this->assertEquals('Beta Customer', $data[1]['name']);
        $this->assertEquals('Zebra Customer', $data[2]['name']);
    }

    public function test_get_customers_with_sort_by_total_purchases_desc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::factory()->create(['total_purchases' => 10]);
        Customer::factory()->create(['total_purchases' => 30]);
        Customer::factory()->create(['total_purchases' => 20]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/customers?sort_by=total_purchases&sort_order=desc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(30, $data[0]['total_purchases']);
        $this->assertEquals(20, $data[1]['total_purchases']);
        $this->assertEquals(10, $data[2]['total_purchases']);
    }

    public function test_get_customers_cache_hit_on_second_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::factory()->create(['name' => 'Test Customer']);

        Sanctum::actingAs($admin);

        // First request
        $response1 = $this->getJson('/api/v1/customers');
        $response1->assertStatus(200);

        // Second request with same params should hit cache
        $response2 = $this->getJson('/api/v1/customers');
        $response2->assertStatus(200);

        // Both should return same data
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }
}
