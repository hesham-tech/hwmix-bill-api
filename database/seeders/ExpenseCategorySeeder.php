<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'إيجار', 'code' => 'RENT'],
            ['name' => 'رواتب وأجور', 'code' => 'SALARIES'],
            ['name' => 'كهرباء ومياه', 'code' => 'UTILITIES'],
            ['name' => 'صيانة وإصلاحات', 'code' => 'MAINTENANCE'],
            ['name' => 'دعاية وإعلان', 'code' => 'MARKETING'],
            ['name' => 'مصاريف بنكية', 'code' => 'BANK_FEES'],
            ['name' => 'أدوات مكتبية', 'code' => 'OFFICE_SUPPLIES'],
            ['name' => 'نثرية ومصاريف أخرى', 'code' => 'MISC'],
        ];

        $companyIds = \App\Models\Company::pluck('id');

        foreach ($companyIds as $companyId) {
            foreach ($categories as $cat) {
                \App\Models\ExpenseCategory::updateOrCreate(
                    ['code' => $cat['code'], 'company_id' => $companyId],
                    ['name' => $cat['name']]
                );
            }
        }
    }
}
