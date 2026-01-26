<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{

    public function run(): void
    {
        if (!config('boss_pods.seed.demo')) {
            return;
        }

        if (Customer::count() >= 5) {
            $this->command->info('Customers already seeded. Skipping.');
            return;
        }

        $customers = [
            ['name' => 'John Doe', 'phone' => '+1-555-1001', 'total_purchases' => 0],
            ['name' => 'Jane Smith', 'phone' => '+1-555-1002', 'total_purchases' => 0],
            ['name' => 'Bob Johnson', 'phone' => '+1-555-1003', 'total_purchases' => 0],
            ['name' => 'Alice Williams', 'phone' => '+1-555-1004', 'total_purchases' => 0],
            ['name' => 'Charlie Brown', 'phone' => '+1-555-1005', 'total_purchases' => 0],
            ['name' => 'Diana Prince', 'phone' => '+1-555-1006', 'total_purchases' => 0],
            ['name' => 'Eve Davis', 'phone' => '+1-555-1007', 'total_purchases' => 0],
            ['name' => 'Frank Miller', 'phone' => '+1-555-1008', 'total_purchases' => 0],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['phone' => $customerData['phone']],
                $customerData
            );
        }

        $this->command->info('Customers seeded successfully.');
    }
}
