<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // بيانات للماركات
        Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'description' => 'شركة آبل العالمية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
        Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'description' => 'شركة سامسونج الكورية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}
