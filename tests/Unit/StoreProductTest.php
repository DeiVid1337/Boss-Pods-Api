<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Tests\TestCase;

class StoreProductTest extends TestCase
{
    public function test_store_product_scope_low_stock_returns_only_low_stock_items(): void
    {
        $store = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product1->id,
            'stock_quantity' => 5,
            'min_stock_level' => 10,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product2->id,
            'stock_quantity' => 15,
            'min_stock_level' => 10,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product3->id,
            'stock_quantity' => 10,
            'min_stock_level' => 10,
        ]);

        $lowStockProducts = StoreProduct::lowStock()->get();

        $this->assertCount(2, $lowStockProducts);
        foreach ($lowStockProducts as $storeProduct) {
            $this->assertLessThanOrEqual($storeProduct->min_stock_level, $storeProduct->stock_quantity);
        }
    }

    public function test_store_product_scope_for_store_filters_by_store(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        $product4 = Product::factory()->create();
        $product5 = Product::factory()->create();

        StoreProduct::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product1->id,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product2->id,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product3->id,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product4->id,
        ]);

        StoreProduct::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product5->id,
        ]);

        $store1Products = StoreProduct::forStore($store1->id)->get();

        $this->assertCount(2, $store1Products);
        foreach ($store1Products as $storeProduct) {
            $this->assertEquals($store1->id, $storeProduct->store_id);
        }
    }

    public function test_store_product_has_stock_returns_true_when_sufficient(): void
    {
        $storeProduct = StoreProduct::factory()->create([
            'stock_quantity' => 50,
        ]);

        $this->assertTrue($storeProduct->hasStock(30));
        $this->assertTrue($storeProduct->hasStock(50));
    }

    public function test_store_product_has_stock_returns_false_when_insufficient(): void
    {
        $storeProduct = StoreProduct::factory()->create([
            'stock_quantity' => 20,
        ]);

        $this->assertFalse($storeProduct->hasStock(30));
        $this->assertFalse($storeProduct->hasStock(21));
    }

    public function test_store_product_decrement_stock_reduces_quantity(): void
    {
        $storeProduct = StoreProduct::factory()->create([
            'stock_quantity' => 50,
        ]);

        $storeProduct->decrementStock(10);

        $this->assertEquals(40, $storeProduct->fresh()->stock_quantity);
    }

    public function test_store_product_has_store_relationship(): void
    {
        $store = Store::factory()->create();
        $storeProduct = StoreProduct::factory()->create(['store_id' => $store->id]);

        $this->assertInstanceOf(Store::class, $storeProduct->store);
        $this->assertEquals($store->id, $storeProduct->store->id);
    }

    public function test_store_product_has_product_relationship(): void
    {
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $storeProduct->product);
        $this->assertEquals($product->id, $storeProduct->product->id);
    }
}
