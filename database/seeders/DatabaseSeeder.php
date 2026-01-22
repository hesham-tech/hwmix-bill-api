<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
                // 1. الأساسيات والشركات (The Foundation)
            CompanySeeder::class,

                // 2. الهوية والصلاحيات (Identity & Access)
            PermissionsSeeder::class,
            UserSeeder::class,

                // 3. الإعدادات والأنواع (Configuration)
            InvoiceTypeSeeder::class,
            PaymentMethodSeeder::class,
            CashBoxTypeSeeder::class,
            WarehouseSeeder::class,
            ExpenseCategorySeeder::class,

                // 4. البيانات التشغيلية (Operational Data)
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
