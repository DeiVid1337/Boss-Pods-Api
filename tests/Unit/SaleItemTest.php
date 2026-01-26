<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Tests\TestCase;

class SaleItemTest extends TestCase
{
    public function test_sale_item_has_sale_relationship(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['store_id' => $store->id, 'user_id' => $user->id]);
        $saleItem = SaleItem::factory()->create(['sale_id' => $sale->id]);

        $this->assertInstanceOf(Sale::class, $saleItem->sale);
        $this->assertEquals($sale->id, $saleItem->sale->id);
    }

    public function test_sale_item_has_store_product_relationship(): void
    {
        $store = Store::factory()->create();
        $product = \App\Models\Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);
        $saleItem = SaleItem::factory()->create(['store_product_id' => $storeProduct->id]);

        $this->assertInstanceOf(StoreProduct::class, $saleItem->storeProduct);
        $this->assertEquals($storeProduct->id, $saleItem->storeProduct->id);
    }
}
