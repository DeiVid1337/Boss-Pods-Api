<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{

    public function run(): void
    {
        $this->createAdminUser();

        if (config('boss_pods.seed.demo')) {
            $this->createDemoUsers();
        }
    }


    private function createAdminUser(): void
    {
        $email = config('boss_pods.seed.admin_email');
        $password = config('boss_pods.seed.admin_password');
        $name = config('boss_pods.seed.admin_name');

        if (app()->environment('production') && empty($password)) {
            $this->command->error('SEED_ADMIN_PASSWORD is required in production environment.');
            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command->info("Admin user with email {$email} already exists. Skipping.");
            return;
        }

        if (empty($password)) {
            $password = config('boss_pods.seed.demo_password', 'password');
            $this->command->warn("Using default password for admin user. Set SEED_ADMIN_PASSWORD in production!");
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'store_id' => null,
            'is_active' => true,
        ]);

        $this->command->info("Admin user created: {$email}");
    }


    private function createDemoUsers(): void
    {
        $stores = Store::all();
        if ($stores->isEmpty()) {
            $this->command->warn('No stores found. Skipping demo users creation.');
            return;
        }

        $demoPassword = config('boss_pods.seed.demo_password', 'password');
        $firstStore = $stores->first();
        $secondStore = $stores->skip(1)->first();

        if (!User::where('email', 'manager@boss-pods.test')->exists()) {
            User::factory()
                ->manager($firstStore)
                ->create([
                    'name' => 'Demo Manager',
                    'email' => 'manager@boss-pods.test',
                    'password' => $demoPassword,
                ]);
            $this->command->info('Demo manager created: manager@boss-pods.test');
        }

        if (!User::where('email', 'seller1@boss-pods.test')->exists()) {
            User::factory()
                ->seller($firstStore)
                ->create([
                    'name' => 'Demo Seller 1',
                    'email' => 'seller1@boss-pods.test',
                    'password' => $demoPassword,
                ]);
            $this->command->info('Demo seller 1 created: seller1@boss-pods.test');
        }

        if ($secondStore && !User::where('email', 'seller2@boss-pods.test')->exists()) {
            User::factory()
                ->seller($secondStore)
                ->create([
                    'name' => 'Demo Seller 2',
                    'email' => 'seller2@boss-pods.test',
                    'password' => $demoPassword,
                ]);
            $this->command->info('Demo seller 2 created: seller2@boss-pods.test');
        }
    }
}
