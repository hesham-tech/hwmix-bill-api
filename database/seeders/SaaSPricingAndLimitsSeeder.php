<?php

namespace Database\Seeders;

// تعليق عربي: سيدر لتهيئة الموارد والحدود الأساسية للساس (Usage Metrics) وشرائح الأسعار للباقات والكوبونات للتشغيل والتحقق محلياً.

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use Carbon\Carbon;

class SaaSPricingAndLimitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. تهيئة الموارد والحدود الأساسية
        $metrics = [
            [
                'key' => 'users',
                'name' => 'عدد المستخدمين المسموح به',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\UsersUsageDriver',
                'unit' => 'مستخدم',
                'metric_type' => 'resource',
                'status' => true,
            ],
            [
                'key' => 'products',
                'name' => 'عدد المنتجات النشطة',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\ProductsUsageDriver',
                'unit' => 'منتج',
                'metric_type' => 'resource',
                'status' => true,
            ],
            [
                'key' => 'invoices',
                'name' => 'الفواتير الشهرية الصادرة',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\InvoicesUsageDriver',
                'unit' => 'فاتورة',
                'metric_type' => 'usage',
                'status' => true,
            ],
            [
                'key' => 'warehouses',
                'name' => 'عدد المستودعات / الفروع',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\WarehousesUsageDriver',
                'unit' => 'مستودع',
                'metric_type' => 'resource',
                'status' => true,
            ],
            [
                'key' => 'whatsapp_messages',
                'name' => 'رسائل الواتساب الشهرية',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\WhatsappMessagesUsageDriver',
                'unit' => 'رسالة',
                'metric_type' => 'usage',
                'status' => true,
            ],
            [
                'key' => 'api_calls',
                'name' => 'طلبات API الشهرية',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\ApiCallsUsageDriver',
                'unit' => 'طلب',
                'metric_type' => 'usage',
                'status' => true,
            ],
            [
                'key' => 'storage_size',
                'name' => 'مساحة التخزين المستهلكة',
                'resolver_class' => 'App\\Services\\SaaS\\Drivers\\StorageSizeUsageDriver',
                'unit' => 'ميجابايت',
                'metric_type' => 'resource',
                'status' => true,
            ],
        ];

        foreach ($metrics as $metric) {
            DB::table('usage_metrics')->updateOrInsert(
                ['key' => $metric['key']],
                array_merge($metric, [
                    'company_id' => null,
                    'created_by' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }

        // 2. تهيئة باقات تجريبية وتجارية إضافية
        $basicPlan = Plan::updateOrCreate(
            ['code' => 'basic_plan'],
            [
                'name' => 'الباقة الأساسية',
                'description' => 'باقة تناسب الشركات الناشئة والصغيرة لتنظيم المبيعات والمخازن.',
                'price' => 100.00,
                'currency' => 'EGP',
                'duration' => 1,
                'duration_unit' => 'month',
                'trial_days' => 7,
                'is_active' => true,
                'type' => 'paid',
                'features' => [
                    'payment_gateways' => false,
                    'export_import' => true,
                    'mail_settings' => true,
                    'warehouses_multi' => false,
                    'installment_system' => true,
                    'activity_logs' => true,
                    'reports_advanced' => false,
                    'max_users' => 5,
                    'max_products' => 100,
                    'max_invoices' => 200,
                    'max_warehouses' => 2,
                ]
            ]
        );

        $premiumPlan = Plan::updateOrCreate(
            ['code' => 'premium_plan'],
            [
                'name' => 'الباقة المتقدمة (الشركات المتميزة)',
                'description' => 'باقة متكاملة للشركات المتوسطة والكبيرة تشمل جميع المزايا بدون قيود.',
                'price' => 300.00,
                'currency' => 'EGP',
                'duration' => 1,
                'duration_unit' => 'month',
                'trial_days' => 14,
                'is_active' => true,
                'type' => 'paid',
                'features' => [
                    'payment_gateways' => true,
                    'export_import' => true,
                    'mail_settings' => true,
                    'warehouses_multi' => true,
                    'installment_system' => true,
                    'activity_logs' => true,
                    'reports_advanced' => true,
                    'max_users' => -1, // غير محدود
                    'max_products' => -1,
                    'max_invoices' => -1,
                    'max_warehouses' => -1,
                ]
            ]
        );

        // 3. تهيئة شرائح خصومات الأسعار للباقات (Pricing Tiers)
        $pricingTiers = [
            // شرائح الباقة الأساسية
            [
                'plan_id' => $basicPlan->id,
                'min_months' => 1,
                'max_months' => 2,
                'price_per_month' => 100.00,
                'discount_percent' => 0.00,
            ],
            [
                'plan_id' => $basicPlan->id,
                'min_months' => 3,
                'max_months' => 5,
                'price_per_month' => 90.00,
                'discount_percent' => 10.00,
            ],
            [
                'plan_id' => $basicPlan->id,
                'min_months' => 6,
                'max_months' => 11,
                'price_per_month' => 80.00,
                'discount_percent' => 20.00,
            ],
            [
                'plan_id' => $basicPlan->id,
                'min_months' => 12,
                'max_months' => null, // 12+ شهر
                'price_per_month' => 70.00,
                'discount_percent' => 30.00,
            ],

            // شرائح الباقة المتقدمة
            [
                'plan_id' => $premiumPlan->id,
                'min_months' => 1,
                'max_months' => 2,
                'price_per_month' => 300.00,
                'discount_percent' => 0.00,
            ],
            [
                'plan_id' => $premiumPlan->id,
                'min_months' => 3,
                'max_months' => 5,
                'price_per_month' => 270.00,
                'discount_percent' => 10.00,
            ],
            [
                'plan_id' => $premiumPlan->id,
                'min_months' => 6,
                'max_months' => 11,
                'price_per_month' => 240.00,
                'discount_percent' => 20.00,
            ],
            [
                'plan_id' => $premiumPlan->id,
                'min_months' => 12,
                'max_months' => null,
                'price_per_month' => 200.00,
                'discount_percent' => 33.33,
            ],
        ];

        foreach ($pricingTiers as $tier) {
            DB::table('plan_pricing_tiers')->updateOrInsert(
                [
                    'plan_id' => $tier['plan_id'],
                    'min_months' => $tier['min_months'],
                    'max_months' => $tier['max_months']
                ],
                array_merge($tier, [
                    'company_id' => null,
                    'created_by' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }

        // 4. تهيئة الكوبونات
        $coupons = [
            [
                'code' => 'SAVE20',
                'discount_type' => 'percent',
                'value' => 20.00,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addYear(),
                'max_uses' => 100,
                'used_count' => 0,
                'is_cumulative' => false,
                'status' => true,
            ],
            [
                'code' => 'SAVE50EGP',
                'discount_type' => 'fixed',
                'value' => 50.00,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addYear(),
                'max_uses' => 50,
                'used_count' => 0,
                'is_cumulative' => false,
                'status' => true,
            ]
        ];

        foreach ($coupons as $coupon) {
            DB::table('coupons')->updateOrInsert(
                ['code' => $coupon['code']],
                array_merge($coupon, [
                    'company_id' => null,
                    'created_by' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }
    }
}
