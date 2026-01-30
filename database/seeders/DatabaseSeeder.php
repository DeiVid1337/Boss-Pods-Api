<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;


    public function run(): void
    {
        $seeders = [];

        if (config('boss_pods.seed.production')) {
            $seeders = [
                StoreSeeder::class,
                UserSeeder::class,
            ];
        } elseif (config('boss_pods.seed.demo', true)) {
            $seeders = [
                StoreSeeder::class,
                ProductSeeder::class,
                UserSeeder::class,
                StoreProductSeeder::class,
            ];
        }

        $this->call($seeders);
    }
}
