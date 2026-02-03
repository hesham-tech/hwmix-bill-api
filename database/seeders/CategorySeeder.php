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
            'description' => 'أحدث الهواتف الذكية',
            'company_id' => 1,
            'created_by' => 1,
        ]);
        Category::create([
            'name' => 'إكسسوارات',
            'description' => 'إكسسوارات الهواتف والأجهزة',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}
