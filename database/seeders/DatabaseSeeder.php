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
                // 1. الأساسيات (The Foundation)
            PermissionsSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentMethodSeeder::class,
            CashBoxTypeSeeder::class,
            ExpenseCategorySeeder::class,

                // 2. الشركات (الآن المراقب سيجد البيانات اللازمة)
            CompanySeeder::class,

                // 3. المستخدمين والربط
            UserSeeder::class,

                // 4. إعدادات الشركات والبيانات التشغيلية
            CompanyInvoiceTypeSeeder::class,
            CompanyPaymentMethodSeeder::class,
            CompanyCashBoxTypeSeeder::class,
            WarehouseSeeder::class,

            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
