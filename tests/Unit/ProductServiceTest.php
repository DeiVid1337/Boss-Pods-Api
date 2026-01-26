<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductService;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = new ProductService();
    }

    public function test_list_applies_search_filter_on_brand_name_flavor(): void
    {
        Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product1', 'flavor' => 'Mint']);
        Product::factory()->create(['brand' => 'BrandB', 'name' => 'Product2', 'flavor' => 'Mint']);
        Product::factory()->create(['brand' => 'BrandA', 'name' => 'Product3', 'flavor' => 'Vanilla']);

        $result = $this->productService->list(['search' => 'Mint'], 15);

        $this->assertCount(2, $result->items());
    }

    public function test_list_applies_sort_by_brand_asc(): void
    {
        Product::factory()->create(['brand' => 'Zebra']);
        Product::factory()->create(['brand' => 'Alpha']);
        Product::factory()->create(['brand' => 'Beta']);

        $result = $this->productService->list(['sort_by' => 'brand', 'sort_order' => 'asc'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha', $items[0]->brand);
        $this->assertEquals('Beta', $items[1]->brand);
        $this->assertEquals('Zebra', $items[2]->brand);
    }

    public function test_list_defaults_to_brand_asc_when_invalid_sort_by(): void
    {
        Product::factory()->create(['brand' => 'Zebra']);
        Product::factory()->create(['brand' => 'Alpha']);

        $result = $this->productService->list(['sort_by' => 'invalid'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha', $items[0]->brand);
    }
}
