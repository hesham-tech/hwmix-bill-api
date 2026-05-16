<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للأقسام (2 أقسام)
        Category::create([
            'name' => 'موبايلات',
            'slug' => 'mobiles',
            'description' => 'أحدث الهواتف الذكية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
        Category::create([
            'name' => 'إكسسوارات',
            'slug' => 'accessories',
            'description' => 'إكسسوارات الهواتف والأجهزة',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}
