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
                // 1. الأساسيات (System Types)
            InvoiceTypeSeeder::class,
            PaymentMethodSeeder::class,
            CashBoxTypeSeeder::class,

                // 2. الشركة والبيانات المرتبطة
            CompanySeeder::class,
            WarehouseSeeder::class,
            ExpenseCategorySeeder::class,

                // 3. الصلاحيات والمستخدمين
            PermissionsSeeder::class, // ينشئ الصلاحيات والأدوار والـ Super Admin
            UserSeeder::class,        // ينشئ عملاء للتجربة (جملة وقطاعي)

                // 4. المخزون والمنتجات
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
