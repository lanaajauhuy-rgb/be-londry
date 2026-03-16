<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CustomerSeeder::class,
            ServiceSeeder::class,
            OrderSeeder::class,
            OrderItemsSeeder::class,
            PaymentSeeder::class,
            OrderStatusHistorySeeder::class,
            DeliverySeeder::class,
        ]);
    }
}
