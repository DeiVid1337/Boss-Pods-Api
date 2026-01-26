<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{


    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        $stores = [
            [
                'name' => 'Palmas',
                'address' => 'Avenida Beira Mar, 1234 - Centro, Guarujá - SP, 11400-000',
                'phone' => '(13) 3355-1234',
                'is_active' => true,
            ],
            [
                'name' => 'Guarujá',
                'address' => 'Rua das Flores, 567 - Praia da Enseada, Guarujá - SP, 11400-000',
                'phone' => '(13) 3355-5678',
                'is_active' => true,
            ],
        ];

        foreach ($stores as $storeData) {
            Store::firstOrCreate(
                ['name' => $storeData['name']],
                $storeData
            );
        }
    }
}
