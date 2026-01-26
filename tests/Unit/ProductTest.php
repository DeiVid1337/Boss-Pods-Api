<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_full_name_accessor_returns_expected_string(): void
    {
        $product = Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);

        $this->assertEquals('BrandX - Pod1 - Mint', $product->full_name);
    }

    public function test_product_has_store_products_relationship(): void
    {
        $product = Product::factory()->create();
        \App\Models\Store::factory()->create();
        \App\Models\StoreProduct::factory()->count(2)->create(['product_id' => $product->id]);

        $this->assertCount(2, $product->storeProducts);
    }

    public function test_product_unique_constraint_on_brand_name_flavor(): void
    {
        Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Product::factory()->create([
            'brand' => 'BrandX',
            'name' => 'Pod1',
            'flavor' => 'Mint',
        ]);
    }
}
