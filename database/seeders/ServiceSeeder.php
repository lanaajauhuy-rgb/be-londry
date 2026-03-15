<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('services')->insert([
            [
                // Layanan kiloan reguler.
                'code' => 'SVC000001',
                'name' => 'Cuci Kiloan',
                'pricing_model' => 'per_kg',
                'unit_price' => 7000,
                'estimated_hours' => 48,
                'description' => 'Layanan cuci kiloan reguler.',
                'is_active' => true,
            ],
            [
                // Layanan setrika.
                'code' => 'SVC000002',
                'name' => 'Setrika Saja',
                'pricing_model' => 'per_kg',
                'unit_price' => 5000,
                'estimated_hours' => 24,
                'description' => 'Layanan setrika tanpa cuci.',
                'is_active' => true,
            ],
            [
                // Layanan dry clean per item.
                'code' => 'SVC000003',
                'name' => 'Dry Clean Jas',
                'pricing_model' => 'per_item',
                'unit_price' => 25000,
                'estimated_hours' => 72,
                'description' => 'Dry clean untuk jas atau pakaian formal.',
                'is_active' => true,
            ],
        ]);
    }
}
