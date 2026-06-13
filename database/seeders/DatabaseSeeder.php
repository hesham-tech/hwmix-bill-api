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
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $this->call([
                // 1. الأساسيات (The Foundation)
            CompanySeeder::class,
            PermissionsSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentMethodSeeder::class,
            CashBoxTypeSeeder::class,
            ExpenseCategorySeeder::class,

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
            SystemUnitsSeeder::class,
            \Modules\Legal\database\seeders\LegalDatabaseSeeder::class,
        ]);

        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }
}
