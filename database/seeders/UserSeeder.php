<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{

    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        $adminPassword = config('boss_pods.seed.admin_password') 
            ?: config('boss_pods.seed.demo_password', 'password');
        $demoPassword = config('boss_pods.seed.demo_password', 'password');

        User::firstOrCreate(
            ['email' => 'admin1@boss-pods.com'],
            [
                'name' => 'Admin 1',
                'email' => 'admin1@boss-pods.com',
                'password' => $adminPassword,
                'role' => 'admin',
                'store_id' => null,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin2@boss-pods.com'],
            [
                'name' => 'Admin 2',
                'email' => 'admin2@boss-pods.com',
                'password' => $adminPassword,
                'role' => 'admin',
                'store_id' => null,
                'is_active' => true,
            ]
        );

        $palmasStore = Store::where('name', 'Palmas')->first();
        if ($palmasStore) {
            User::firstOrCreate(
                ['email' => 'sayd@boss-pods.com'],
                [
                    'name' => 'Sayd',
                    'email' => 'sayd@boss-pods.com',
                    'password' => $demoPassword,
                    'role' => 'manager',
                    'store_id' => $palmasStore->id,
                    'is_active' => true,
                ]
            );
        }

        $guarujaStore = Store::where('name', 'Guarujá')->first();
        if ($guarujaStore) {
            User::firstOrCreate(
                ['email' => 'vendedor1@boss-pods.com'],
                [
                    'name' => 'Vendedor 1',
                    'email' => 'vendedor1@boss-pods.com',
                    'password' => $demoPassword,
                    'role' => 'seller',
                    'store_id' => $guarujaStore->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
