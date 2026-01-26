<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{


    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        $products = [
            ['brand' => 'Vaporesso', 'name' => 'XROS 3', 'flavor' => 'Mint'],
            ['brand' => 'Vaporesso', 'name' => 'XROS 3', 'flavor' => 'Tobacco'],
            ['brand' => 'Vaporesso', 'name' => 'XROS 3', 'flavor' => 'Fruit'],
            ['brand' => 'SMOK', 'name' => 'Nord 5', 'flavor' => 'Mint'],
            ['brand' => 'SMOK', 'name' => 'Nord 5', 'flavor' => 'Tobacco'],
            ['brand' => 'SMOK', 'name' => 'Nord 5', 'flavor' => 'Vanilla'],
            ['brand' => 'Uwell', 'name' => 'Caliburn G3', 'flavor' => 'Mint'],
            ['brand' => 'Uwell', 'name' => 'Caliburn G3', 'flavor' => 'Fruit'],
            ['brand' => 'Uwell', 'name' => 'Caliburn G3', 'flavor' => 'Coffee'],
            ['brand' => 'Geekvape', 'name' => 'Aegis Pod', 'flavor' => 'Mint'],
            ['brand' => 'Geekvape', 'name' => 'Aegis Pod', 'flavor' => 'Tobacco'],
            ['brand' => 'Geekvape', 'name' => 'Aegis Pod', 'flavor' => 'Fruit'],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                [
                    'brand' => $productData['brand'],
                    'name' => $productData['name'],
                    'flavor' => $productData['flavor'],
                ],
                $productData
            );
        }
    }
}
