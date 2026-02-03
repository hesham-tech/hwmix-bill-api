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
            'description' => 'شركة آبل العالمية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
        Brand::create([
            'name' => 'Samsung',
            'description' => 'شركة سامسونج الكورية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}
