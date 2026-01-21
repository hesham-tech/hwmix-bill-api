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
        // User::factory(10)->create();

        $this->call([
                // ============================================
                // المرحلة 1: الصلاحيات والبيانات الأساسية
                // ============================================
            PermissionsSeeder::class,

                // ============================================
                // المرحلة 2: أنواع الفواتير وطرق الدفع (قبل الشركات!)
                // ============================================
            InvoiceTypeSeeder::class,
            PaymentMethodSeeder::class,
            CashBoxTypeSeeder::class,

                // ============================================
                // المرحلة 3: الشركات والمستخدمين
                // (سيتم ربط InvoiceTypes تلقائياً عبر CompanyObserver)
                // ============================================
            CompanySeeder::class,
            UserSeeder::class,
            ExpenseCategorySeeder::class,

                // ============================================
                // المرحلة 4: بيانات المنتجات والمخازن
                //============================================
            WarehouseSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            ProductSeeder::class,

            // InvoiceItemSeeder::class,
            // RevenueSeeder::class,
        ]);
    }
}
