<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customers')->upsert([
            [
                'customer_code' => 'CUS001',
                'name' => 'John Doe',
                'phone' => '1234567890',
                'email' => 'john.doe@example.com',
                'address' => '123 Main St',
                'notes' => 'Customer notes',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'customer_code' => 'CUS002',
                'name' => 'Jane Smith',
                'phone' => '9876543210',
                'email' => 'jane.smith@example.com',
                'address' => '456 Elm St',
                'notes' => 'Another customer notes',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ], ['customer_code'], ['name', 'phone', 'email', 'address', 'notes', 'updated_at']);
    }
}
