<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name' => 'هونكس المبدعة للتجارة',
            'email' => 'info@hwunex.com',
            'phone' => '01006444991',
            'address' => '123 شارع التكنولوجيا - التجمع الخامس - القاهرة',
            'field' => 'تجارة الأجهزة الإلكترونية',
            'owner_name' => 'هشام محمد',
            'created_by' => 1,
        ]);
    }
}
