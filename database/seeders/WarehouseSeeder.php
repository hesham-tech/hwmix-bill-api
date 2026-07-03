<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // استخدام DB::table مباشرة لتجاوز Observers و Blameable أثناء الـ Seeding
        DB::table('warehouses')->insertOrIgnore([
            'name'       => 'المخزن الرئيسي',
            'status'     => 'active',
            'company_id' => 1,
            'created_by' => 1,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
