<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // بيانات للماركات
        Brand::firstOrCreate(
            ['name' => 'Apple', 'company_id' => 1],
            [
                'slug' => 'apple',
                'description' => 'شركة آبل العالمية',
                'created_by' => 1,
            ]
        );
        Brand::firstOrCreate(
            ['name' => 'Samsung', 'company_id' => 1],
            [
                'slug' => 'samsung',
                'description' => 'شركة سامسونج الكورية',
                'created_by' => 1,
            ]
        );
    }
}
