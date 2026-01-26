<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SaleSeeder;
use Database\Seeders\StoreProductSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_seeder_creates_stores(): void
    {
        $this->seed(StoreSeeder::class);

        $this->assertDatabaseHas('stores', ['name' => 'Boss Pods Downtown']);
        $this->assertDatabaseHas('stores', ['name' => 'Boss Pods Mall']);
        $this->assertDatabaseHas('stores', ['name' => 'Boss Pods Airport']);
        $this->assertEquals(3, Store::count());
    }

    public function test_store_seeder_is_idempotent(): void
    {
        $this->seed(StoreSeeder::class);
        $count1 = Store::count();

        $this->seed(StoreSeeder::class);
        $count2 = Store::count();

        $this->assertEquals($count1, $count2);
    }

    public function test_product_seeder_creates_products(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertDatabaseHas('products', [
            'brand' => 'VapePro',
            'name' => 'Classic',
            'flavor' => 'Tobacco',
        ]);
        $this->assertGreaterThanOrEqual(5, Product::count());
    }

    public function test_product_seeder_is_idempotent(): void
    {
        $this->seed(ProductSeeder::class);
        $count1 = Product::count();

        $this->seed(ProductSeeder::class);
        $count2 = Product::count();

        $this->assertEquals($count1, $count2);
    }

    public function test_admin_user_seeder_creates_admin(): void
    {
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);
        config(['boss_pods.seed.admin_name' => 'Test Admin']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@test.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('admin', $admin->role);
        $this->assertNull($admin->store_id);
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_user_seeder_is_idempotent(): void
    {
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);

        $this->seed(AdminUserSeeder::class);
        $count1 = User::where('email', 'admin@test.com')->count();

        $this->seed(AdminUserSeeder::class);
        $count2 = User::where('email', 'admin@test.com')->count();

        $this->assertEquals(1, $count1);
        $this->assertEquals(1, $count2);
    }

    public function test_admin_user_seeder_creates_demo_users_when_demo_enabled(): void
    {
        config(['boss_pods.seed.demo' => true]);
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);

        // Create stores first (required for demo users)
        $this->seed(StoreSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $manager = User::where('email', 'manager@boss-pods.test')->first();
        $this->assertNotNull($manager);
        $this->assertEquals('manager', $manager->role);
        $this->assertNotNull($manager->store_id);

        $seller = User::where('email', 'seller1@boss-pods.test')->first();
        $this->assertNotNull($seller);
        $this->assertEquals('seller', $seller->role);
        $this->assertNotNull($seller->store_id);
    }

    public function test_store_product_seeder_creates_store_products(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(StoreProductSeeder::class);

        $storeProducts = StoreProduct::all();
        $this->assertGreaterThan(0, $storeProducts->count());

        // Verify sale_price >= cost_price
        foreach ($storeProducts as $storeProduct) {
            $this->assertGreaterThanOrEqual(
                (float) $storeProduct->cost_price,
                (float) $storeProduct->sale_price,
                "Store product {$storeProduct->id} has sale_price < cost_price"
            );
        }
    }

    public function test_store_product_seeder_is_idempotent(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(StoreProductSeeder::class);
        $count1 = StoreProduct::count();

        $this->seed(StoreProductSeeder::class);
        $count2 = StoreProduct::count();

        $this->assertEquals($count1, $count2);
    }

    public function test_customer_seeder_creates_customers(): void
    {
        $this->seed(CustomerSeeder::class);

        $this->assertDatabaseHas('customers', ['phone' => '+1-555-1001']);
        $this->assertGreaterThanOrEqual(5, Customer::count());
    }

    public function test_customer_seeder_sets_total_purchases_to_zero(): void
    {
        $this->seed(CustomerSeeder::class);

        $customers = Customer::all();
        foreach ($customers as $customer) {
            $this->assertEquals(0, $customer->total_purchases);
        }
    }

    public function test_sale_seeder_creates_sales_via_service(): void
    {
        // Enable demo seeders
        config(['boss_pods.seed.demo' => true]);
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);

        $this->seed(StoreSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(AdminUserSeeder::class);
        $this->seed(StoreProductSeeder::class);
        $this->seed(CustomerSeeder::class);

        // Ensure store products have stock
        StoreProduct::query()->update(['stock_quantity' => 50]);

        $this->seed(SaleSeeder::class);

        $sales = Sale::all();
        $this->assertGreaterThan(0, $sales->count());

        // Verify stock was decremented
        $storeProduct = StoreProduct::first();
        $this->assertLessThan(50, $storeProduct->stock_quantity);
    }

    public function test_database_seeder_runs_all_seeders_in_order(): void
    {
        config(['boss_pods.seed.demo' => true]);
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);

        $this->seed();

        // Verify all seeders ran
        $this->assertGreaterThan(0, Store::count());
        $this->assertGreaterThan(0, Product::count());
        $this->assertNotNull(User::where('email', 'admin@test.com')->first());
        $this->assertGreaterThan(0, StoreProduct::count());
        $this->assertGreaterThan(0, Customer::count());
    }

    public function test_database_seeder_only_runs_admin_in_production(): void
    {
        // Set environment to production and disable demo seeders
        config(['app.env' => 'production']);
        config(['boss_pods.seed.demo' => false]);
        config(['boss_pods.seed.admin_email' => 'admin@test.com']);
        config(['boss_pods.seed.admin_password' => 'password123']);

        $this->seed();

        // Only admin should exist
        $this->assertEquals(1, User::count());
        $this->assertEquals(0, Store::count());
        $this->assertEquals(0, Product::count());
    }
}
