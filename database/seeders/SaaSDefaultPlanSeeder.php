<?php

namespace Database\Seeders;

// تعليق عربي: سيدر لتهيئة الباقة التجريبية المجانية الافتراضية للساس (SaaS Free Trial) لتمكين العملاء من تجربة المنصة بحدود ضيقة.

use Illuminate\Database\Seeder;
use App\Models\Plan;

class SaaSDefaultPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'free_trial'],
            [
                'name' => 'الباقة التجريبية المجانية',
                'description' => 'باقة تجريبية مجانية تتيح لك اختبار المنصة والتعرف على خدماتها الأساسية بحدود محدودة.',
                'price' => 0.00,
                'currency' => 'EGP',
                'duration' => null,
                'duration_unit' => null,
                'trial_days' => 0,
                'is_active' => true,
                'type' => 'free',
                'features' => [
                    'payment_gateways' => false,
                    'export_import' => false,
                    'mail_settings' => true,
                    'warehouses_multi' => false,
                    'installment_system' => false,
                    'activity_logs' => true,
                    'reports_advanced' => false,
                    'max_users' => 3,
                    'max_products' => 10,
                    'max_invoices' => 5,
                    'max_warehouses' => 1,
                ]
            ]
        );
    }
}
