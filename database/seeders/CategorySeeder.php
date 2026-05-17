<?php

namespace Database\Seeders;

use Modules\Inventory\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للأقسام (2 أقسام)
        Category::firstOrCreate(
            ['name' => 'موبايلات', 'company_id' => 1],
            [
                'slug' => 'mobiles',
                'description' => 'أحدث الهواتف الذكية',
                'created_by' => 1,
            ]
        );
        Category::firstOrCreate(
            ['name' => 'إكسسوارات', 'company_id' => 1],
            [
                'slug' => 'accessories',
                'description' => 'إكسسوارات الهواتف والأجهزة',
                'created_by' => 1,
            ]
        );
    }
}
