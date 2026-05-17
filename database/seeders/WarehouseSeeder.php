<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(
            ['name' => 'المخزن الرئيسي', 'company_id' => 1],
            [
                'location' => 'الموقع الرئيسي',
                'manager' => 'مدير المخزن',
                'capacity' => 1000,
                'status' => 'active',
                'created_by' => 1,
            ]
        );
    }
}
