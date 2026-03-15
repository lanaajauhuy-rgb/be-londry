<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrderItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Contoh 5 order items
        DB::table('order_items')->insert([
            [
                'order_id' => 1,            // Pastikan order_id 1 sudah ada di orders
                'service_id' => 1,          // Pastikan service_id 1 sudah ada di services
                'service_type' => 'kiloan',
                'item_name' => 'Cuci Kiloan',
                'qty' => 1,
                'weight_kg' => 5.50,
                'unit_price' => 10000,
                'line_total' => 55000,
                'notes' => 'Tidak pakai pewangi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => 1,
                'service_id' => 2,
                'service_type' => 'per_item',
                'item_name' => 'Setrika Baju',
                'qty' => 3,
                'weight_kg' => null,
                'unit_price' => 5000,
                'line_total' => 15000,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'order_id' => 1,
                'service_id' => 1,
                'service_type' => 'kiloan',
                'item_name' => 'Cuci Kiloan',
                'qty' => 1,
                'weight_kg' => 7.25,
                'unit_price' => 10000,
                'line_total' => 72500,
                'notes' => 'Pakaian putih dipisah',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
