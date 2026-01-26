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

        if (app()->environment('production')) {
            return;
        } else {
            if (config('boss_pods.seed.demo', true)) {
                $seeders = [
                    StoreSeeder::class,
                    ProductSeeder::class,
                    UserSeeder::class,
                    StoreProductSeeder::class,
                ];
            }
        }

        $this->call($seeders);
    }
}
